<?php

namespace Tests\Unit;

use App\Mcp\Tools\ExpressionByGeneTool;
use App\Mcp\Tools\GetCohortChIPseqTool;
use App\Mcp\Tools\GetCohortExpressionTool;
use App\Mcp\Tools\GetCohortMutationGenesTool;
use App\Mcp\Tools\GetCohortSamplesTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Mockery;
use Tests\TestCase;

class GetCohortToolsTest extends TestCase
{
    public function test_samples_routes_a_project_id_to_the_project_implementation(): void
    {
        $tool = new class extends GetCohortSamplesTool
        {
            protected function isAccessibleProject(int $projectId): bool
            {
                return $projectId === 25062;
            }

            protected function projectSamples(int $projectId, string $expType): array
            {
                return ['status' => 'success', 'project_id' => $projectId, 'exp_type' => $expType];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'project',
            'cohort_id' => '25062',
            'exp_type' => 'RNAseq',
        ]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame('getCohortSamples', $content['action']);
        $this->assertSame('project', $content['cohort_type']);
        $this->assertSame(25062, $content['cohort_id']);
        $this->assertSame('RNAseq', $content['exp_type']);
    }

    public function test_samples_routes_an_exact_cancer_type_to_the_cancer_type_implementation(): void
    {
        $tool = new class extends GetCohortSamplesTool
        {
            protected function cancerTypeSamples(string $cancerTypeId): array
            {
                return ['status' => 'success', 'cancer_type_id' => $cancerTypeId];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'Neuroblastoma',
        ]))->getStructuredContent();

        $this->assertSame('getCohortSamples', $content['action']);
        $this->assertSame('cancer_type', $content['cohort_type']);
        $this->assertSame('Neuroblastoma', $content['cohort_id']);
    }

    public function test_cancer_type_samples_are_cleaned_counted_and_sorted(): void
    {
        $tool = new class extends GetCohortSamplesTool
        {
            protected function currentUserId(): ?int
            {
                return 3803;
            }

            protected function availableCancerTypes(int $userId): array
            {
                return ['ARMS'];
            }

            protected function samplesForCancerType(int $userId, string $cancerTypeId): array
            {
                return [
                    (object) [
                        'patient_id' => 'PAT2', 'sample_id' => 'S2',
                        'sample_name' => 'Sample 2', 'sample_alias' => 'Alias 2',
                        'tissue_cat' => 'tumor', 'tissue_type' => 'primary',
                        'exp_type' => 'RNAseq', 'library_type' => 'ribozero',
                        'material_type' => 'RNA', 'platform' => 'NovaSeq',
                    ],
                    (object) [
                        'patient_id' => 'PAT1', 'sample_id' => 'S1',
                        'sample_name' => 'Sample 1', 'sample_alias' => null,
                        'tissue_cat' => 'tumor', 'tissue_type' => 'metastatic',
                        'exp_type' => 'Exome', 'library_type' => 'capture',
                        'material_type' => 'DNA', 'platform' => 'NovaSeq',
                    ],
                ];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'ARMS',
        ]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame(2, $content['sample_count']);
        $this->assertSame(2, $content['patient_count']);
        $this->assertSame('S1', $content['samples'][0]['sample_id']);
        $this->assertSame('', $content['samples'][0]['sample_alias']);
        $this->assertSame('PAT1', json_decode($content['table_json'], true)['data'][0][0]);
    }

    public function test_project_samples_require_an_experiment_type(): void
    {
        $content = (new GetCohortSamplesTool)->handle(new Request([
            'cohort_type' => 'project',
            'cohort_id' => 25062,
        ]))->getStructuredContent();

        $this->assertSame('error', $content['status']);
        $this->assertStringContainsString('exp_type is required', $content['message']);
    }

    public function test_samples_are_filtered_server_side_by_experiment_and_library_type(): void
    {
        $tool = new class extends GetCohortSamplesTool
        {
            protected function cancerTypeSamples(string $cancerTypeId): array
            {
                $samples = [
                    ['patient_id' => 'P1', 'sample_id' => 'S1', 'sample_name' => 'S1', 'sample_alias' => '', 'tissue_category' => 'tumor', 'tissue_type' => 'primary', 'assay_type' => 'RNAseq', 'library_type' => 'polyA', 'material_type' => 'RNA', 'platform' => 'NovaSeq'],
                    ['patient_id' => 'P2', 'sample_id' => 'S2', 'sample_name' => 'S2', 'sample_alias' => '', 'tissue_category' => 'tumor', 'tissue_type' => 'primary', 'assay_type' => 'RNAseq', 'library_type' => 'Ribo-Zero', 'material_type' => 'RNA', 'platform' => 'NovaSeq'],
                    ['patient_id' => 'P3', 'sample_id' => 'S3', 'sample_name' => 'S3', 'sample_alias' => '', 'tissue_category' => 'tumor', 'tissue_type' => 'primary', 'assay_type' => 'Exome', 'library_type' => 'polyA', 'material_type' => 'DNA', 'platform' => 'NovaSeq'],
                ];

                return [
                    'status' => 'success',
                    'cancer_type_id' => $cancerTypeId,
                    'samples' => $samples,
                    'sample_count' => 3,
                    'patient_count' => 3,
                    'table_json' => json_encode([
                        'cols' => array_map(static fn (string $title): array => ['title' => $title], [
                            'Patient ID', 'Sample ID', 'Sample Name', 'Sample Alias', 'Tissue Category',
                            'Tissue Type', 'Assay Type', 'Library Type', 'Material Type', 'Platform',
                        ]),
                        'data' => array_map('array_values', $samples),
                    ], JSON_UNESCAPED_SLASHES),
                ];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'Neuroblastoma',
            'exp_type' => 'RNAseq',
            'library_type' => 'Poly-A',
        ]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame(1, $content['sample_count']);
        $this->assertSame(1, $content['patient_count']);
        $this->assertSame('S1', $content['samples'][0]['sample_id']);
        $this->assertSame('S1', json_decode($content['table_json'], true)['data'][0][1]);
        $this->assertSame(['exp_type' => 'RNAseq', 'library_type' => 'Poly-A'], $content['filters']);
    }

    public function test_chipseq_routes_by_cohort_type(): void
    {
        $tool = new class extends GetCohortChIPseqTool
        {
            protected function cancerTypeChIPseq(string $cancerTypeId): array
            {
                return ['status' => 'success', 'cancer_type_id' => $cancerTypeId];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'ARMS',
        ]))->getStructuredContent();

        $this->assertSame('getCohortChIPseq', $content['action']);
        $this->assertSame('ARMS', $content['cohort_id']);
    }

    public function test_expression_returns_the_requested_cancer_type_rows(): void
    {
        $tool = new class extends GetCohortExpressionTool
        {
            protected function isAvailableCancerType(string $cancerTypeId): bool
            {
                return $cancerTypeId === 'Neuroblastoma';
            }

            protected function resolveGeneSymbol(string $requestedGene, string $genomeVersion): ?string
            {
                return $requestedGene === 'FGFR4' ? 'FGFR4' : null;
            }

            protected function cohortExpressionData(
                string $cohortType,
                int|string $cohortId,
                string $gene,
                string $tissue,
                string $genomeVersion,
            ): array {
                return [
                    'sample_ids' => ['S2', 'S1'],
                    'samples' => ['Alias 2', 'Alias 1'],
                    'patients' => ['Alias 2' => 'P2', 'Alias 1' => 'P1'],
                    'exp_data' => ['FGFR4' => ['hg19' => [8, 2]]],
                ];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'Neuroblastoma',
            'gene' => 'fgfr4',
        ]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame('getCohortExpression', $content['action']);
        $this->assertSame('Neuroblastoma', $content['cohort_id']);
        $this->assertSame('FGFR4', $content['gene']);
        $this->assertSame(2, $content['sample_count']);
        $this->assertSame(5.0, $content['statistics']['median']);
        $this->assertSame('P1', $content['rows'][0]['patient_id']);
        $this->assertStringContainsString('S2', $content['table_json']);
    }

    public function test_expression_routes_a_numeric_project_id_to_its_project_group(): void
    {
        $tool = new class extends GetCohortExpressionTool
        {
            public array $received = [];

            protected function isAccessibleProject(int $projectId): bool
            {
                return $projectId === 25062;
            }

            protected function projectLabel(int $projectId): ?string
            {
                return $projectId === 25062 ? 'RNA landscape' : null;
            }

            protected function resolveGeneSymbol(string $requestedGene, string $genomeVersion): ?string
            {
                return 'FGFR4';
            }

            protected function cohortExpressionData(
                string $cohortType,
                int|string $cohortId,
                string $gene,
                string $tissue,
                string $genomeVersion,
            ): array {
                $this->received = compact('cohortType', 'cohortId', 'gene', 'tissue', 'genomeVersion');

                return [
                    'sample_ids' => ['S1'],
                    'samples' => ['Alias 1'],
                    'patients' => ['Alias 1' => 'P1'],
                    'exp_data' => ['FGFR4' => ['hg19' => [4.25]]],
                ];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'project',
            'cohort_id' => '25062',
            'gene' => 'FGFR4',
            'tissue' => 'tumor',
        ]))->getStructuredContent();

        $this->assertSame('project', $content['cohort_type']);
        $this->assertSame(25062, $content['cohort_id']);
        $this->assertSame('tumor', $content['tissue']);
        $this->assertSame(4.25, $content['rows'][0]['tpm']);
        $this->assertSame(25062, $tool->received['cohortId']);
        $this->assertSame('tumor', $tool->received['tissue']);
    }

    public function test_project_expression_plot_uses_project_metadata_grouping(): void
    {
        $expression = Mockery::mock(ExpressionByGeneTool::class);
        $expression->shouldReceive('handle')->once()->withArgs(function (Request $request): bool {
            $arguments = $request->all();

            return $arguments['project_id'] === 24421
                && $arguments['gene'] === 'FGFR4'
                && $arguments['value_type'] === 'tpm'
                && $arguments['plot_type'] === 'violin'
                && $arguments['group_by'] === 'diagnosis'
                && $arguments['transform'] === 'log2p1';
        })->andReturn(Response::structured([
            'status' => 'success',
            'action' => 'expression_by_gene',
            'project_id' => 24421,
            'project_name' => 'RNAseq_Landscape_Manuscript',
            'gene' => 'FGFR4',
            'plot_type' => 'violin',
            'group_by' => 'diagnosis',
            'transform' => 'log2p1',
            'plot_rows' => [[
                'group' => 'Neuroblastoma',
                'raw_expression' => 4.5,
            ]],
        ]));
        $this->app->instance(ExpressionByGeneTool::class, $expression);

        $tool = new class extends GetCohortExpressionTool
        {
            protected function isAccessibleProject(int $projectId): bool
            {
                return $projectId === 24421;
            }

            protected function projectLabel(int $projectId): ?string
            {
                return $projectId === 24421 ? 'RNAseq_Landscape_Manuscript' : null;
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'project',
            'cohort_id' => 24421,
            'gene' => 'FGFR4',
            'plot_type' => 'violin',
            'group_by' => 'diagnosis',
            'transform' => 'log2p1',
        ]))->getStructuredContent();

        $this->assertSame('expression_by_gene', $content['action']);
        $this->assertSame('project', $content['cohort_type']);
        $this->assertSame(24421, $content['cohort_id']);
        $this->assertSame('diagnosis', $content['group_by']);
        $this->assertNotEmpty($content['plot_rows']);
    }

    public function test_cancer_type_chipseq_returns_clean_rows_and_display_table(): void
    {
        $tool = new class extends GetCohortChIPseqTool
        {
            protected function isAvailableCancerType(string $cancerTypeId): bool
            {
                return $cancerTypeId === 'Neuroblastoma';
            }

            protected function chipseqTable(string $cancerTypeId): array
            {
                return [
                    'cols' => [['title' => 'Library'], ['title' => 'Target']],
                    'data' => [
                        ['<a href="/sample/S1">Sample 1</a>', 'MYCN'],
                        ['<a href="/sample/S2">Sample 2</a>', 'H3K27ac'],
                    ],
                ];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'Neuroblastoma',
            'target' => 'mycn',
        ]))->getStructuredContent();

        $this->assertSame(['Library', 'Target'], $content['columns']);
        $this->assertSame(['Sample 1', 'MYCN'], $content['rows'][0]);
        $this->assertSame(1, $content['row_count']);
        $this->assertSame('mycn', $content['target']);
        $this->assertStringContainsString('<a href=', $content['table_json']);
        $this->assertStringNotContainsString('H3K27ac', $content['table_json']);
    }

    public function test_mutation_genes_routes_by_cohort_type_and_preserves_type(): void
    {
        $tool = new class extends GetCohortMutationGenesTool
        {
            protected function isAccessibleProject(int $projectId): bool
            {
                return $projectId === 25062;
            }

            protected function projectMutationGenes(int $projectId, string $type): array
            {
                return ['status' => 'success', 'project_id' => $projectId, 'type' => $type];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'project',
            'cohort_id' => 25062,
            'type' => 'somatic',
        ]))->getStructuredContent();

        $this->assertSame('getCohortMutationGenes', $content['action']);
        $this->assertSame('somatic', $content['type']);
        $this->assertSame(25062, $content['cohort_id']);
    }

    public function test_schema_requires_cohort_type_and_id(): void
    {
        $schema = (new GetCohortChIPseqTool)->schemaDefinition();

        $this->assertSame(['cohort_type', 'cohort_id'], $schema['required']);
        $this->assertSame(['project', 'cancer_type'], $schema['properties']['cohort_type']['enum']);
        $this->assertSame(['integer', 'string'], $schema['properties']['cohort_id']['type']);
        $this->assertArrayHasKey('target', $schema['properties']);
        $this->assertFalse($schema['additionalProperties']);
    }

    public function test_expression_schema_requires_a_gene_and_cohort(): void
    {
        $schema = (new GetCohortExpressionTool)->schemaDefinition();

        $this->assertSame(['cohort_type', 'cohort_id', 'gene'], $schema['required']);
        $this->assertSame('string', $schema['properties']['gene']['type']);
        $this->assertSame(['all', 'tumor', 'normal', null], $schema['properties']['tissue']['enum']);
    }

    public function test_project_cohort_id_must_be_numeric(): void
    {
        $content = (new GetCohortChIPseqTool)->handle(new Request([
            'cohort_type' => 'project',
            'cohort_id' => 'Compass',
        ]))->getStructuredContent();

        $this->assertSame('error', $content['status']);
        $this->assertStringContainsString('numeric project ID', $content['message']);
    }

    public function test_project_cohort_must_be_accessible_to_the_current_user(): void
    {
        $tool = new class extends GetCohortChIPseqTool
        {
            protected function isAccessibleProject(int $projectId): bool
            {
                return false;
            }

            protected function projectChIPseq(int $projectId): array
            {
                $this->fail('An inaccessible project must not be queried.');
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'project',
            'cohort_id' => 99999,
        ]))->getStructuredContent();

        $this->assertSame('error', $content['status']);
        $this->assertStringContainsString('not accessible', $content['message']);
    }
}
