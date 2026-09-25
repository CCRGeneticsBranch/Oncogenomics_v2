<?php

namespace Tests\Unit;

use App\Services\ExpressionPlotPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpressionPlotPresenterTest extends TestCase
{
    public function test_it_builds_a_log2p1_violin_ordered_by_median_descending(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'plot_type' => 'violin',
            'transform' => 'log2p1',
            'group_order' => 'median_desc',
            'group_by' => 'diagnosis',
            'plot_rows' => [
                ['group' => 'Low', 'raw_expression' => 0],
                ['group' => 'High', 'raw_expression' => 15],
                ['group' => 'Low', 'raw_expression' => 3],
                ['group' => 'High', 'raw_expression' => 31],
                ['group' => 'Low', 'raw_expression' => 7],
                ['group' => 'High', 'raw_expression' => 63],
            ],
        ]);

        $this->assertNotNull($chart);
        $this->assertSame('plotly', $chart['type']);
        $this->assertSame('FGFR4 expression by diagnosis', $chart['title']);
        $this->assertSame(['High', 'Low'], array_column($chart['data'], 'name'));
        $this->assertSame(['High', 'Low'], $chart['layout']['xaxis']['categoryarray']);
        $this->assertSame('High', $chart['data'][0]['x0']);
        $this->assertSame([4.0, 5.0, 6.0], $chart['data'][0]['y']);
        $this->assertSame([0.0, 2.0, 3.0], $chart['data'][1]['y']);
        $this->assertFalse($chart['data'][0]['points']);
        $this->assertSame('hard', $chart['data'][0]['spanmode']);
        $this->assertSame(0.8, $chart['data'][0]['width']);
        $this->assertFalse($chart['data'][0]['box']['visible']);
        $this->assertSame(['High', 3, 5.0], $chart['data'][0]['meta']);
        $this->assertSame('log2(expression + 1)', $chart['layout']['yaxis']['title']['text']);
        $this->assertSame(6, $chart['value_count']);
        $this->assertSame(2, $chart['group_count']);
        $this->assertFalse($chart['truncated']);
    }

    public function test_it_returns_no_chart_when_no_plot_was_requested(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'plot_rows' => [['group' => 'ARMS', 'raw_expression' => 12.5]],
        ]);

        $this->assertNull($chart);
    }

    public function test_query_intent_recovers_plot_settings_omitted_by_the_model(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'value_type' => 'tmm-rpkm',
            'plot_rows' => [
                ['group' => 'Tumor', 'raw_expression' => 1],
                ['group' => 'Tumor', 'raw_expression' => 3],
                ['group' => 'Normal', 'raw_expression' => 15],
                ['group' => 'Normal', 'raw_expression' => 31],
            ],
        ], null, 'Show me log2 FGFR4 expression and plot a violin plot order by median value in descending order.');

        $this->assertNotNull($chart);
        $this->assertSame('violin', $chart['data'][0]['type']);
        $this->assertSame(['Normal', 'Tumor'], $chart['layout']['xaxis']['categoryarray']);
        $this->assertSame('log2(TMM-RPKM + 1)', $chart['layout']['yaxis']['title']['text']);
        $this->assertStringContainsString('order: median_desc', $chart['summary']);
    }

    public function test_follow_up_violin_uses_cohort_expression_rows(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'status' => 'success',
            'action' => 'getCohortExpression',
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'Neuroblastoma',
            'cohort_name' => 'Neuroblastoma',
            'gene' => 'FGFR4',
            'value_type' => 'tpm',
            'rows' => [
                ['patient_id' => 'P1', 'sample_id' => 'S1', 'tpm' => 0.5],
                ['patient_id' => 'P2', 'sample_id' => 'S2', 'tpm' => 2.22],
                ['patient_id' => 'P3', 'sample_id' => 'S3', 'tpm' => 125.99],
            ],
        ], null, 'plot violin');

        $this->assertNotNull($chart);
        $this->assertSame('violin', $chart['data'][0]['type']);
        $this->assertSame('Neuroblastoma', $chart['data'][0]['name']);
        $this->assertSame([0.5, 2.22, 125.99], $chart['data'][0]['y']);
        $this->assertSame('TPM', $chart['layout']['yaxis']['title']['text']);
        $this->assertSame(3, $chart['value_count']);
        $this->assertSame(1, $chart['group_count']);
    }

    public function test_it_builds_a_grouped_expression_heatmap(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'value_type' => 'tmm-rpkm',
            'plot_type' => 'heatmap',
            'group_by' => 'diagnosis',
            'transform' => 'log2p1',
            'group_order' => 'median_desc',
            'plot_rows' => [
                ['group' => 'Low', 'raw_expression' => 0],
                ['group' => 'High', 'raw_expression' => 15],
                ['group' => 'Low', 'raw_expression' => 3],
                ['group' => 'High', 'raw_expression' => 31],
            ],
        ]);

        $this->assertNotNull($chart);
        $this->assertSame('heatmap', $chart['data'][0]['type']);
        $this->assertSame(['FGFR4'], $chart['data'][0]['y']);
        $this->assertSame([[4.0, 5.0, 0.0, 2.0]], $chart['data'][0]['z']);
        $this->assertSame(['High', 'Low'], $chart['layout']['xaxis']['ticktext']);
        $this->assertSame([1.5, 3.5], $chart['layout']['xaxis']['tickvals']);
        $this->assertSame(['FGFR4'], $chart['layout']['yaxis']['categoryarray']);
        $this->assertSame(
            'Samples grouped by diagnosis',
            $chart['layout']['xaxis']['title']['text'],
        );
        $this->assertSame('log2(TMM-RPKM + 1)', $chart['data'][0]['colorbar']['title']['text']);
        $this->assertStringContainsString('Heatmap plot of FGFR4', $chart['summary']);
        $this->assertStringContainsString('source order within each median-ordered group', $chart['summary']);
    }

    public function test_zscore_heatmap_uses_a_symmetric_diverging_color_scale(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'plot_type' => 'heatmap',
            'transform' => 'zscore',
            'plot_rows' => [
                ['sample' => 'S1', 'group' => 'A', 'raw_expression' => 0],
                ['sample' => 'S2', 'group' => 'A', 'raw_expression' => 1],
                ['sample' => 'S3', 'group' => 'A', 'raw_expression' => 9],
            ],
        ]);

        $this->assertNotNull($chart);
        $this->assertSame('RdBu', $chart['data'][0]['colorscale']);
        $this->assertSame(0, $chart['data'][0]['zmid']);
        $this->assertEqualsWithDelta(
            -$chart['data'][0]['zmax'],
            $chart['data'][0]['zmin'],
            0.000001,
        );
    }

    public function test_it_labels_project_tumor_normal_data_and_expression_units(): void
    {
        $rows = [];
        foreach (range(1, 9) as $index) {
            $rows[] = [
                'group' => 'Diagnosis '.$index,
                'dataset' => $index % 2 === 0 ? 'normal' : 'tumor',
                'raw_expression' => $index,
            ];
            $rows[] = [
                'group' => 'Diagnosis '.$index,
                'dataset' => $index % 2 === 0 ? 'normal' : 'tumor',
                'raw_expression' => $index + 0.5,
            ];
        }

        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'value_type' => 'tmm-rpkm',
            'plot_type' => 'violin',
            'transform' => 'log2p1',
            'group_order' => 'median_desc',
            'plot_rows' => $rows,
        ]);

        $this->assertNotNull($chart);
        $this->assertSame('FGFR4 expression by Dataset', $chart['title']);
        $this->assertSame('log2(TMM-RPKM + 1)', $chart['layout']['yaxis']['title']['text']);
        $this->assertGreaterThanOrEqual(1000, $chart['layout']['width']);
        $this->assertFalse($chart['config']['responsive']);
    }

    public function test_it_removes_the_redundant_all_suffix_from_ungrouped_dataset_labels(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'value_type' => 'tmm-rpkm',
            'plot_type' => 'violin',
            'transform' => 'log2p1',
            'group_order' => 'median_desc',
            'plot_rows' => [
                [
                    'group' => 'Tumor | All', 'dataset' => 'tumor',
                    'metadata_field' => null, 'metadata_value' => 'All', 'raw_expression' => 1.31,
                ],
                [
                    'group' => 'Tumor | All', 'dataset' => 'tumor',
                    'metadata_field' => null, 'metadata_value' => 'All', 'raw_expression' => 1.42,
                ],
                [
                    'group' => 'Normal | All', 'dataset' => 'normal',
                    'metadata_field' => null, 'metadata_value' => 'All', 'raw_expression' => 7.13,
                ],
                [
                    'group' => 'Normal | All', 'dataset' => 'normal',
                    'metadata_field' => null, 'metadata_value' => 'All', 'raw_expression' => 7.24,
                ],
            ],
        ]);

        $this->assertNotNull($chart);
        $this->assertSame(['Normal', 'Tumor'], $chart['layout']['xaxis']['categoryarray']);
        $this->assertSame(['Normal', 'Tumor'], array_column($chart['data'], 'x0'));
    }

    public function test_it_ignores_malformed_rows_and_sanitizes_labels(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => '<b>FGFR4</b>',
            'plot_type' => 'violin',
            'transform' => 'log2p1',
            'group_order' => 'none',
            'plot_rows' => [
                'not a row',
                ['group' => 'Invalid', 'raw_expression' => 'not numeric'],
                ['group' => 'Invalid', 'raw_expression' => -2],
                ['group' => '<img src=x onerror=alert(1)>ARMS', 'raw_expression' => 3],
                ['group' => '<img src=x onerror=alert(1)>ARMS', 'expression' => 7],
            ],
        ]);

        $this->assertNotNull($chart);
        $this->assertSame('FGFR4 expression by Group', $chart['title']);
        $this->assertSame('ARMS', $chart['data'][0]['name']);
        $this->assertSame([2.0, 3.0], $chart['data'][0]['y']);
        $this->assertSame(2, $chart['value_count']);
        $this->assertStringNotContainsString('<', json_encode($chart, JSON_THROW_ON_ERROR));

        $this->assertNull((new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'plot_type' => 'violin',
            'transform' => 'none',
            'plot_rows' => [['group' => 'A', 'raw_expression' => 'bad']],
        ]));
    }

    public function test_chart_payload_is_bounded_while_order_uses_all_values(): void
    {
        $rows = [];
        foreach (['Low' => 1, 'Middle' => 10, 'High' => 100] as $group => $value) {
            for ($index = 0; $index < 3000; $index++) {
                $rows[] = ['group' => $group, 'raw_expression' => $value + ($index % 3)];
            }
        }

        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'plot_type' => 'violin',
            'transform' => 'none',
            'group_order' => 'median_desc',
            'plot_rows' => $rows,
        ], 8192);

        $this->assertNotNull($chart);
        $this->assertLessThanOrEqual(8192, strlen(json_encode($chart, JSON_THROW_ON_ERROR)));
        $this->assertSame(9000, $chart['value_count']);
        $this->assertSame(['High', 'Middle', 'Low'], $chart['layout']['xaxis']['categoryarray']);
        $this->assertTrue($chart['truncated']);
        $this->assertLessThan(9000, $chart['displayed_value_count']);
        $this->assertStringContainsString('medians and group ordering were computed from all valid values', $chart['summary']);
    }

    public function test_violin_uses_raw_distributions_and_excludes_single_value_groups_before_sorting(): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'TP53',
            'plot_type' => 'violin',
            'transform' => 'none',
            'group_order' => 'median_desc',
            'group_by' => 'Diagnosis',
            'plot_rows' => [
                ['group' => 'Misleading singleton', 'raw_expression' => 100],
                ['group' => 'Lower distribution', 'raw_expression' => 10],
                ['group' => 'Lower distribution', 'raw_expression' => 12],
                ['group' => 'Higher distribution', 'raw_expression' => 20],
                ['group' => 'Higher distribution', 'raw_expression' => 22],
                ['group' => 'Higher distribution', 'raw_expression' => 24],
            ],
        ]);

        $this->assertNotNull($chart);
        $this->assertSame(
            ['Higher distribution', 'Lower distribution'],
            $chart['layout']['xaxis']['categoryarray'],
        );
        $this->assertSame('Higher distribution', $chart['data'][0]['x0']);
        $this->assertSame('Lower distribution', $chart['data'][1]['x0']);
        $this->assertSame([20.0, 22.0, 24.0], $chart['data'][0]['y']);
        $this->assertSame([10.0, 12.0], $chart['data'][1]['y']);
        $this->assertFalse($chart['data'][0]['points']);
        $this->assertSame(1, $chart['excluded_group_count']);
        $this->assertSame(1, $chart['excluded_value_count']);
        $this->assertStringContainsString(
            'omitted because a violin density requires at least 2 observations per group',
            $chart['summary'],
        );
    }

    public function test_large_grouped_violin_requires_stable_distributions_before_sorting(): void
    {
        $rows = [];
        foreach (range(1, 21) as $index) {
            $rows[] = ['group' => 'Singleton '.$index, 'raw_expression' => 100 + $index];
        }
        foreach ([10, 11, 12, 13, 14] as $value) {
            $rows[] = ['group' => 'Lower distribution', 'raw_expression' => $value];
        }
        foreach ([20, 21, 22, 23, 24] as $value) {
            $rows[] = ['group' => 'Higher distribution', 'raw_expression' => $value];
        }
        foreach ([200, 201, 202, 203] as $value) {
            $rows[] = ['group' => 'Sparse misleading group', 'raw_expression' => $value];
        }

        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'TP53',
            'plot_type' => 'violin',
            'group_order' => 'median_desc',
            'group_by' => 'Diagnosis',
            'plot_rows' => $rows,
        ]);

        $this->assertNotNull($chart);
        $this->assertSame(5, $chart['minimum_group_size']);
        $this->assertSame(
            ['Higher distribution', 'Lower distribution'],
            $chart['layout']['xaxis']['categoryarray'],
        );
        $this->assertSame('hard', $chart['data'][0]['spanmode']);
        $this->assertSame(22, $chart['excluded_group_count']);
        $this->assertStringContainsString(
            'requires at least 5 observations per group',
            $chart['summary'],
        );
    }

    public function test_many_diagnoses_use_separate_full_width_density_traces(): void
    {
        $rows = [];
        foreach (range(1, 30) as $group) {
            foreach (range(1, 8) as $sample) {
                $rows[] = [
                    'group' => 'Diagnosis '.$group,
                    'raw_expression' => $group + ($sample / 10),
                ];
            }
        }

        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'TP53',
            'plot_type' => 'violin',
            'group_by' => 'Diagnosis',
            'transform' => 'log2p1',
            'plot_rows' => $rows,
        ]);

        $this->assertNotNull($chart);
        $this->assertCount(30, $chart['data']);
        $this->assertSame(array_fill(0, 30, 'violin'), array_column($chart['data'], 'type'));
        $this->assertCount(30, array_unique(array_column($chart['data'], 'x0')));
        $this->assertSame(240, array_sum(array_map(
            static fn (array $trace): int => count($trace['y']),
            $chart['data'],
        )));
        $this->assertFalse($chart['data'][0]['points']);
        $this->assertSame(0.8, $chart['data'][0]['width']);
    }

    public function test_heatmap_payload_is_bounded_while_group_order_uses_all_values(): void
    {
        $rows = [];
        foreach (range(1, 30) as $group) {
            foreach (range(1, 200) as $sample) {
                $rows[] = [
                    'sample' => 'SAMPLE-'.$group.'-'.$sample,
                    'patient_id' => 'PATIENT-'.$group.'-'.$sample,
                    'dataset' => 'tumor',
                    'group' => 'Diagnosis '.$group,
                    'raw_expression' => $group + ($sample / 1000),
                ];
            }
        }

        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'plot_type' => 'heatmap',
            'transform' => 'none',
            'group_order' => 'median_desc',
            'group_by' => 'diagnosis',
            'plot_rows' => $rows,
        ], 8192);

        $this->assertNotNull($chart);
        $this->assertLessThanOrEqual(8192, strlen(json_encode($chart, JSON_THROW_ON_ERROR)));
        $this->assertSame(6000, $chart['value_count']);
        $this->assertSame('Diagnosis 30', $chart['layout']['xaxis']['ticktext'][0]);
        $this->assertTrue($chart['truncated']);
        $this->assertLessThan(6000, $chart['displayed_value_count']);
    }

    public function test_heatmap_keeps_all_small_groups_when_there_are_more_than_one_hundred(): void
    {
        $rows = [];
        foreach (range(1, 111) as $group) {
            $rows[] = [
                'sample' => 'S'.$group,
                'group' => 'Diagnosis '.$group,
                'raw_expression' => $group,
            ];
        }

        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'plot_type' => 'heatmap',
            'transform' => 'log2p1',
            'group_order' => 'median_desc',
            'group_by' => 'diagnosis',
            'plot_rows' => $rows,
        ]);

        $this->assertNotNull($chart);
        $this->assertSame(111, $chart['group_count']);
        $this->assertSame(111, $chart['displayed_group_count']);
        $this->assertCount(111, $chart['layout']['xaxis']['ticktext']);
        $this->assertFalse($chart['truncated']);
    }

    #[DataProvider('supportedPlotTypes')]
    public function test_it_builds_each_supported_plot_type(string $requested, string $traceType): void
    {
        $chart = (new ExpressionPlotPresenter)->present([
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'plot_type' => $requested,
            'transform' => 'none',
            'group_order' => 'median_asc',
            'plot_rows' => [
                ['group' => 'B', 'raw_expression' => 4],
                ['group' => 'B', 'raw_expression' => 5],
                ['group' => 'A', 'raw_expression' => 2],
                ['group' => 'A', 'raw_expression' => 3],
            ],
        ]);

        $this->assertNotNull($chart);
        $this->assertSame($traceType, $chart['data'][0]['type']);
    }

    /** @return array<string, array{string, string}> */
    public static function supportedPlotTypes(): array
    {
        return [
            'heatmap' => ['heatmap', 'heatmap'],
            'violin' => ['violin', 'violin'],
            'boxplot' => ['boxplot', 'box'],
            'barplot' => ['barplot', 'bar'],
            'column' => ['column', 'bar'],
        ];
    }
}
