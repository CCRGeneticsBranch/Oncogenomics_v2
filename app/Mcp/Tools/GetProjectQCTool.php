<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetProjectQCTool extends Tool
{
    protected string $name = 'get_project_qc';

    protected string $description = <<<'MARKDOWN'
        Get DNA or RNA quality control (QC) metrics for the current project.
        Use this tool for requests asking about QC, quality control, or sample
        quality metrics. The `type` argument selects the assay and must be
        either "dna" or "rna" (case-insensitive). Returns a generic table
        payload that the chatbot renders with jQuery DataTables; the table data
        lives in the `qc_data` element of the returned JSON. project_id is
        supplied by the chatbot project context.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'type' => 'required|string',
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $type = strtolower(trim((string) $validated['type']));

            if (!in_array($type, ['dna', 'rna'], true)) {
                return Response::structured([
                    'status' => 'error',
                    'message' => "Invalid QC type '{$type}'. Expected 'dna' or 'rna'.",
                    'action' => $this->name,
                ]);
            }

            $project = Project::getProject($projectId);

            if ($project === null) {
                return Response::structured([
                    'status' => 'error',
                    'message' => "Project {$projectId} not found",
                    'action' => $this->name,
                ]);
            }

            $qcResult = app(ProjectController::class)->getQC($projectId, $type, 'json');

            if ($qcResult instanceof \Illuminate\Http\JsonResponse) {
                $qcResult = json_encode($qcResult->getData(true), JSON_UNESCAPED_SLASHES);
            } elseif ($qcResult instanceof \Symfony\Component\HttpFoundation\Response) {
                $qcResult = (string) $qcResult->getContent();
            } elseif (!is_string($qcResult)) {
                $qcResult = json_encode($qcResult, JSON_UNESCAPED_SLASHES);
            }

            if (!is_string($qcResult) || trim($qcResult) === '') {
                return Response::structured([
                    'status' => 'error',
                    'message' => 'QC data could not be serialized.',
                    'action' => $this->name,
                ]);
            }

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => $project->name,
                'type' => $type,
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => $qcResult,
                'title' => strtoupper($type) . ' QC Metrics',
                'summary' => 'Quality control metrics for the requested ' . strtoupper($type) . ' assay.',
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'message' => $e->getMessage(),
                'action' => $this->name,
            ]);
        }
    }

    public function schema($schema = null): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Current project ID supplied by the chatbot context.',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['dna', 'rna'],
                    'description' => 'QC assay type: "dna" or "rna" (case-insensitive).',
                ],
            ],
            'required' => ['project_id', 'type'],
            'additionalProperties' => false,
        ];
    }
}
