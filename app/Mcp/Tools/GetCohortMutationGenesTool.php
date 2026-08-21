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
    use ResolvesCohortInput;

    protected string $name = 'getCohortMutationGenes';

    protected string $description = <<<'MARKDOWN'
        Summarize mutation genes for either an authorized project or an
        authorized cancer type (diagnosis), grouped by tier importance. Counts
        are distinct patients. The type parameter accepts germline, somatic,
        ranseq, or variants.

        First determine the cohort type from the user's wording. A named data
        project is cohort_type=project: call getProjects first and use its
        numeric project ID. A diagnosis/cancer type is
        cohort_type=cancer_type: call getCancerTypes first and use its exact
        Cancer Type value. Never guess the cohort type or ID.
        When a trusted host page supplies a fixed cohort context, that context
        has already completed this resolution step.
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

            return $this->cohortResponse($content, $cohortType, $cohortId, $this->name);
        } catch (\Throwable $e) {
            return Response::structured([
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
