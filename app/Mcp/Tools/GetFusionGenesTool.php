<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class GetFusionGenesTool extends LegacySchemaTool
{
    protected bool $supportsCompactOutput = true;

    protected string $name = 'get_fusion_genes';

    protected string $description = <<<'MARKDOWN'
        Return project fusion calls for filtering, counts, joins, or combined alteration questions. left_gene alone matches either partner; with right_gene matches the directed 5′–3′ pair. Pass caller="Arriba" (or the requested caller) separately from filter_column; diagnosis filters disease. Join on patient_id, case_id, sample_id. Use fusion_by_gene only for the viewer.
    MARKDOWN;

    /**
     * Columns that may be used to filter the fusion result set.
     *
     * @var array<int, string>
     */
    private const FILTER_COLUMNS = ['type', 'var_level', 'left_region', 'right_region'];

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'left_gene' => 'required|string|max:100',
            'right_gene' => 'nullable|string|max:100',
            'diagnosis' => 'nullable|string|max:200',
            'caller' => 'nullable|string|max:100',
            'filter_column' => 'nullable|string|in:'.implode(',', self::FILTER_COLUMNS),
            'filter_value' => 'nullable|string|max:100',
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $project = Project::getProject($projectId);

            if ($project === null) {
                return $this->resultResponse($request, [
                    'status' => 'error',
                    'message' => "Project {$projectId} not found",
                    'action' => $this->name,
                ]);
            }

            $leftGene = $this->cleanGene($validated['left_gene'] ?? null);
            $rightGene = $this->cleanGene($validated['right_gene'] ?? null);

            if ($leftGene === null) {
                return $this->resultResponse($request, [
                    'status' => 'error',
                    'message' => 'A valid gene symbol is required for left_gene.',
                    'action' => $this->name,
                ]);
            }

            $filterColumn = $validated['filter_column'] ?? null;
            $filterValue = $this->cleanFilterValue($validated['filter_value'] ?? null);
            $caller = $this->cleanFilterValue($validated['caller'] ?? null);

            if ($filterColumn !== null && $filterValue === null) {
                $filterColumn = null;
            }
            if ($filterColumn === null) {
                $filterValue = null;
            }

            $tableJson = $this->normalizeToJson(
                app(ProjectController::class)->getFusionGenes(
                    $projectId,
                    $leftGene,
                    $rightGene,
                    $filterColumn,
                    $filterValue,
                    $caller
                )
            );

            if (!is_string($tableJson) || trim($tableJson) === '') {
                return $this->resultResponse($request, [
                    'status' => 'error',
                    'message' => 'Fusion gene data could not be serialized.',
                    'action' => $this->name,
                ]);
            }

            $decoded = json_decode($tableJson, true);
            $diagnosis = $this->cleanDiagnosis($validated['diagnosis'] ?? null);
            if (is_array($decoded)) {
                $decoded = $this->filterTableByDiagnosis($decoded, $diagnosis);
                $tableJson = (string) json_encode($decoded, JSON_UNESCAPED_SLASHES);
            }
            $rowCount = is_array($decoded) && isset($decoded['data']) ? count((array) $decoded['data']) : 0;
            $pair = $rightGene === null ? $leftGene : "{$leftGene}-{$rightGene}";
            $scope = $diagnosis === null ? 'this project' : "{$diagnosis} in this project";
            $callerScope = $caller === null ? '' : " called by {$caller}";

            return $this->resultResponse($request, [
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => $project->name,
                'left_gene' => $leftGene,
                'right_gene' => $rightGene,
                'diagnosis' => $diagnosis,
                'caller' => $caller,
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => $tableJson,
                'title' => 'Fusion Genes',
                'summary' => $rowCount === 0
                    ? "No fusion gene call{$callerScope} found for {$pair} in {$scope}."
                    : "{$rowCount} fusion gene call(s){$callerScope} found for {$pair} in {$scope}.",
            ]);
        } catch (\Throwable $e) {
            return $this->resultResponse($request, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'action' => $this->name,
            ]);
        }
    }

    private function cleanGene(?string $gene): ?string
    {
        $gene = trim((string) $gene);
        if ($gene === '' || strtolower($gene) === 'null') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9._\-]+$/', $gene) ? strtoupper($gene) : null;
    }

    private function cleanFilterValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9 ._\-]+$/', $value) ? $value : null;
    }

    private function cleanDiagnosis(?string $diagnosis): ?string
    {
        $diagnosis = trim((string) $diagnosis);
        if ($diagnosis === '' || strtolower($diagnosis) === 'null' || strtolower($diagnosis) === 'all') {
            return null;
        }

        return $diagnosis;
    }

    /**
     * Keep only the rows whose diagnosis column matches the requested diagnosis.
     *
     * @param  array<string, mixed>  $table
     * @return array<string, mixed>
     */
    private function filterTableByDiagnosis(array $table, ?string $diagnosis): array
    {
        if ($diagnosis === null || !isset($table['cols']) || !isset($table['data'])) {
            return $table;
        }

        $index = null;
        foreach ((array) $table['cols'] as $position => $col) {
            $title = is_array($col) ? (string) ($col['title'] ?? '') : (string) $col;
            if (strcasecmp(trim($title), 'diagnosis') === 0) {
                $index = $position;
                break;
            }
        }
        if ($index === null) {
            return $table;
        }

        $needle = mb_strtolower($diagnosis);
        $rows = [];
        foreach ((array) $table['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cell = mb_strtolower(trim(strip_tags((string) ($row[$index] ?? ''))));
            if ($cell === '') {
                continue;
            }
            // Accept either direction so "osteosarcoma" still matches "Osteosarcoma, high grade".
            if (mb_strpos($cell, $needle) !== false || (mb_strlen($cell) >= 4 && mb_strpos($needle, $cell) !== false)) {
                $rows[] = $row;
            }
        }
        $table['data'] = array_values($rows);

        return $table;
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

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Current project ID supplied by the chatbot context.',
                ],
                'left_gene' => [
                    'type' => 'string',
                    'description' => 'Gene symbol to search for. When right_gene is omitted the gene is matched on either side of the fusion.',
                ],
                'right_gene' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional partner gene symbol. When provided, only fusions with left_gene as the 5\' partner and right_gene as the 3\' partner are returned.',
                ],
                'diagnosis' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional cancer type or disease name used to filter the rows, e.g. "Osteosarcoma".',
                ],
                'caller' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional fusion caller name, for example "Arriba". It is matched case-insensitively against caller keys in the JSON Tool column. Do not pass caller names through filter_column.',
                ],
                'filter_column' => [
                    'type' => ['string', 'null'],
                    'enum' => ['type', 'var_level', 'left_region', 'right_region', null],
                    'description' => 'Optional non-caller column used to filter fusion calls. Allowed values are type, var_level, left_region, and right_region.',
                ],
                'filter_value' => [
                    'type' => ['string', 'null'],
                    'description' => 'Value the filter_column must match, e.g. "in-frame" for type or "exonic" for left_region.',
                ],
            ],
            'required' => ['project_id', 'left_gene'],
            'additionalProperties' => false,
        ];
    }
}
