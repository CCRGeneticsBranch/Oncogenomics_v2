<?php

namespace App\Services;

use App\Models\VarAnnotation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CohortMutationDetailsService
{
    public function fetch(int $userId, string $cohortType, int|string $cohortId, array $filters, int $limit, int $offset, bool $summaryOnly = false): array
    {
        $view = (string) VarAnnotation::getTableName();
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.]*$/', $view)) {
            throw new \RuntimeException('The variant annotation view is not configured.');
        }
        $query = DB::table($view.' as v')->where('v.type', $filters['type'])
            ->whereRaw('upper(v.gene) = ?', [strtoupper($filters['gene'])])
            ->where('v.genome_version', $filters['genome_version'] ?? 'hg19')
            ->whereExists(function (Builder $q) use ($userId, $cohortType, $cohortId) {
                $q->selectRaw('1')->from('project_samples as p')
                    ->join('user_projects as u', 'u.project_id', '=', 'p.project_id')
                    ->where('u.user_id', $userId)->whereColumn('p.patient_id', 'v.patient_id')
                    ->whereColumn('p.sample_id', 'v.sample_id');
                if ($cohortType === 'project') $q->where('p.project_id', (int) $cohortId);
                else $q->where('p.diagnosis', (string) $cohortId);
            });
        $query->leftJoin('var_tier_avia as t', function ($join) {
            foreach (['patient_id', 'sample_id', 'type', 'chromosome', 'start_pos', 'end_pos', 'ref', 'alt', 'gene'] as $field) {
                $join->on('t.'.$field, '=', 'v.'.$field);
            }
        });
        if (!empty($filters['sample_id'])) $query->where('v.sample_id', $filters['sample_id']);
        if (isset($filters['min_vaf'])) $query->where('v.vaf', '>=', $filters['min_vaf']);
        $tierContext = $filters['tier_context'] ?? ($filters['type'] === 'germline' ? 'germline' : 'somatic');
        if (isset($filters['tier'])) {
            $query->where('t.'.$tierContext.'_level', 'like', 'Tier '.$filters['tier'].'%');
        }
        $query->select(['v.patient_id', 'v.case_id', 'v.sample_id', 'v.gene', 'v.chromosome',
            'v.start_pos', 'v.end_pos', 'v.ref', 'v.alt', 'v.genome_version', 'v.type',
            'v.so as consequence', 'v.achange as protein_change', 'v.vaf', 'v.total_cov', 'v.var_cov',
            't.germline_level as germline_tier', 't.somatic_level as somatic_tier'])->distinct();
        $rowsQuery = DB::query()->fromSub($query, 'm');
        $total = (clone $rowsQuery)->count();
        $patientCount = (clone $rowsQuery)->distinct()->count('m.patient_id');
        $sampleCount = (clone $rowsQuery)->distinct()->count('m.sample_id');
        $rows = [];
        if (!$summaryOnly) {
            foreach (['patient_id', 'sample_id', 'case_id', 'chromosome', 'start_pos', 'end_pos', 'ref', 'alt',
                'gene', 'genome_version', 'type', 'consequence', 'protein_change', 'vaf', 'total_cov', 'var_cov',
                'germline_tier', 'somatic_tier'] as $field) $rowsQuery->orderBy('m.'.$field);
            $rows = array_map(static fn ($row) => (array) $row, $rowsQuery->offset($offset)->limit($limit)->get()->all());
        }
        return ['rows' => $rows, 'total_rows' => $total, 'patient_count' => $patientCount, 'sample_count' => $sampleCount];
    }
}
