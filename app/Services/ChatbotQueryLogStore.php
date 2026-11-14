<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

/** Durable audit records; independent of expiring conversation-cache documents. */
class ChatbotQueryLogStore
{
    private const TEXT_COLUMNS = ['question', 'answer', 'tools_json', 'llm_calls_json', 'feedback'];

    public function query(): Builder
    {
        return DB::connection(config('chatbot.logs.connection'))->table('chatbot_query_logs');
    }

    public function insert(array $values): bool
    {
        return $this->safely(fn () => $this->write($values));
    }

    public function finish(string $id, array $values): bool
    {
        return $this->safely(fn () => $this->write($values, $id));
    }

    public function feedback(string $id, int $userId, ?int $rating, ?string $feedback): bool
    {
        $row = $this->query()->where('id', $id)->where('user_id', $userId)->first(['status']);
        abort_if($row === null, 404);
        abort_if($row->status === 'running', 409, 'This response is still running.');
        $this->write(['rating' => $rating, 'feedback' => $feedback, 'feedback_at' => $this->utcNow()], $id, $userId);
        return true;
    }

    public function feedbackFor(int $userId, array $ids): array
    {
        if ($ids === []) return [];
        try {
            $rows = $this->query()->where('user_id', $userId)->whereIn('id', $ids)->get(['id', 'rating', 'feedback']);
            $result = [];
            foreach ($rows as $row) $result[$row->id] = ['rating' => $row->rating, 'feedback' => $this->text($row->feedback)];
            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    public function search(array $filters)
    {
        $query = $this->query();
        if (($filters['q'] ?? '') !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
            $query->where(function (Builder $q) use ($term) {
                foreach (['question', 'answer', 'feedback', 'provider', 'model', 'tools_json'] as $field) {
                    $q->orWhereRaw("lower({$field}) like ? escape '!'", [$term]);
                }
            });
        }
        $nameParts = preg_split('/\s+/u', trim($filters['user_name'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        if ($nameParts !== []) {
            // Resolve on the application connection; logs may live in another database.
            // Each word must match either name field, allowing first, last, or full names.
            $profiles = DB::table('user_profile');
            foreach ($nameParts as $part) {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($part)).'%';
                $profiles->where(function (Builder $names) use ($term) {
                    $names->whereRaw("lower(first_name) like ? escape '!'", [$term])
                        ->orWhereRaw("lower(last_name) like ? escape '!'", [$term]);
                });
            }
            $query->whereIn('user_id', $profiles->distinct()->pluck('user_id')->all());
        }
        if (isset($filters['user_id'])) $query->where('user_id', $filters['user_id']);
        if (($filters['rating'] ?? '') === 'unrated') $query->whereNull('rating');
        elseif (($filters['rating'] ?? '') !== '') $query->where('rating', (int) $filters['rating']);
        if (($filters['status'] ?? '') !== '') $query->where('status', $filters['status']);
        $timezone = config('chatbot.logs.display_timezone', 'America/New_York');
        if (!empty($filters['from'])) $query->where('started_at', '>=', CarbonImmutable::parse($filters['from'], $timezone)->utc()->format('Y-m-d H:i:s'));
        if (!empty($filters['to'])) $query->where('started_at', '<', CarbonImmutable::parse($filters['to'], $timezone)->addDay()->utc()->format('Y-m-d H:i:s'));
        $sort = ['newest' => 'started_at', 'slowest' => 'duration_ms', 'tokens' => 'total_tokens'][$filters['sort'] ?? 'newest'];
        $page = $query->orderByDesc($sort)->orderByDesc('id')->paginate(25)->withQueryString();
        // Resolve names in the application database, even if logs use another connection.
        // Batch only this page's IDs so lookup cost does not grow with log history.
        $userIds = $page->getCollection()->pluck('user_id')->unique()->all();
        $projectIds = $page->getCollection()->filter(static fn ($row) => $row->scope === 'project'
            && ctype_digit((string) $row->cohort_id))->pluck('cohort_id')->unique()->all();
        $profiles = $userIds === [] ? collect() : DB::table('user_profile')->whereIn('user_id', $userIds)
            ->get(['user_id', 'first_name', 'last_name'])->keyBy('user_id');
        $projects = $projectIds === [] ? collect() : DB::table('projects')->whereIn('id', $projectIds)
            ->pluck('name', 'id');
        $page->getCollection()->transform(function ($row) use ($profiles, $projects) {
            $profile = $profiles->get($row->user_id);
            $row->user_name = trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) ?: 'User '.$row->user_id;
            $row->cohort_name = match ($row->scope) {
                'project' => $projects->get($row->cohort_id) ?: 'Project '.$row->cohort_id,
                'global' => 'All authorized cohorts',
                default => $row->cohort_id,
            };
            foreach (self::TEXT_COLUMNS as $column) $row->$column = $this->text($row->$column ?? null);
            return $row;
        });
        return $page;
    }

    private function write(array $values, ?string $id = null, ?int $owner = null): void
    {
        $connection = DB::connection(config('chatbot.logs.connection'));
        if ($connection->getDriverName() !== 'oracle') {
            $query = $this->query();
            if ($id === null) $query->insert($values);
            else {
                $query->where('id', $id);
                if ($owner !== null) $query->where('user_id', $owner);
                $query->update($values);
            }
            return;
        }
        // Explicit CLOB binds support questions, answers and feedback beyond 4 KB.
        $grammar = $connection->getQueryGrammar();
        $table = $grammar->wrapTable('chatbot_query_logs');
        $columns = array_keys($values);
        $bindings = array_values($values);
        $names = array_map(static fn ($i) => ':v'.$i, array_keys($bindings));
        $quoted = array_map($grammar->wrap(...), $columns);
        if ($id === null) $sql = 'insert into '.$table.' ('.implode(',', $quoted).') values ('.implode(',', $names).')';
        else {
            $sets = array_map(static fn ($column, $name) => $column.'='.$name, $quoted, $names);
            $sql = 'update '.$table.' set '.implode(',', $sets).' where '.$grammar->wrap('id').'=:record_id';
            if ($owner !== null) $sql .= ' and '.$grammar->wrap('user_id').'=:owner_id';
        }
        $statement = $connection->getPdo()->prepare($sql);
        foreach ($bindings as $index => &$value) {
            $type = $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            if ($value !== null && in_array($columns[$index], self::TEXT_COLUMNS, true)) $type = SQLT_CLOB;
            $statement->bindParam($names[$index], $value, $type);
        }
        unset($value);
        if ($id !== null) $statement->bindValue(':record_id', $id);
        if ($owner !== null) $statement->bindValue(':owner_id', $owner, PDO::PARAM_INT);
        $statement->execute();
    }

    private function safely(callable $write): bool
    {
        try { $write(); return true; }
        catch (Throwable $exception) {
            // Do not leak question/answer text through SQL exception bindings.
            Log::warning('Chatbot query log could not be saved. Check chatbot_query_logs schema and permissions.', ['exception' => $exception::class]);
            return false;
        }
    }

    private function text(mixed $value): ?string
    {
        return is_resource($value) ? stream_get_contents($value) : ($value === null ? null : (string) $value);
    }

    private function utcNow(): string { return CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'); }
}
