<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Models\Gene;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetCorrelationDataTool extends Tool
{
    protected string $name = 'getCorrelationData';

    protected string $description = <<<'MARKDOWN'
        Return gene-expression correlations for a gene in an authorized
        project. The first required parameter is project_id and the second is
        gene_symbol. Use getProjects first when only a project name is known.
        Results include the correlated gene symbol, gene identifier,
        coefficient, and positive/negative direction.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'gene_symbol' => 'required|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'cutoff' => 'nullable|numeric|min:0|max:1',
            'genome_version' => 'nullable|string|in:hg19,hg38',
            'method' => 'nullable|string|in:pearson,spearman',
            'value_type' => 'nullable|string|in:tpm,tmm-rpkm',
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $project = $this->accessibleProject($projectId);
            if ($project === null) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Project {$projectId} was not found or is not accessible to the authorized user.",
                ]);
            }

            $genomeVersion = (string) ($validated['genome_version'] ?? 'hg19');
            $requestedGene = strtoupper(trim((string) $validated['gene_symbol']));
            $geneSymbol = $this->resolveGeneSymbol($requestedGene, $genomeVersion);
            if ($geneSymbol === null) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Gene {$requestedGene} was not found for {$genomeVersion}.",
                ]);
            }

            $cutoff = (float) ($validated['cutoff'] ?? 0.2);
            $method = (string) ($validated['method'] ?? 'pearson');
            $valueType = (string) ($validated['value_type'] ?? 'tpm');
            $table = $this->correlationTable(
                $projectId,
                $geneSymbol,
                $cutoff,
                $genomeVersion,
                $method,
                $valueType
            );

            if (!isset($table['cols'], $table['data']) || !is_array($table['cols']) || !is_array($table['data'])) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => 'Correlation data could not be serialized as a table.',
                ]);
            }

            $correlations = [];
            foreach ($table['data'] as $row) {
                $row = (array) $row;
                $coefficient = is_numeric($row[2] ?? null) ? (float) $row[2] : null;
                if ($coefficient === null || abs($coefficient) < $cutoff) {
                    continue;
                }
                $correlations[] = [
                    'gene_symbol' => $this->cleanCell($row[0] ?? ''),
                    'gene_id' => $this->cleanCell($row[1] ?? ''),
                    'coefficient' => $coefficient,
                    'direction' => $this->cleanCell($row[3] ?? ''),
                ];
            }

            usort($correlations, static function (array $left, array $right): int {
                return abs((float) $right['coefficient']) <=> abs((float) $left['coefficient'])
                    ?: strcasecmp($left['gene_symbol'], $right['gene_symbol']);
            });

            $rowCount = count($correlations);
            $filteredTable = [
                'cols' => [
                    ['title' => 'Symbol'],
                    ['title' => 'Gene'],
                    ['title' => 'Coefficient'],
                    ['title' => 'Positive/negative'],
                ],
                'data' => array_map(static fn (array $correlation): array => [
                    $correlation['gene_symbol'],
                    $correlation['gene_id'],
                    $correlation['coefficient'],
                    $correlation['direction'],
                ], $correlations),
            ];

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => trim((string) ($project->name ?? '')),
                'gene_symbol' => $geneSymbol,
                'cutoff' => $cutoff,
                'genome_version' => $genomeVersion,
                'method' => $method,
                'value_type' => $valueType,
                'correlations' => $correlations,
                'correlation_count' => $rowCount,
                'data_type' => 'table',
                'display_type' => 'correlation_table',
                'table_json' => (string) json_encode($filteredTable, JSON_UNESCAPED_SLASHES),
                'title' => "{$geneSymbol} expression correlations",
                'summary' => "{$rowCount} gene(s) correlated with {$geneSymbol} in project {$project->name} using {$method} correlation.",
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function accessibleProject(int $projectId)
    {
        foreach (Project::getAll(false) as $availableProject) {
            if ((int) $availableProject->id === $projectId) {
                return Project::getProject($projectId);
            }
        }

        return null;
    }

    protected function resolveGeneSymbol(string $geneSymbol, string $genomeVersion): ?string
    {
        $gene = Gene::getGene($geneSymbol, $genomeVersion);

        return $gene === null ? null : (string) $gene->getSymbol();
    }

    /** @return array<string, mixed> */
    protected function correlationTable(
        int $projectId,
        string $geneSymbol,
        float $cutoff,
        string $genomeVersion,
        string $method,
        string $valueType
    ): array {
        $result = app(ProjectController::class)->getCorrelationData(
            $projectId,
            $geneSymbol,
            $cutoff,
            $genomeVersion,
            $method,
            $valueType
        );

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return (array) $result->getData(true);
        }
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            $result = $result->getContent();
        }
        if (is_string($result)) {
            $decoded = json_decode($result, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($result) ? $result : [];
    }

    protected function cleanCell($value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5));
    }

    public function schema($schema = null): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Numeric project ID. Use getProjects first when only a project name is known.',
                ],
                'gene_symbol' => [
                    'type' => 'string',
                    'description' => 'Gene symbol whose expression correlations should be returned, for example FGFR4.',
                ],
                'cutoff' => [
                    'type' => ['number', 'null'],
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Optional absolute correlation cutoff. Defaults to 0.2.',
                ],
                'genome_version' => [
                    'type' => ['string', 'null'],
                    'enum' => ['hg19', 'hg38', null],
                    'description' => 'Optional genome version. Defaults to hg19.',
                ],
                'method' => [
                    'type' => ['string', 'null'],
                    'enum' => ['pearson', 'spearman', null],
                    'description' => 'Optional correlation method. Defaults to pearson.',
                ],
                'value_type' => [
                    'type' => ['string', 'null'],
                    'enum' => ['tpm', 'tmm-rpkm', null],
                    'description' => 'Optional expression value type. Defaults to tpm.',
                ],
            ],
            'required' => ['project_id', 'gene_symbol'],
            'additionalProperties' => false,
        ];
    }
}
