<?php

namespace App\Console\Commands;

use App\Services\Oncotree\CodexResearcher;
use App\Services\Oncotree\MappingUpdater;
use App\Services\Oncotree\EvidenceValidationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class UpdateOncotreeMappings extends Command
{
    protected $signature = 'oncotree:update-mappings
        {--dry-run : Preview mappings and email only chouh@nih.gov; do not change mappings or retry state}
        {--limit= : Maximum diagnoses to research; 0 means unlimited (default)}
        {--retry-unresolved : Ignore the cooldown for previously ambiguous/unresolved terms}';
    protected $description = 'Research unmapped cancer_types diagnoses against the local OncoTree TSV and email an audit summary.';

    public function handle(MappingUpdater $updater, CodexResearcher $researcher): int
    {
        $mode = (string) config('oncotree_mapping.mode', 'dev');
        if (!in_array($mode, ['dev', 'production'], true)) {
            $this->error('ONCOTREE_MAPPING_MODE must be dev or production.');
            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit') ?? config('oncotree_mapping.limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 0) {
            $this->error('Limit must be a non-negative integer; 0 means unlimited.');
            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $root = config('oncotree_mapping.directory');
        if (!is_dir($root) && !mkdir($root, 0700, true)) throw new \RuntimeException('Cannot create audit directory.');
        $lock = fopen($root.'/run.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) fclose($lock);
            $this->error('Another mapping run is active.');
            return self::FAILURE;
        }
        $runId = gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
        $directory = $root.'/'.$runId;
        mkdir($directory, 0700);
        $report = ['run_id' => $runId, 'mode' => $mode, 'dry_run' => $dry,
            'started_at' => gmdate('c'), 'instance' => config('app.url'),
            'model' => config('oncotree_mapping.model') ?: 'Codex default', 'results' => []];
        $failed = false;
        try {
            $recipients = $updater->recipients($dry ? 'dev' : $mode);
            if ($recipients === []) throw new \RuntimeException('No valid summary email recipients; run aborted.');
            $path = config('oncotree_mapping.taxonomy');
            $taxonomy = $updater->taxonomy($path);
            $report['taxonomy_sha256'] = hash_file('sha256', $path);
            $statePath = $root.'/attempts.json';
            $state = is_file($statePath) ? json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR) : [];
            if (!is_array($state)) throw new \RuntimeException('Invalid attempts state.');
            $diagnoses = $updater->unmapped();
            $report['unmapped_total'] = count($diagnoses);
            $fingerprint = static fn ($d) => hash('sha256', json_encode([$d, $report['taxonomy_sha256'], 'llm_semantic_v3_normal'], JSON_THROW_ON_ERROR));
            // New terms first, then the oldest attempted failures; avoid starvation.
            usort($diagnoses, static fn ($a, $b) => ($state[$fingerprint($a)]['time'] ?? 0) <=> ($state[$fingerprint($b)]['time'] ?? 0)
                ?: strcmp($a['id'], $b['id']));
            $processed = 0;
            $report['cooldown_skipped'] = 0;
            foreach ($diagnoses as $diagnosis) {
                $key = $fingerprint($diagnosis);
                $previous = $state[$key] ?? [];
                if (!$this->option('retry-unresolved') && in_array($previous['status'] ?? '', ['ambiguous', 'unresolved'], true)
                    && ($previous['time'] ?? 0) > time() - max(1, (int) config('oncotree_mapping.retry_days')) * 86400) {
                    $report['cooldown_skipped']++;
                    continue;
                }
                if ($limit > 0 && $processed >= $limit) continue;
                $processed++;
                $item = ['diagnosis' => $diagnosis['id'], 'status' => 'error'];
                $work = $directory.'/term-'.$processed;
                mkdir($work, 0700);
                try {
                    if (trim($diagnosis['id']) === '' || strlen($diagnosis['id']) > 1000) {
                        throw new \InvalidArgumentException('Diagnosis is empty or too long for oncotree_mapping.');
                    }
                    $feedback = null;
                    for ($attempt = 1; $attempt <= 2; $attempt++) {
                        $attemptDirectory = $work.'/attempt-'.$attempt;
                        mkdir($attemptDirectory, 0700);
                        $proposal = $researcher->research($diagnosis, $taxonomy, $attemptDirectory, $feedback);
                        $this->save($attemptDirectory.'/proposal.json', $proposal);
                        try {
                            $item = $updater->validate($proposal, $diagnosis, $taxonomy);
                            $item['research_attempts'] = $attempt;
                            break;
                        } catch (EvidenceValidationException $e) {
                            $this->save($attemptDirectory.'/evidence-error.json', $e->details);
                            if ($attempt === 2) throw $e;
                            $feedback = ['failure' => $e->details, 'previous_proposal' => $proposal];
                        }
                    }
                    // Save validated evidence before any commit; retain raw proposal separately.
                    $this->save($work.'/validated.json', $item);
                    if ($item['status'] === 'matched') {
                        $item['status'] = $dry ? 'would_insert' : ($updater->insert($diagnosis, $item['code']) ? 'inserted' : 'already_mapped');
                    }
                } catch (\Throwable $e) {
                    $item = ['diagnosis' => $diagnosis['id'], 'status' => 'error',
                        'reason' => 'Research, validation, or insertion failed. See the private run report.'];
                    // Database exceptions may include connection details: never email them.
                    $this->save($work.'/error.json', ['type' => get_class($e), 'message' => $e->getMessage(),
                        'evidence' => $e instanceof EvidenceValidationException ? $e->details : null]);
                    $failed = true;
                }
                $report['results'][] = $item;
                $this->save($directory.'/report.json', $report);
                if (!$dry) {
                    $state[$key] = ['time' => time(), 'status' => $item['status']];
                    $this->save($statePath, $state);
                }
                $this->line($item['status'].': '.$diagnosis['id']);
            }
            $report['processed'] = $processed;
            $report['remaining_unprocessed'] = count($diagnoses) - $processed - $report['cooldown_skipped'];
        } catch (\Throwable $e) {
            $failed = true;
            $report['fatal_error'] = 'Job failed; see the private error report.';
            $this->save($directory.'/error.json', ['type' => get_class($e), 'message' => $e->getMessage()]);
        } finally {
            try {
                $report['finished_at'] = gmdate('c');
                // Reporting only: patient identifiers are never passed to the researcher.
                foreach ($report['results'] as &$result) {
                    try {
                        $result['patient_ids'] = DB::table('patients')
                            ->where('diagnosis', $result['diagnosis'])->whereNotNull('patient_id')
                            ->select('patient_id as patient_id')->distinct()->orderBy('patient_id')
                            ->get()->map(static fn ($row) => (string) $row->patient_id)->all();
                    } catch (\Throwable $e) {
                        $result['patient_lookup_failed'] = true;
                        $failed = true;
                    }
                }
                unset($result);
                $body = $this->summary($report);
                file_put_contents($directory.'/summary.txt', $body);
                try {
                    $recipients = $updater->recipients($dry ? 'dev' : $mode);
                    if ($recipients === []) throw new \RuntimeException('No summary recipients.');
                    foreach ($recipients as $email) {
                        Mail::raw($body, static function ($message) use ($email, $mode, $dry) {
                            $message->to($email)->subject('[Oncogenomics]['.($dry ? 'DRY RUN' : $mode).'] OncoTree mapping summary');
                        });
                    }
                    $report['email_status'] = 'sent';
                    $report['email_recipients'] = $recipients;
                } catch (\Throwable $e) {
                    $failed = true;
                    $report['email_status'] = 'failed';
                    $this->save($directory.'/mail-error.json', ['type' => get_class($e), 'message' => $e->getMessage()]);
                    $this->error($dry ? 'Dry-run email failed. No mappings were inserted. See the run report.' : 'Summary email failed. Mappings already committed remain applied. See the run report.');
                }
                $this->save($directory.'/report.json', $report);
                $this->info('Report: '.$directory.'/report.json');
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function save(string $path, array $value): void
    {
        $tmp = $path.'.tmp';
        if (file_put_contents($tmp, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) === false
            || !rename($tmp, $path)) throw new \RuntimeException('Unable to save audit/state file.');
        chmod($path, 0600);
    }

    private function summary(array $report): string
    {
        $counts = array_count_values(array_column($report['results'], 'status'));
        $lines = ['Clinomics OncoTree mapping update',
            'Run: '.$report['run_id'], 'Mode: '.$report['mode'].($report['dry_run'] ? ' (DRY RUN)' : ''),
            'Taxonomy SHA256: '.($report['taxonomy_sha256'] ?? 'unavailable'),
            'Unmapped at start: '.($report['unmapped_total'] ?? 'unknown'),
            'Processed: '.($report['processed'] ?? count($report['results'])),
            'Deferred by cooldown: '.($report['cooldown_skipped'] ?? 0),
            'Remaining beyond batch: '.($report['remaining_unprocessed'] ?? 'unknown'),
            'Outcomes: '.json_encode($counts), $report['fatal_error'] ?? '', ''];
        foreach ($report['results'] as $item) {
            $lines[] = $item['diagnosis'].' => '.($item['code'] ?? '(no code)').' ['.$item['status'].']';
            $lines[] = 'Patients: '.(!empty($item['patient_lookup_failed'])
                ? 'unavailable (lookup failed)'
                : (implode(', ', $item['patient_ids'] ?? []) ?: 'none'));
            $lines[] = $item['reason'] ?? '';
            foreach ($item['sources'] ?? [] as $source) $lines[] = 'Source: '.$source['url'];
            $lines[] = '';
        }
        $lines[] = 'Existing mappings were not overwritten. Ambiguous/unresolved terms remain unmapped.';
        return implode("\n", $lines);
    }
}
