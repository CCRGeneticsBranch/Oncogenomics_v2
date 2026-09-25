<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetProjectSTRTool extends LegacySchemaTool
{
    protected string $name = 'get_project_str';

    protected string $description = <<<'MARKDOWN'
        Get STR (short tandem repeat) profiling results for the current project.
        Use this tool for requests asking about STR, STR profiles, or sample
        identity/fingerprinting. Returns a generic table payload (columns and
        rows) that the chatbot renders with jQuery DataTables. project_id is
        supplied by the chatbot project context.
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
                return Response::structured([
                    'status' => 'error',
                    'message' => "Project {$projectId} not found",
                    'action' => $this->name,
                ]);
            }

            $tableJson = $this->normalizeToJson(
                app(ProjectController::class)->getProjectSTR($projectId, 'json')
            );

            if (!is_string($tableJson) || trim($tableJson) === '') {
                return Response::structured([
                    'status' => 'error',
                    'message' => 'STR data could not be serialized.',
                    'action' => $this->name,
                ]);
            }

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => $project->name,
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => $tableJson,
                'title' => 'STR Profile',
                'summary' => 'STR profiling results for the current project.',
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
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
