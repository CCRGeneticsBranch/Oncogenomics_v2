<?php

namespace Tests\Unit;

use App\Ai\Support\ToolResultCompactor;
use App\Mcp\Tools\GetExpGeneSummaryTool;
use App\Services\ExpressionPlotPresenter;
use Tests\TestCase;

class GlobalExpressionPlotTest extends TestCase
{
    public function test_global_boxplot_keeps_753_groups_and_all_21642_observations(): void
    {
        $rows = [];
        for ($i = 0; $i < 21642; $i++) {
            $rows[] = ['group' => 'Diagnosis '.($i % 753), 'raw_expression' => $i % 101];
        }
        $result = ['action' => 'getExpGeneSummary', 'gene' => 'MYCN', 'value_type' => 'tpm',
            'group_by' => 'diagnosis', 'plot_type' => 'boxplot', 'plot_rows' => $rows];
        $chart = (new ExpressionPlotPresenter)->present($result);
        $this->assertSame(753, $chart['displayed_group_count']);
        $this->assertSame(21642, $chart['displayed_value_count']);
        $this->assertFalse($chart['truncated']);
        $this->assertCount(753, $chart['data'][0]['q1']);
        $this->assertLessThan(2000000, strlen(json_encode($chart)));
        $preview = (new ToolResultCompactor)->compact($result);
        $this->assertSame(753, $preview['expression_summary']['row_count']);
        $this->assertCount(75, $preview['expression_summary']['rows']);
        $this->assertArrayNotHasKey('plot_rows', $preview);
        $this->assertSame('boxplot', $preview['plot_type']);
    }

    public function test_exact_transformed_statistics_whiskers_and_singletons(): void
    {
        $rows = array_map(fn ($v) => ['group' => 'A', 'raw_expression' => $v], [0, 1, 3, 7, 15, 1023]);
        $rows[] = ['group' => 'B', 'raw_expression' => 3];
        $rows[] = ['group' => 'Invalid', 'raw_expression' => null];
        $chart = (new ExpressionPlotPresenter)->present(['action' => 'getExpGeneSummary',
            'gene' => 'MYCN', 'group_by' => 'diagnosis', 'value_type' => 'tpm',
            'plot_type' => 'boxplot', 'transform' => 'log2p1', 'plot_rows' => $rows]);
        $trace = $chart['data'][0];
        $this->assertSame([1.0, 2.0], $trace['q1']);
        $this->assertSame([2.5, 2.0], $trace['median']);
        $this->assertSame([4.0, 2.0], $trace['q3']);
        $this->assertSame([0.0, 2.0], $trace['lowerfence']);
        $this->assertSame([8.5, 2.0], $trace['upperfence']);
        $this->assertStringContainsString('Individual outlier points are not displayed', $chart['summary']);
    }

    public function test_chart_followup_intent_and_public_tool_schema(): void
    {
        $intent = (new ExpressionPlotPresenter)->applyQueryIntent(['gene_id' => 'MYCN'], 'Can you show them as a boxplot group by diangosis?');
        $this->assertSame('boxplot', $intent['plot_type']);
        $this->assertSame('diangosis', $intent['group_by']);
        $schema = (new GetExpGeneSummaryTool)->schemaDefinition();
        $this->assertContains('boxplot', $schema['properties']['plot_type']['enum']);
        $this->assertSame(['gene_id'], $schema['required']);
        $this->assertArrayNotHasKey('project_id', $schema['properties']);
    }
}
