<?php

namespace Tests\Unit;

use App\Mcp\Tools\RunDifferentialExpressionTool;
use App\Models\Project;
use App\Services\CohortAnalysisService;
use Laravel\Mcp\Request;
use Tests\TestCase;

class RunDifferentialExpressionToolTest extends TestCase
{
    public function test_it_returns_ranked_differential_expression_results(): void
    {
        $service = new class extends CohortAnalysisService {
            private int $calls = 0;
            public function accessibleProject(int $projectId): ?Project { return (new Project())->forceFill(['name' => 'Landscape']); }
            public function fusionCohortSamples(int $projectId, array $cohort): array
            {
                $this->calls++;
                $prefix = $this->calls === 1 ? 'A' : 'B';
                return [
                    ['patient_id' => $prefix.'P1', 'sample_id' => $prefix.'1', 'sample_name' => '', 'sample_alias' => '', 'rnaseq_sample' => ''],
                    ['patient_id' => $prefix.'P2', 'sample_id' => $prefix.'2', 'sample_name' => '', 'sample_alias' => '', 'rnaseq_sample' => ''],
                ];
            }
            public function countMatrixPath(int $projectId, ?string $genomeVersion): string { return '/fake/counts.tsv'; }
            public function runDifferentialExpression(string $matrixPath, array $groupA, array $groupB, float $alpha): array
            {
                return ['rows' => [[
                    'gene' => 'GENE1', 'baseMean' => 20.0, 'log2FoldChange' => 2.5,
                    'lfcSE' => 0.5, 'stat' => 5.0, 'pvalue' => 0.0001, 'padj' => 0.01,
                ]], 'tested_gene_count' => 1, 'significant_gene_count' => 1];
            }
        };
        $tool = new class($service) extends RunDifferentialExpressionTool {
            public function __construct(private CohortAnalysisService $fake) {}
            protected function service(): CohortAnalysisService { return $this->fake; }
        };

        $content = $tool->handle(new Request([
            'project_id' => 24421,
            'group_a' => ['left_gene' => 'EWSR1', 'right_gene' => 'CREB1', 'diagnosis' => 'IMT'],
            'group_b' => ['left_gene' => 'EWSR1', 'right_gene' => 'ATF1', 'diagnosis' => 'IMT'],
        ]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame('GENE1', $content['genes'][0]['gene']);
        $this->assertSame(2.5, $content['genes'][0]['log2FoldChange']);
        $this->assertSame(2, $content['group_a_sample_count']);
        $this->assertStringContainsString('Positive log2 fold change', $content['summary']);
    }

    public function test_it_stops_before_analysis_when_a_cohort_is_too_small(): void
    {
        $service = new class extends CohortAnalysisService {
            public function accessibleProject(int $projectId): ?Project { return (new Project())->forceFill(['name' => 'Landscape']); }
            public function fusionCohortSamples(int $projectId, array $cohort): array
            {
                return [['patient_id' => 'P1', 'sample_id' => $cohort['right_gene'], 'sample_name' => '', 'sample_alias' => '', 'rnaseq_sample' => '']];
            }
        };
        $tool = new class($service) extends RunDifferentialExpressionTool {
            public function __construct(private CohortAnalysisService $fake) {}
            protected function service(): CohortAnalysisService { return $this->fake; }
        };
        $content = $tool->handle(new Request([
            'project_id' => 1,
            'group_a' => ['left_gene' => 'EWSR1', 'right_gene' => 'CREB1'],
            'group_b' => ['left_gene' => 'EWSR1', 'right_gene' => 'ATF1'],
        ]))->getStructuredContent();

        $this->assertSame('insufficient_samples', $content['status']);
        $this->assertSame(1, $content['group_a_sample_count']);
    }

    public function test_schema_has_two_structured_cohorts(): void
    {
        $schema = (new RunDifferentialExpressionTool())->schemaDefinition();
        $this->assertSame(['project_id', 'group_a', 'group_b'], $schema['required']);
        $this->assertSame(['left_gene', 'right_gene'], $schema['properties']['group_a']['required']);
        $this->assertSame(['positive', 'negative', null], $schema['properties']['group_a']['properties']['fusion_status']['enum']);
        $this->assertSame(500, $schema['properties']['limit']['maximum']);
        $this->assertFalse($schema['properties']['return_all_genes']['default']);
    }

    public function test_it_accepts_a_fusion_negative_control_cohort(): void
    {
        $service = new class extends CohortAnalysisService {
            public array $statuses = [];
            public function accessibleProject(int $projectId): ?Project { return (new Project())->forceFill(['name' => 'RMS']); }
            public function fusionCohortSamples(int $projectId, array $cohort): array
            {
                $this->statuses[] = $cohort['fusion_status'] ?? 'positive';
                $prefix = ($cohort['fusion_status'] ?? 'positive') === 'negative' ? 'N' : 'P';
                return [
                    ['patient_id' => $prefix.'1', 'sample_id' => $prefix.'1', 'sample_name' => '', 'sample_alias' => '', 'rnaseq_sample' => ''],
                    ['patient_id' => $prefix.'2', 'sample_id' => $prefix.'2', 'sample_name' => '', 'sample_alias' => '', 'rnaseq_sample' => ''],
                ];
            }
            public function countMatrixPath(int $projectId, ?string $genomeVersion): string { return '/fake/counts.tsv'; }
            public function runDifferentialExpression(string $matrixPath, array $groupA, array $groupB, float $alpha): array
            {
                return ['rows' => [], 'tested_gene_count' => 0, 'significant_gene_count' => 0];
            }
        };
        $tool = new class($service) extends RunDifferentialExpressionTool {
            public function __construct(private CohortAnalysisService $fake) {}
            protected function service(): CohortAnalysisService { return $this->fake; }
        };

        $content = $tool->handle(new Request([
            'project_id' => 26795,
            'group_a' => ['left_gene' => 'PAX3', 'right_gene' => 'FOXO1', 'fusion_status' => 'positive'],
            'group_b' => ['left_gene' => 'PAX3', 'right_gene' => 'FOXO1', 'fusion_status' => 'negative'],
        ]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame(['positive', 'negative'], $service->statuses);
    }

    public function test_it_can_return_all_tested_genes(): void
    {
        $service = new class extends CohortAnalysisService {
            private int $calls = 0;
            public function accessibleProject(int $projectId): ?Project { return (new Project())->forceFill(['name' => 'Landscape']); }
            public function fusionCohortSamples(int $projectId, array $cohort): array
            {
                $this->calls++;
                $prefix = $this->calls === 1 ? 'A' : 'B';
                return [
                    ['patient_id' => $prefix.'1', 'sample_id' => $prefix.'1', 'sample_name' => '', 'sample_alias' => '', 'rnaseq_sample' => ''],
                    ['patient_id' => $prefix.'2', 'sample_id' => $prefix.'2', 'sample_name' => '', 'sample_alias' => '', 'rnaseq_sample' => ''],
                ];
            }
            public function countMatrixPath(int $projectId, ?string $genomeVersion): string { return '/fake/counts.tsv'; }
            public function runDifferentialExpression(string $matrixPath, array $groupA, array $groupB, float $alpha): array
            {
                $rows = [];
                for ($index = 1; $index <= 501; $index++) {
                    $rows[] = ['gene' => 'GENE'.$index, 'padj' => 0.01];
                }
                return ['rows' => $rows, 'tested_gene_count' => 501, 'significant_gene_count' => 501];
            }
        };
        $tool = new class($service) extends RunDifferentialExpressionTool {
            public function __construct(private CohortAnalysisService $fake) {}
            protected function service(): CohortAnalysisService { return $this->fake; }
        };

        $content = $tool->handle(new Request([
            'project_id' => 1,
            'group_a' => ['left_gene' => 'A', 'right_gene' => 'B'],
            'group_b' => ['left_gene' => 'C', 'right_gene' => 'D'],
            'return_all_genes' => true,
        ]))->getStructuredContent();

        $this->assertTrue($content['return_all_genes']);
        $this->assertSame(501, $content['returned_gene_count']);
        $this->assertCount(501, $content['genes']);
    }
}
