<?php

namespace Tests\Unit;

use App\Mcp\Tools\GetCorrelationDataTool;
use Laravel\Mcp\Request;
use Tests\TestCase;

class GetCorrelationDataToolTest extends TestCase
{
    public function test_it_returns_correlations_sorted_by_absolute_coefficient(): void
    {
        $tool = new class extends GetCorrelationDataTool
        {
            protected function accessibleProject(int $projectId)
            {
                return $projectId === 24421 ? (object) ['name' => 'Landscape'] : null;
            }

            protected function resolveGeneSymbol(string $geneSymbol, string $genomeVersion): ?string
            {
                return $geneSymbol === 'FGFR4' ? 'FGFR4' : null;
            }

            protected function correlationTable(
                int $projectId,
                string $geneSymbol,
                float $cutoff,
                string $genomeVersion,
                string $method,
                string $valueType
            ): array {
                return [
                    'cols' => [
                        ['title' => 'Symbol'],
                        ['title' => 'Gene'],
                        ['title' => 'Coefficient'],
                        ['title' => 'Positive/negative'],
                    ],
                    'data' => [
                        ['GENE1', 'ENSG1', '0.55', 'Positive'],
                        ['GENE2', 'ENSG2', '-0.91', 'Negative'],
                        ['GENE3', 'ENSG3', '0.10', 'Positive'],
                    ],
                ];
            }
        };

        $content = $tool->handle(new Request([
            'project_id' => 24421,
            'gene_symbol' => 'fgfr4',
        ]))->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame('getCorrelationData', $content['action']);
        $this->assertSame(24421, $content['project_id']);
        $this->assertSame('FGFR4', $content['gene_symbol']);
        $this->assertSame('GENE2', $content['correlations'][0]['gene_symbol']);
        $this->assertSame(-0.91, $content['correlations'][0]['coefficient']);
        $this->assertSame(2, $content['correlation_count']);
        $this->assertSame(2, count(json_decode($content['table_json'], true)['data']));
        $this->assertSame('pearson', $content['method']);
        $this->assertSame('tpm', $content['value_type']);
    }

    public function test_it_rejects_an_inaccessible_project(): void
    {
        $tool = new class extends GetCorrelationDataTool
        {
            protected function accessibleProject(int $projectId)
            {
                return null;
            }
        };

        $content = $tool->handle(new Request([
            'project_id' => 999,
            'gene_symbol' => 'FGFR4',
        ]))->getStructuredContent();

        $this->assertSame('error', $content['status']);
        $this->assertStringContainsString('not accessible', $content['message']);
    }

    public function test_schema_orders_project_id_before_gene_symbol(): void
    {
        $tool = new class extends GetCorrelationDataTool
        {
            protected function accessibleProject(int $projectId)
            {
                return null;
            }
        };

        $schema = $tool->schemaDefinition();

        $this->assertSame(['project_id', 'gene_symbol'], $schema['required']);
        $this->assertSame(['project_id', 'gene_symbol'], array_slice(array_keys($schema['properties']), 0, 2));
        $this->assertFalse($schema['additionalProperties']);
    }
}
