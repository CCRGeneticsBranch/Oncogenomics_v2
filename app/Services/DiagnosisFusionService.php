<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DiagnosisFusionService
{
    public function pairs(int $userId, string $diagnosis, array $filters): array
    {
        return $this->queryPairs($userId, $diagnosis, null, $filters);
    }

    public function projectPairs(int $userId, int $projectId, array $filters, string $fusionTable = 'var_fusion'): array
    {
        return $this->queryPairs($userId, $filters['diagnosis'] ?? null, $projectId, $filters, $fusionTable);
    }

    private function queryPairs(int $userId, ?string $diagnosis, ?int $projectId, array $filters, string $fusionTable = 'var_fusion'): array
    {
        $cutoff = max(1, (int) ($filters['cutoff'] ?? 1));
        $query = DB::table($fusionTable.' as v')->whereExists(function (Builder $q) use ($userId, $diagnosis, $projectId) {
            $q->selectRaw('1')->from('project_samples as p')
                ->join('user_projects as u', 'u.project_id', '=', 'p.project_id')
                ->where('u.user_id', $userId)
                ->whereColumn('p.sample_id', 'v.sample_id')->whereColumn('p.patient_id', 'v.patient_id');
            if ($diagnosis !== null) $q->where('p.diagnosis', $diagnosis);
            if ($projectId !== null) $q->where('p.project_id', $projectId);
        });
        $gene = strtoupper(trim($filters['gene'] ?? ''));
        $partner = strtoupper(trim($filters['partner'] ?? ''));
        if ($gene !== '') {
            $query->where(function (Builder $q) use ($gene, $partner) {
                $q->where(function (Builder $side) use ($gene, $partner) {
                    $side->whereRaw('upper(v.left_gene) = ?', [$gene]);
                    if ($partner !== '') $side->whereRaw('upper(v.right_gene) = ?', [$partner]);
                })->orWhere(function (Builder $side) use ($gene, $partner) {
                    $side->whereRaw('upper(v.right_gene) = ?', [$gene]);
                    if ($partner !== '') $side->whereRaw('upper(v.left_gene) = ?', [$partner]);
                });
            });
        }
        if (isset($filters['tier'])) $query->whereRaw('substr(v.var_level, 1, 1) = ?', [$filters['tier']]);
        $fields = ['v.left_chr', 'v.left_gene', 'v.right_chr', 'v.right_gene'];
        if (empty($filters['caller'])) {
            return array_map(static fn ($r) => (array) $r, $query->select($fields)
                ->selectRaw('substr(v.var_level, 1, 1) as tier, count(distinct v.patient_id) as patient_count')
                ->groupBy(...$fields)->groupByRaw('substr(v.var_level, 1, 1)')
                ->havingRaw('count(distinct v.patient_id) >= ?', [$cutoff])->get()->all());
        }
        // Caller names are JSON keys. Reuse the parser rather than matching
        // arbitrary substrings or support values; gene/tier filters run in SQL.
        $rows = $query->select($fields)->addSelect(['v.patient_id', 'v.tool'])
            ->selectRaw('substr(v.var_level, 1, 1) as tier')->get()->all();
        $rows = app(CohortAnalysisService::class)->filterFusionRowsByCaller($rows, $filters['caller']);
        $groups = [];
        foreach ($rows as $row) {
            $pair = array_intersect_key((array) $row, array_flip(['left_chr', 'left_gene', 'right_chr', 'right_gene', 'tier']));
            $key = json_encode($pair);
            $groups[$key]['pair'] = $pair;
            $groups[$key]['patients'][(string) $row->patient_id] = true;
        }
        $groups = array_filter($groups, static fn ($g) => count($g['patients']) >= $cutoff);
        return array_values(array_map(static fn ($g) => $g['pair'] + ['patient_count' => count($g['patients'])], $groups));
    }
}
