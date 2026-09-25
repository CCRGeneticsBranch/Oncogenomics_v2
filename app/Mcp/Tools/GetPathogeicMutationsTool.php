<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetPathogeicMutationsTool extends LegacySchemaTool
{
    protected string $name = 'get_pathogeic_mutations';

    protected string $description = <<<'MARKDOWN'
        Get pathogenic mutations for a diagnosis and gene ID in the current
        project. Use this tool for requests asking for pathogenic mutations,
        optionally filtered by diagnosis and gene ID. Returns a generic table
        payload that the chatbot renders with jQuery DataTables. Domain
        arguments are diagnosis and gene_id; project_id is supplied by the
        chatbot project context.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'diagnosis' => 'nullable|string|max:255',
            'gene_id' => 'nullable|string|max:100',
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $diagnosis = $this->normalizeFilter($validated['diagnosis'] ?? null);
            $geneId = $this->normalizeFilter($validated['gene_id'] ?? null);
            $project = Project::getProject($projectId);

            if ($project === null) {
                return Response::structured([
                    'status' => 'error',
                    'message' => "Project {$projectId} not found",
                    'action' => $this->name,
                ]);
            }

            $tableResult = app(ProjectController::class)->getPathogeicMutations(
                $projectId,
                $diagnosis,
                $geneId
            );

            if ($tableResult instanceof \Illuminate\Http\JsonResponse) {
                $tableResult = $tableResult->getData(true);
            } elseif ($tableResult instanceof \Symfony\Component\HttpFoundation\Response) {
                $tableResult = json_decode((string) $tableResult->getContent(), true);
            } elseif (is_string($tableResult)) {
                $decoded = json_decode($tableResult, true);
                $tableResult = is_array($decoded) ? $decoded : $tableResult;
            }

            $tableJson = is_string($tableResult)
                ? $tableResult
                : json_encode($tableResult, JSON_UNESCAPED_SLASHES);

            if (!is_string($tableJson) || trim($tableJson) === '') {
                return Response::structured([
                    'status' => 'error',
                    'message' => 'Pathogenic mutation data could not be serialized.',
                    'action' => $this->name,
                ]);
            }

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => $project->name,
                'diagnosis' => $diagnosis === 'null' ? null : $diagnosis,
                'gene_id' => $geneId === 'null' ? null : $geneId,
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => $tableJson,
                'title' => 'Pathogenic Mutations',
                'summary' => 'Pathogenic mutations matching the requested diagnosis and gene ID.',
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'message' => $e->getMessage(),
                'action' => $this->name,
            ]);
        }
    }

    private function normalizeFilter($value): string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' || strtolower($normalized) === 'null'
            ? 'null'
            : $normalized;
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
                'diagnosis' => [
                    'type' => ['string', 'null'],
                    'description' => 'Cancer type or diagnosis from the query, including acronyms such as NSCLC.',
                ],
                'gene_id' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional gene ID or gene symbol when the query names a specific gene.',
                ],
            ],
            'required' => ['project_id'],
            'additionalProperties' => false,
        ];
    }
}