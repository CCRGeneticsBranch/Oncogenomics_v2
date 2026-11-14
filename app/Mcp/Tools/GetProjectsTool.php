<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetProjectsTool extends LegacySchemaTool
{
    protected bool $supportsCompactOutput = true;

    protected string $name = 'getProjects';

    protected string $description = <<<'MARKDOWN'
        Resolve authorized project names to numeric IDs. Use query with match=exact first; if absent, try contains. Preserve ambiguous matches and ask the user to choose. Never guess IDs.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $request->validate(['result_role'=>'sometimes|string|in:discovery,answer']);
        try {
            $projects = array_map(function ($project): array {
                return [
                    'project_id' => (int) $project->id,
                    'project_name' => trim((string) $project->name),
                    'description' => trim((string) ($project->description ?? '')),
                ];
            }, $this->availableProjects());

            $query = $request->get('query');
            $match = $request->get('match', 'contains');
            if (($query !== null && (!is_string($query) || strlen($query) > 200))
                || !in_array($match, ['exact', 'contains'], true)) {
                throw new \InvalidArgumentException('query must be a string up to 200 characters; match must be exact or contains.');
            }
            if ($query !== null && trim($query) !== '') {
                $query = trim($query);
                $projects = array_values(array_filter($projects, static fn (array $project): bool =>
                    $match === 'exact' ? strcasecmp($project['project_name'], $query) === 0
                        : stripos($project['project_name'], $query) !== false
                ));
            }

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

            return $this->resultResponse($request, [
                'status' => 'success',
                'result_role' => $request->get('result_role', 'discovery'),
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
            return $this->resultResponse($request, [
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
            'properties' => [
                'result_role' => ['type' => 'string', 'enum' => ['discovery', 'answer'], 'description' => 'Default discovery: resolving a cohort for another task, shown only in Technical details. Use answer only when the user explicitly requests a project or cancer-type listing.'],
                'query' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Project name filter, case-insensitive; omit to list all authorized projects.'],
                'match' => ['type' => 'string', 'enum' => ['exact', 'contains'], 'description' => 'Defaults to contains. Use exact to resolve a full project name.'],
            ],
            'additionalProperties' => false,
        ];
    }
}
