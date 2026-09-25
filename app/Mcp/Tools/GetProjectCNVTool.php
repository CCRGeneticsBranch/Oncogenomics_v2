<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\VarController;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetProjectCNVTool extends LegacySchemaTool
{
    protected string $name = 'get_project_cnv';

    protected string $description = <<<'MARKDOWN'
        Get the copy number variation (CNV) segments of a gene across all
        samples of the current project. Use this tool for requests such as
        "CNV of TP53", "copy number for MYCN", or "is ALK amplified in this
        project". Pass diagnosis when the query names a cancer type or disease,
        for example "CNV of FOXO1 in Osteosarcoma". Returns a generic table
        payload (columns and rows) that the chatbot renders with jQuery
        DataTables. project_id is supplied by the chatbot project context.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'gene' => 'required|string|max:100',
            'diagnosis' => 'nullable|string|max:200',
            'source' => 'nullable|string|in:sequenza,cnvkit,conserting,freec',
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $project = Project::getProject($projectId);

            if ($project === null) {
                return Response::structured([
                    'status' => 'error',
                    'message' => "Project {$projectId} not found",
                    'action' => $this->name,
                ]);
            }

            $gene = $this->cleanGene($validated['gene'] ?? null);

            if ($gene === null) {
                return Response::structured([
                    'status' => 'error',
                    'message' => 'A valid gene symbol is required.',
                    'action' => $this->name,
                ]);
            }

            $source = $validated['source'] ?? 'sequenza';

            $tableJson = $this->normalizeToJson(
                app(VarController::class)->getCNVByGene($projectId, $gene, $source, 'json', 'Project', 'N')
            );

            if (!is_string($tableJson) || trim($tableJson) === '') {
                return Response::structured([
                    'status' => 'error',
                    'message' => 'CNV data could not be serialized.',
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
            $scope = $diagnosis === null ? 'this project' : "{$diagnosis} in this project";

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => $project->name,
                'gene' => $gene,
                'diagnosis' => $diagnosis,
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => $tableJson,
                'title' => 'Copy Number Variation',
                'summary' => $rowCount === 0
                    ? "No CNV segment found for {$gene} in {$scope}."
                    : "{$rowCount} CNV segment(s) found for {$gene} in {$scope}.",
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
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
                    'description' => 'Current project ID supplied by the chatbot context. Used as the cohort ID.',
                ],
                'gene' => [
                    'type' => 'string',
                    'description' => 'Gene symbol to report copy number for, e.g. "TP53".',
                ],
                'diagnosis' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional cancer type or disease name used to filter the rows, e.g. "Osteosarcoma".',
                ],
                'source' => [
                    'type' => ['string', 'null'],
                    'enum' => ['sequenza', 'cnvkit', 'conserting', 'freec', null],
                    'description' => 'Optional CNV caller. Defaults to sequenza.',
                ],
            ],
            'required' => ['project_id', 'gene'],
            'additionalProperties' => false,
        ];
    }
}
