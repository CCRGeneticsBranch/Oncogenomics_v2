<?php

namespace App\Mcp\Tools;

use App\Services\CohortAnalysisService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetCohortSchemaTool extends Tool
{
    protected string $name = 'getCohortSchema';

    protected string $description = <<<'MARKDOWN'
        Describe the cohort filters and project-level expression matrices that
        are available for downstream analyses. Call this before constructing a
        comparison cohort. The initial implementation supports diagnosis plus
        an exact fusion pair and optional fusion caller. Cancer-type scope is
        intentionally not supported because cancer types are user-specific and
        do not own a single project expression matrix.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate(['project_id' => 'required|integer']);
        try {
            $projectId = (int) $validated['project_id'];
            $service = $this->service();
            $project = $service->accessibleProject($projectId);
            if ($project === null) {
                return Response::structured([
                    'status' => 'error', 'action' => $this->name,
                    'message' => "Project {$projectId} was not found or is not accessible to the authorized user.",
                ]);
            }

            $diagnoses = $service->diagnoses($projectId);
            $callers = $service->fusionCallers($projectId);
            $matrices = $service->expressionMatrices($projectId);

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => trim((string) $project->name),
                'scope' => 'project',
                'cohort_definition' => [
                    'source' => 'fusion',
                    'required_fields' => ['left_gene', 'right_gene'],
                    'optional_fields' => ['diagnosis', 'caller', 'fusion_status'],
                    'matching' => [
                        'fusion_pair' => 'exact, case-insensitive, either orientation',
                        'diagnosis' => 'exact',
                        'caller' => 'exact, case-insensitive',
                        'fusion_status' => 'positive includes samples with the exact fusion; negative includes samples without that exact fusion. Defaults to positive.',
                    ],
                ],
                'diagnoses' => $diagnoses,
                'fusion_callers' => $callers,
                'expression_matrices' => $matrices,
                'supported_analyses' => [[
                    'name' => 'runDifferentialExpression',
                    'method' => 'DESeq2',
                    'required_matrix_data_type' => 'count',
                    'effect_direction' => 'positive log2FoldChange means higher in group_a than group_b',
                ]],
                'unsupported_scopes' => [[
                    'scope' => 'cancer_type',
                    'reason' => 'A user-specific cancer type may span projects and does not have one authoritative project-level count matrix.',
                ]],
                'summary' => count($diagnoses).' diagnosis value(s), '.count($callers).' fusion caller(s), and '.count($matrices).' count matrix file(s) are available.',
            ]);
        } catch (\Throwable $e) {
            return Response::structured(['status' => 'error', 'action' => $this->name, 'message' => $e->getMessage()]);
        }
    }

    protected function service(): CohortAnalysisService
    {
        return app(CohortAnalysisService::class);
    }

    public function schema($schema = null): array
    {
        return [
            'type' => 'object',
            'properties' => ['project_id' => ['type' => 'integer', 'description' => 'Authorized numeric project ID. Use getProjects first when only a project name is known.']],
            'required' => ['project_id'],
            'additionalProperties' => false,
        ];
    }
}
