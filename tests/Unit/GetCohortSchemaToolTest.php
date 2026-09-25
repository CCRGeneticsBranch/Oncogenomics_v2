<?php

namespace Tests\Unit;

use App\Mcp\Tools\GetCohortSchemaTool;
use App\Models\Project;
use App\Services\CohortAnalysisService;
use Laravel\Mcp\Request;
use Tests\TestCase;

class GetCohortSchemaToolTest extends TestCase
{
    public function test_it_describes_project_scoped_fusion_cohorts(): void
    {
        $service = new class extends CohortAnalysisService {
            public function accessibleProject(int $projectId): ?Project
            {
                return (new Project())->forceFill(['name' => 'RNAseq landscape']);
            }
            public function diagnoses(int $projectId): array
            {
                return [['diagnosis' => 'IMT', 'rna_sample_count' => 8]];
            }
            public function fusionCallers(int $projectId): array
            {
                return ['Arriba'];
            }
            public function expressionMatrices(int $projectId): array
            {
                return [['data_type' => 'count', 'genome_version' => null, 'path' => 'expression.count.tsv']];
            }
        };
        $tool = new class($service) extends GetCohortSchemaTool {
            public function __construct(private CohortAnalysisService $fake) {}
            protected function service(): CohortAnalysisService { return $this->fake; }
        };

        $content = $tool->handle(new Request(['project_id' => 24421]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame('project', $content['scope']);
        $this->assertSame(['left_gene', 'right_gene'], $content['cohort_definition']['required_fields']);
        $this->assertSame('DESeq2', $content['supported_analyses'][0]['method']);
        $this->assertSame('cancer_type', $content['unsupported_scopes'][0]['scope']);
    }

    public function test_schema_requires_project_id(): void
    {
        $schema = (new GetCohortSchemaTool())->schemaDefinition();
        $this->assertSame(['project_id'], $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }
}
