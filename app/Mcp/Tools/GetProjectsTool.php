<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetProjectsTool extends LegacySchemaTool
{
    protected string $name = 'getProjects';

    protected string $description = <<<'MARKDOWN'
        List every project available to the current MCP context with its numeric
        project ID and name. Use this tool before calling a project-scoped tool
        whenever the user identifies a project by name instead of project_id.
        Prefer a case-insensitive exact name match. If no unique match exists,
        present the matching project choices and ask the user to clarify; never
        invent or guess a project ID.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        try {
            $projects = array_map(function ($project): array {
                return [
                    'project_id' => (int) $project->id,
                    'project_name' => trim((string) $project->name),
                    'description' => trim((string) ($project->description ?? '')),
                ];
            }, $this->availableProjects());

            usort($projects, static function (array $left, array $right): int {
                $nameComparison = strcasecmp($left['project_name'], $right['project_name']);

                return $nameComparison !== 0
                    ? $nameComparison
                    : $left['project_id'] <=> $right['project_id'];
            });

            $table = [
                'cols' => [
                    ['title' => 'Project ID'],
                    ['title' => 'Project Name'],
                    ['title' => 'Description'],
                ],
                'data' => array_map(static function (array $project): array {
                    return [
                        $project['project_id'],
                        $project['project_name'],
                        $project['description'],
                    ];
                }, $projects),
            ];

            $count = count($projects);

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'projects' => $projects,
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => (string) json_encode($table, JSON_UNESCAPED_SLASHES),
                'title' => 'Available Projects',
                'summary' => $count === 1
                    ? '1 project is available.'
                    : "{$count} projects are available.",
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Use the model's existing visibility rules for the current request.
     *
     * @return array<int, object>
     */
    protected function availableProjects(): array
    {
        return Project::getAll(false);
    }

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];
    }
}
