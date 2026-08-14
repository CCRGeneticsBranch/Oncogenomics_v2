<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class BaseProjectTableTool extends Tool
{
    protected string $controllerMethod;

    protected string $tableTitle;

    protected string $tableSummary;

    protected string $serializationError;

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
                    'message' => 'Project '.$projectId.' not found',
                    'action' => $this->name,
                ]);
            }

            $controller = app(ProjectController::class);
            $tableJson = $this->normalizeToJson(
                $controller->{$this->controllerMethod}($projectId)
            );

            if (trim($tableJson) === '') {
                return Response::structured([
                    'status' => 'error',
                    'message' => $this->serializationError,
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
                'title' => $this->tableTitle,
                'summary' => $this->tableSummary,
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
                    'description' => 'Current project ID supplied by the chatbot project context.',
                ],
            ],
            'required' => ['project_id'],
            'additionalProperties' => false,
        ];
    }

    private function normalizeToJson($result): string
    {
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return (string) json_encode($result->getData(true), JSON_UNESCAPED_SLASHES);
        }

        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return (string) $result->getContent();
        }

        return is_string($result)
            ? $result
            : (string) json_encode($result, JSON_UNESCAPED_SLASHES);
    }
}
