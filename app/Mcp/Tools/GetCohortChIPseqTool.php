<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\CancerTypeController;
use App\Http\Controllers\ProjectController;
use App\Mcp\Tools\Concerns\ResolvesCohortInput;
use App\Models\CancerType;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetCohortChIPseqTool extends Tool
{
    use ResolvesCohortInput;

    protected string $name = 'getCohortChIPseq';

    protected string $description = <<<'MARKDOWN'
        Return ChIP-seq QC, read mapping, duplication, peak, and
        super-enhancer metrics for either an authorized project or an
        authorized cancer type (diagnosis).

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
                ? $this->projectChIPseq((int) $cohortId)
                : $this->cancerTypeChIPseq((string) $cohortId);

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
    protected function projectChIPseq(int $projectId): array
    {
        $project = Project::getProject($projectId);
        $tableJson = $this->normalizeToJson(
            app(ProjectController::class)->getChIPseq($projectId, 'json')
        );

        if ($project === null || trim($tableJson) === '') {
            return [
                'status' => 'error',
                'message' => $project === null
                    ? "Project {$projectId} not found."
                    : 'ChIP-seq data could not be serialized.',
            ];
        }

        return [
            'status' => 'success',
            'project_id' => $projectId,
            'project_name' => trim((string) $project->name),
            'data_type' => 'table',
            'display_type' => 'table',
            'table_json' => $tableJson,
            'title' => 'ChIP-seq QC',
            'summary' => 'ChIP-seq QC and peak metrics for project '.$project->name.'.',
        ];
    }

    /** @return array<string, mixed> */
    protected function cancerTypeChIPseq(string $cancerTypeId): array
    {
        if (!$this->isAvailableCancerType($cancerTypeId)) {
            return [
                'status' => 'error',
                'message' => "Cancer type {$cancerTypeId} is not an exact Cancer Type value available from getCancerTypes.",
            ];
        }

        $table = $this->chipseqTable($cancerTypeId);
        if (!isset($table['cols'], $table['data']) || !is_array($table['cols']) || !is_array($table['data'])) {
            return [
                'status' => 'error',
                'message' => 'Cancer-type ChIP-seq data could not be serialized as a table.',
            ];
        }

        $columns = array_map(static fn ($column): string =>
            trim((string) (is_array($column) ? ($column['title'] ?? '') : $column)),
            $table['cols']
        );
        $rows = array_values(array_map(fn ($row): array =>
            array_map(fn ($cell): string => $this->cleanCell($cell), (array) $row),
            $table['data']
        ));
        $rowCount = count($rows);

        return [
            'status' => 'success',
            'cancer_type_id' => $cancerTypeId,
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => $rowCount,
            'count_unit' => 'chipseq_sample_rows',
            'data_type' => 'table',
            'display_type' => 'table',
            'table_json' => (string) json_encode([
                'cols' => $table['cols'],
                'data' => $table['data'],
            ], JSON_UNESCAPED_SLASHES),
            'title' => "ChIP-seq QC for {$cancerTypeId}",
            'summary' => "{$rowCount} ChIP-seq sample row(s) found for {$cancerTypeId}.",
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

    /** @return array<string, mixed> */
    protected function chipseqTable(string $cancerTypeId): array
    {
        $result = app(CancerTypeController::class)->getChIPseq($cancerTypeId, 'json', 'Y');
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return (array) $result->getData(true);
        }
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            $result = $result->getContent();
        }
        if (is_string($result)) {
            $decoded = json_decode($result, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($result) ? $result : [];
    }

    protected function cleanCell($value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5));
    }

    private function normalizeToJson($result): string
    {
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return (string) json_encode($result->getData(true), JSON_UNESCAPED_SLASHES);
        }
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return (string) $result->getContent();
        }

        return is_string($result) ? $result : (string) json_encode($result, JSON_UNESCAPED_SLASHES);
    }

    public function schema($schema = null): array
    {
        return [
            'type' => 'object',
            'properties' => $this->cohortProperties(),
            'required' => ['cohort_type', 'cohort_id'],
            'additionalProperties' => false,
        ];
    }
}
