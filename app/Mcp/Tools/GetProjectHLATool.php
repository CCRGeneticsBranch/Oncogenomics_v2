<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetProjectHLATool extends LegacySchemaTool
{
    protected bool $supportsCompactOutput = true;

    protected string $name = 'get_project_hla';

    protected string $description = <<<'MARKDOWN'
        Return project HLA typing and alleles.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
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

            $tableJson = $this->normalizeToJson(
                app(ProjectController::class)->getProjectHLA($projectId, 'json')
            );

            if (!is_string($tableJson) || trim($tableJson) === '') {
                return $this->resultResponse($request, [
                    'status' => 'error',
                    'message' => 'HLA data could not be serialized.',
                    'action' => $this->name,
                ]);
            }

            return $this->resultResponse($request, [
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => $project->name,
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => $tableJson,
                'title' => 'HLA Typing',
                'summary' => 'HLA typing results for the current project.',
            ]);
        } catch (\Throwable $e) {
            return $this->resultResponse($request, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'action' => $this->name,
            ]);
        }
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
            ],
            'required' => ['project_id'],
            'additionalProperties' => false,
        ];
    }
}
