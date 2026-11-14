<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesCohortInput;
use App\Models\CancerType;
use App\Models\Project;
use App\Models\User;
use App\Services\DiagnosisFusionService;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

class GetFusionCohortDetailTool extends LegacySchemaTool
{
    use ResolvesCohortInput;

    protected bool $supportsCompactOutput = true;

    protected string $name = 'getFusionCohortDetail';

    protected string $description = <<<'MARKDOWN'
        List fusion genes/pairs by tier and distinct-patient count for an authorized project or cancer type, without requiring a gene. Use for questions like "which Tier 1 fusion genes occur in this project?" cutoff is the minimum distinct patients per pair/tier (inclusive, default 2); use cutoff=1 to include single-patient fusions. Filter gene on either side, partner in either orientation, caller, and tier. Counts are per pair/tier and cannot be added as unique patients. Resolve cohort_type/cohort_id with getProjects or getCancerTypes.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'cohort_type' => 'required|string|in:project,cancer_type',
            'cohort_id' => 'required',
            'diagnosis' => 'nullable|string|max:200',
            'cutoff' => 'nullable|integer|min:1',
            'gene' => 'nullable|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'partner' => 'nullable|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'caller' => 'nullable|string|max:100',
            'tier' => 'nullable|string|in:1,2,3,4',
        ]);

        try {
            $cohortType = $validated['cohort_type'];
            [$cohortId, $error] = $this->resolveCohortId($cohortType, $validated['cohort_id'], $this->name);
            if ($error !== null) return $error;
            if ($cohortType === 'cancer_type' && !$this->isAvailableCancerType((string) $cohortId)) {
                throw new \InvalidArgumentException('cohort_id must be an exact Cancer Type value available from getCancerTypes.');
            }
            if ($cohortType === 'cancer_type' && !empty($validated['diagnosis'])) {
                throw new \InvalidArgumentException('diagnosis is only supported within a project cohort.');
            }
            $validated['cutoff'] = (int) ($validated['cutoff'] ?? 2);
            $validated['diagnosis'] = isset($validated['diagnosis'])
                ? (trim($validated['diagnosis']) ?: null) : null;

            if (!empty($validated['partner']) && empty($validated['gene'])) {
                throw new \InvalidArgumentException('partner requires gene.');
            }
            $fusionPairs = array_map(static fn (array $row): array => [
                'left_chr' => trim((string) ($row['left_chr'] ?? '')),
                'left_gene' => trim((string) $row['left_gene']),
                'right_chr' => trim((string) ($row['right_chr'] ?? '')),
                'right_gene' => trim((string) $row['right_gene']),
                'tier' => empty($row['tier']) ? null : 'Tier '.$row['tier'],
                'patient_count' => (int) $row['patient_count'],
            ], $this->fusionPairs($cohortType, $cohortId, $validated));

            usort($fusionPairs, static function (array $left, array $right): int {
                return ($right['patient_count'] <=> $left['patient_count'])
                    ?: strcasecmp($left['left_gene'], $right['left_gene'])
                    ?: strcasecmp($left['right_gene'], $right['right_gene'])
                    ?: strcasecmp((string) $left['tier'], (string) $right['tier']);
            });

            $table = [
                'cols' => [
                    ['title' => 'Left Chr'],
                    ['title' => 'Left Gene'],
                    ['title' => 'Right Chr'],
                    ['title' => 'Right Gene'],
                    ['title' => 'Tier'],
                    ['title' => 'Patient Count'],
                ],
                'data' => array_map(static fn (array $pair): array => [
                    $pair['left_chr'],
                    $pair['left_gene'],
                    $pair['right_chr'],
                    $pair['right_gene'],
                    $pair['tier'],
                    $pair['patient_count'],
                ], $fusionPairs),
            ];
            $count = count($fusionPairs);

            return $this->resultResponse($request, [
                'status' => 'success',
                'action' => $this->name,
                'cohort_type' => $cohortType,
                'cohort_id' => $cohortId,
                'cutoff' => $validated['cutoff'],
                'diagnosis' => $validated['diagnosis'] ?? null,
                'fusion_pairs' => $fusionPairs,
                'gene' => $validated['gene'] ?? null,
                'partner' => $validated['partner'] ?? null,
                'caller' => $validated['caller'] ?? null,
                'tier' => $validated['tier'] ?? null,
                'fusion_pair_count' => $count,
                'count_unit' => 'distinct_patients',
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => (string) json_encode($table, JSON_UNESCAPED_SLASHES),
                'title' => 'Fusion Cohort Detail',
                'summary' => $count === 1
                    ? "1 fusion pair found for {$cohortType} {$cohortId} with at least {$validated['cutoff']} distinct patients per pair/tier."
                    : "{$count} fusion pairs found for {$cohortType} {$cohortId} with at least {$validated['cutoff']} distinct patients per pair/tier.",
            ]);
        } catch (\Throwable $e) {
            return $this->resultResponse($request, [
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function fusionPairs(string $cohortType, int|string $cohortId, array $filters): array
    {
        $user = User::getCurrentUser();
        if ($user === null) throw new \RuntimeException('An authenticated MCP user is required.');
        $service = app(DiagnosisFusionService::class);
        if ($cohortType === 'cancer_type') {
            return $service->pairs((int) $user->id, (string) $cohortId, $filters);
        }
        $project = Project::getProject((int) $cohortId);
        if ($project === null) throw new \RuntimeException('Project is not accessible.');
        $table = $project->getProperty('var_fusion_table') ?: 'var_fusion';
        return $service->projectPairs((int) $user->id, (int) $cohortId, $filters, $table);
    }

    protected function isAvailableCancerType(string $cancerTypeId): bool
    {
        [, $rows] = CancerType::getAll();

        foreach ($rows as $row) {
            if ((string) ($row[0] ?? '') === $cancerTypeId) {
                return true;
            }
        }

        return false;
    }

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->cohortProperties(), [
                'gene' => ['type' => 'string', 'description' => 'Exact gene symbol on either side.'],
                'partner' => ['type' => 'string', 'description' => 'Requires gene; match pair in either orientation.'],
                'caller' => ['type' => 'string', 'description' => 'Exact caller name, case-insensitive.'],
                'tier' => ['type' => 'string', 'enum' => ['1', '2', '3', '4']],
                'diagnosis' => ['type' => 'string', 'description' => 'Optional exact diagnosis filter within a project cohort.'],
                'cutoff' => ['type' => 'integer', 'minimum' => 1, 'default' => 2,
                    'description' => 'Minimum DISTINCT patients per fusion pair/tier, inclusive. Default 2; set 1 for single-patient fusions. Not a row limit.'],
            ]),
            'required' => ['cohort_type', 'cohort_id'],
            'additionalProperties' => false,
        ];
    }
}
