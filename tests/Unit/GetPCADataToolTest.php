<?php

namespace Tests\Unit;

use App\Mcp\Tools\GetPCADataTool;
use Laravel\Mcp\Request;
use Tests\TestCase;

class GetPCADataToolTest extends TestCase
{
    public function test_it_returns_normalized_pca_data(): void
    {
        $tool = new class extends GetPCADataTool
        {
            protected function accessibleProject(int $projectId)
            {
                return $projectId === 24421 ? (object) ['name' => 'Landscape'] : null;
            }

            protected function pcaData(int $projectId, string $valueType, string $genomeVersion): array
            {
                return [
                    'status' => 'ok',
                    'data' => [
                        'S2' => ['2.5', '-1.5', '0.5'],
                        'S1' => ['1.25', '3.5', '-2'],
                    ],
                    'variance_prop' => [40.2, 18.3, 9.1],
                    'pca_variance' => ['4.2', '2.1', '1.0'],
                    'pca_loading' => ['p' => ['PC1' => [['GENE1'], [0.8]]], 'n' => []],
                    'samples' => ['S1', 'S2'],
                    'patients' => ['S1' => 'P1', 'S2' => 'P2'],
                    'sample_meta' => ['attr_list' => ['Diagnosis'], 'data' => []],
                ];
            }
        };

        $content = $tool->handle(new Request(['project_id' => 24421]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame('getPCAData', $content['action']);
        $this->assertSame('S1', $content['coordinates'][0]['entity_id']);
        $this->assertSame(1.25, $content['coordinates'][0]['pc1']);
        $this->assertSame(2, $content['coordinate_count']);
        $this->assertSame(['PC1' => 40.2, 'PC2' => 18.3, 'PC3' => 9.1], $content['explained_variance_percent']);
        $this->assertSame([4.2, 2.1, 1.0], $content['component_variances']);
        $this->assertSame('all', $content['value_type']);
        $this->assertSame('hg19', $content['genome_version']);

        $table = json_decode($content['table_json'], true);
        $this->assertSame(['S1', 1.25, 3.5, -2], $table['data'][0]);
    }

    public function test_it_reports_when_pca_files_are_unavailable(): void
    {
        $tool = new class extends GetPCADataTool
        {
            protected function accessibleProject(int $projectId)
            {
                return (object) ['name' => 'No PCA Project'];
            }

            protected function pcaData(int $projectId, string $valueType, string $genomeVersion): array
            {
                return ['status' => 'no data'];
            }
        };

        $content = $tool->handle(new Request([
            'project_id' => 12,
            'value_type' => 'zscore',
            'genome_version' => 'hg38',
        ]))->getStructuredContent();

        $this->assertSame('no_data', $content['status']);
        $this->assertStringContainsString('No PCA data is available', $content['message']);
    }

    public function test_it_rejects_an_inaccessible_project(): void
    {
        $tool = new class extends GetPCADataTool
        {
            protected function accessibleProject(int $projectId)
            {
                return null;
            }
        };

        $content = $tool->handle(new Request(['project_id' => 999]))->getStructuredContent();

        $this->assertSame('error', $content['status']);
        $this->assertStringContainsString('not accessible', $content['message']);
    }

    public function test_schema_requires_only_project_id(): void
    {
        $tool = new class extends GetPCADataTool
        {
            protected function accessibleProject(int $projectId)
            {
                return null;
            }
        };

        $schema = $tool->schema();

        $this->assertSame(['project_id'], $schema['required']);
        $this->assertSame(['all', 'zscore', null], $schema['properties']['value_type']['enum']);
        $this->assertFalse($schema['additionalProperties']);
    }
}
