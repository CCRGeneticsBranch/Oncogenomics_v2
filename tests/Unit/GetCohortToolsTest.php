<?php

namespace Tests\Unit;

use App\Mcp\Tools\GetCohortChIPseqTool;
use App\Mcp\Tools\GetCohortMutationGenesTool;
use App\Mcp\Tools\GetCohortSamplesTool;
use Laravel\Mcp\Request;
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
        $content = (new GetCohortSamplesTool())->handle(new Request([
            'cohort_type' => 'project',
            'cohort_id' => 25062,
        ]))->getStructuredContent();

        $this->assertSame('error', $content['status']);
        $this->assertStringContainsString('exp_type is required', $content['message']);
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
                    'data' => [['<a href="/sample/S1">Sample 1</a>', 'MYCN']],
                ];
            }
        };

        $content = $tool->handle(new Request([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'Neuroblastoma',
        ]))->getStructuredContent();

        $this->assertSame(['Library', 'Target'], $content['columns']);
        $this->assertSame(['Sample 1', 'MYCN'], $content['rows'][0]);
        $this->assertSame(1, $content['row_count']);
        $this->assertStringContainsString('<a href=', $content['table_json']);
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
        $schema = (new GetCohortChIPseqTool())->schema();

        $this->assertSame(['cohort_type', 'cohort_id'], $schema['required']);
        $this->assertSame(['project', 'cancer_type'], $schema['properties']['cohort_type']['enum']);
        $this->assertSame(['integer', 'string'], $schema['properties']['cohort_id']['type']);
        $this->assertFalse($schema['additionalProperties']);
    }

    public function test_project_cohort_id_must_be_numeric(): void
    {
        $content = (new GetCohortChIPseqTool())->handle(new Request([
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
