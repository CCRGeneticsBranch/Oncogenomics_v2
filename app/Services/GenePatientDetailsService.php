<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Shared by the gene-page HTTP drill-downs and MCP wrappers. */
class GenePatientDetailsService
{
    /** Patient denominators for gene-page percentages, within authorized memberships. */
    public function summaryDenominators(int $userId, string $category): array
    {
        $query = DB::table('project_patients as p')
            ->join('user_projects as u', 'u.project_id', '=', 'p.project_id')
            ->where('u.user_id', $userId);
        $fields = $category === 'project' ? ['p.project_id'] : ['p.diagnosis'];
        $rows = $query->select($fields)->selectRaw('count(distinct p.patient_id) as patient_count')
            ->groupBy(...$fields)->get();
        $totals = [];
        foreach ($rows as $row) {
            $totals[$category === 'project' ? 'p'.$row->project_id : $row->diagnosis] = (int) $row->patient_count;
        }
        return $totals;
    }

    public function patients(int $userId, string $kind, array $filters): array
    {
        $table = match ($kind) {
            'mutation' => 'var_gene_tier',
            'cnv' => ($filters['cat_type'] ?? 'diagnosis') === 'project' ? 'var_cnv_gene_level' : 'var_cnv_genes',
            default => 'var_fusion',
        };
        // Join only authorized memberships, including when no group was supplied.
        // Project names in the result must also come only from these memberships.
        $query = DB::table('project_patients as p')
            ->join('user_projects as u', 'u.project_id', '=', 'p.project_id')
            ->where('u.user_id', $userId);
        $category = $filters['category'] ?? null;
        if ($category !== null && $category !== '') {
            $query->where(($filters['cat_type'] ?? 'diagnosis') === 'project' ? 'u.project_name' : 'p.diagnosis', $category);
        }
        $query->whereExists(function (Builder $variants) use ($table, $kind, $filters) {
            $variants->selectRaw('1')->from($table.' as v')->whereColumn('v.patient_id', 'p.patient_id');
            if ($kind === 'mutation') {
                $variants->where('v.gene', $filters['gene_id'])->where('v.type', $filters['type']);
                $this->tiers($variants, $filters['type'] === 'germline' ? 'v.germline_level' : 'v.somatic_level', $filters['tiers'], false);
            } elseif ($kind === 'cnv') {
                $variants->where('v.gene', $filters['gene_id']);
                $variants->where('v.cnt', $filters['cnv_event'] === 'Amplified' ? '>=' : '<=',
                    $filters['cnv_event'] === 'Amplified' ? $filters['min_amplified'] : $filters['max_deleted']);
            } else {
                if (isset($filters['left_gene'], $filters['right_gene'])) {
                    $variants->where('v.left_gene', $filters['left_gene'])->where('v.right_gene', $filters['right_gene']);
                } else {
                    $variants->where(fn (Builder $genes) => $genes->where('v.left_gene', $filters['gene_id'])
                        ->orWhere('v.right_gene', $filters['gene_id']));
                }
                if (($filters['fusion_type'] ?? 'All') !== 'All') $variants->where('v.type', $filters['fusion_type']);
                $this->tiers($variants, 'v.var_level', $filters['tiers'], true);
            }
        });
        $rows = $query->distinct()->get(['p.patient_id', 'p.diagnosis', 'u.project_name']);
        $groups = [];
        foreach ($rows as $row) {
            $key = json_encode([$row->patient_id, $row->diagnosis]);
            $groups[$key]['patient'] = [$row->patient_id, $row->diagnosis];
            $groups[$key]['projects'][$row->project_name] = true;
        }
        ksort($groups);
        return array_values(array_map(static function ($group) use ($kind, $filters) {
            $names = array_keys($group['projects']);
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            return array_merge($group['patient'], $kind === 'cnv' ? [$filters['cnv_event']] : [], [implode(', ', $names)]);
        }, $groups));
    }

    public function cnvPatients(int $userId, array $filters): array
    {
        return array_merge(
            $this->patients($userId, 'cnv', $filters + ['cnv_event' => 'Amplified']),
            $this->patients($userId, 'cnv', $filters + ['cnv_event' => 'Deleted']),
        );
    }

    private function tiers(Builder $query, string $field, string $tiers, bool $fusion): void
    {
        $query->where(function (Builder $group) use ($field, $tiers, $fusion) {
            foreach (explode(',', $tiers) as $tier) {
                $tier = trim($tier);
                if ($tier === 'No Tier') {
                    $group->orWhereNull($field);
                } elseif ($fusion) {
                    $group->orWhere($field, 'like', trim(str_replace('Tier', '', $tier)).'%');
                } elseif (str_replace(' ', '', $tier) === 'Tier1') {
                    $group->orWhere($field, 'like', 'Tier 1%');
                } else {
                    $group->orWhere($field, preg_replace('/^Tier\s*([1-4])$/', 'Tier $1', $tier));
                }
            }
        });
    }
}
