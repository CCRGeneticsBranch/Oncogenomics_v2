<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetPCADataTool extends LegacySchemaTool
{
    protected string $name = 'getPCAData';

    protected string $description = <<<'MARKDOWN'
        Return expression PCA data for an authorized project, including PC1-3
        coordinates, explained variance, component variances, positive and
        negative gene loadings, sample metadata, and patient mappings. Use
        getProjects first when only a project name is known.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'value_type' => 'nullable|string|in:all,zscore',
            'genome_version' => 'nullable|string|in:hg19,hg38',
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

            $valueType = (string) ($validated['value_type'] ?? 'all');
            $genomeVersion = (string) ($validated['genome_version'] ?? 'hg19');
            $pca = $this->pcaData($projectId, $valueType, $genomeVersion);

            if (($pca['status'] ?? null) === 'no data') {
                return Response::structured([
                    'status' => 'no_data',
                    'action' => $this->name,
                    'project_id' => $projectId,
                    'project_name' => trim((string) ($project->name ?? '')),
                    'value_type' => $valueType,
                    'genome_version' => $genomeVersion,
                    'message' => "No PCA data is available for project {$project->name}, {$genomeVersion}, value type {$valueType}.",
                ]);
            }

            if (($pca['status'] ?? null) !== 'ok' || !isset($pca['data']) || !is_array($pca['data'])) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => 'PCA data could not be serialized.',
                ]);
            }

            $coordinates = [];
            foreach ($pca['data'] as $entityId => $values) {
                $values = array_values((array) $values);
                if (count($values) < 3 || !is_numeric($values[0]) || !is_numeric($values[1]) || !is_numeric($values[2])) {
                    continue;
                }
                $coordinates[] = [
                    'entity_id' => (string) $entityId,
                    'pc1' => (float) $values[0],
                    'pc2' => (float) $values[1],
                    'pc3' => (float) $values[2],
                ];
            }

            usort($coordinates, static fn (array $left, array $right): int =>
                strcasecmp($left['entity_id'], $right['entity_id'])
            );

            $varianceValues = array_values((array) ($pca['variance_prop'] ?? []));
            $explainedVariance = [];
            foreach ($varianceValues as $index => $variance) {
                if (is_numeric($variance)) {
                    $explainedVariance['PC'.($index + 1)] = (float) $variance;
                }
            }

            $componentVariances = array_values(array_map(
                static fn ($value) => is_numeric($value) ? (float) $value : null,
                (array) ($pca['pca_variance'] ?? [])
            ));

            $table = [
                'cols' => [
                    ['title' => 'Entity ID'],
                    ['title' => 'PC1'],
                    ['title' => 'PC2'],
                    ['title' => 'PC3'],
                ],
                'data' => array_map(static fn (array $coordinate): array => [
                    $coordinate['entity_id'],
                    $coordinate['pc1'],
                    $coordinate['pc2'],
                    $coordinate['pc3'],
                ], $coordinates),
            ];

            $coordinateCount = count($coordinates);

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'project_id' => $projectId,
                'project_name' => trim((string) ($project->name ?? '')),
                'value_type' => $valueType,
                'genome_version' => $genomeVersion,
                'coordinates' => $coordinates,
                'coordinate_count' => $coordinateCount,
                'explained_variance_percent' => $explainedVariance,
                'component_variances' => $componentVariances,
                'loadings' => (array) ($pca['pca_loading'] ?? []),
                'samples' => array_values((array) ($pca['samples'] ?? [])),
                'patients' => (array) ($pca['patients'] ?? []),
                'sample_metadata' => (array) ($pca['sample_meta'] ?? []),
                'data_type' => 'pca',
                'display_type' => 'pca_data',
                'table_json' => (string) json_encode($table, JSON_UNESCAPED_SLASHES),
                'title' => "Expression PCA for {$project->name}",
                'summary' => "{$coordinateCount} PCA coordinate row(s) found for project {$project->name}.",
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

    /** @return array<string, mixed> */
    protected function pcaData(int $projectId, string $valueType, string $genomeVersion): array
    {
        $result = app(ProjectController::class)->getPCAData($projectId, $valueType, $genomeVersion);

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

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Numeric project ID. Use getProjects first when only a project name is known.',
                ],
                'value_type' => [
                    'type' => ['string', 'null'],
                    'enum' => ['all', 'zscore', null],
                    'description' => 'Use all/default expression values or z-score PCA files. Defaults to all.',
                ],
                'genome_version' => [
                    'type' => ['string', 'null'],
                    'enum' => ['hg19', 'hg38', null],
                    'description' => 'Genome version. Defaults to hg19.',
                ],
            ],
            'required' => ['project_id'],
            'additionalProperties' => false,
        ];
    }
}
