<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesCohortInput;
use App\Models\CancerType;
use App\Models\Project;
use App\Models\VarAnnotation;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class GetCohortMutationGenesTool extends BaseMutationGenesTool
{
    protected bool $supportsCompactOutput = true;

    use ResolvesCohortInput;

    protected string $name = 'getCohortMutationGenes';

    protected string $description = <<<'MARKDOWN'
        Summarize mutation genes by context and tier for an authorized project or diagnosis. Counts are distinct patients per gene/tier, not additive patient totals. The type parameter selects the call source, not the tier annotation: somatic means paired tumor/normal; variants means tumor-only calls, which also have somatic tiers. For cell lines or tumor-only cohorts use variants and read data.somatic. An empty somatic source does not establish absence of somatic-tier variants. ranseq means RNA-seq variants.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'cohort_type' => 'required|string|in:project,cancer_type',
            'cohort_id' => 'required',
            'type' => 'required|string|in:'.implode(',', self::REQUEST_TYPES),
        ]);

        try {
            $cohortType = (string) $validated['cohort_type'];
            [$cohortId, $error] = $this->resolveCohortId(
                $cohortType,
                $validated['cohort_id'],
                $this->name
            );
            if ($error !== null) {
                return $error;
            }

            $content = $cohortType === 'project'
                ? $this->projectMutationGenes((int) $cohortId, (string) $validated['type'])
                : $this->cancerTypeMutationGenes((string) $cohortId, (string) $validated['type']);

            if (($content['status'] ?? '') === 'success') {
                $content['source_note'] = match ($validated['type']) {
                    'somatic' => 'Only paired tumor/normal somatic calls were queried. This does not include tumor-only variants with somatic tier annotations.',
                    'variants' => 'Tumor-only calls; data.somatic contains somatic tier annotations, not confirmed paired tumor/normal somatic calls.',
                    'germline' => 'Germline calls and germline tier annotations.',
                    default => 'RNA-seq calls, with separate germline and somatic tier annotations.',
                };
                if ($validated['type'] === 'somatic' && ($content['gene_count'] ?? null) === 0) {
                    $content['summary'] = ($content['summary'] ?? '').' No paired somatic calls matched. Before concluding no somatic-tier genes exist, query the same cohort with type=variants and inspect data.somatic; label any result as tumor-only calls.';
                }
            }
            return $this->cohortResponse($content, $cohortType, $cohortId, $this->name, $request);
        } catch (\Throwable $e) {
            return $this->resultResponse($request, [
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    protected function projectMutationGenes(int $projectId, string $type): array
    {
        $project = Project::getProject($projectId);
        if ($project === null) {
            return [
                'status' => 'error',
                'message' => "Project {$projectId} was not found or is not accessible to the authorized user.",
            ];
        }

        $sourceType = $this->internalType($type);
        $annotation = VarAnnotation::is_avia() ? 'AVIA' : 'Khanlab';
        $tierTable = $project->getProperty('var_tier_table') ?: 'var_tier_avia';
        $rows = $project->getVarGeneTier(
            $sourceType,
            'any',
            'any',
            $annotation,
            1,
            0,
            0,
            $tierTable
        );
        $summary = $this->summarizeRows($rows, $type);

        return [
            'status' => 'success',
            'project_id' => $projectId,
            'project_name' => trim((string) $project->name),
            'type' => $type,
            'source_type' => $sourceType,
            'count_unit' => 'distinct_patients',
            'data' => $summary['data'],
            'gene_count' => $summary['gene_count'],
            'display_type' => 'tiered_mutation_gene_summary',
            'summary' => "{$summary['gene_count']} mutation gene(s) found in project {$project->name}, grouped by tier.",
        ];
    }

    /** @return array<string, mixed> */
    protected function cancerTypeMutationGenes(string $cancerTypeId, string $type): array
    {
        if (!$this->isAvailableCancerType($cancerTypeId)) {
            return [
                'status' => 'error',
                'message' => "Cancer type {$cancerTypeId} is not an exact Cancer Type value available from getCancerTypes.",
            ];
        }

        $cancerType = CancerType::find($cancerTypeId);
        if ($cancerType === null) {
            return [
                'status' => 'error',
                'message' => "Cancer type {$cancerTypeId} was not found.",
            ];
        }

        $sourceType = $this->internalType($type);
        $annotation = VarAnnotation::is_avia() ? 'AVIA' : 'Khanlab';
        $rows = $cancerType->getVarGeneTier(
            $sourceType,
            'any',
            'any',
            $annotation,
            1,
            0,
            0,
            'var_tier_avia',
            'Y'
        );
        $summary = $this->summarizeRows($rows, $type);

        return [
            'status' => 'success',
            'cancer_type_id' => $cancerTypeId,
            'type' => $type,
            'source_type' => $sourceType,
            'count_unit' => 'distinct_patients',
            'data' => $summary['data'],
            'gene_count' => $summary['gene_count'],
            'display_type' => 'tiered_mutation_gene_summary',
            'summary' => "{$summary['gene_count']} mutation gene(s) found for {$cancerTypeId}, grouped by tier.",
        ];
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
                'type' => $this->typeSchema(),
            ]),
            'required' => ['cohort_type', 'cohort_id', 'type'],
            'additionalProperties' => false,
        ];
    }
}
