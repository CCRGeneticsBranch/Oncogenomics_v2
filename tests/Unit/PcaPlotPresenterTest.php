<?php
namespace Tests\Unit;

use App\Services\PcaPlotPresenter;
use App\Services\ClinomicsChatAgentRunner;
use App\Ai\Support\ToolResultCompactor;
use App\Ai\Support\ChatbotRunContext;
use App\Ai\Tools\ScopedMcpTool;
use App\Mcp\Tools\GetPCADataTool;
use Tests\TestCase;

class PcaPlotPresenterTest extends TestCase
{
    private function pcaResult(): array
    {
        return ['action' => 'getPCAData', 'status' => 'success', 'project_id' => 42,
            'coordinate_count' => 2, 'explained_variance_percent' => ['PC1' => 40, 'PC2' => 20, 'PC3' => 10],
            'coordinates' => [['entity_id' => 'A', 'pc1' => 1, 'pc2' => 2, 'pc3' => 3],
                ['entity_id' => '<b>B</b>', 'pc1' => -1, 'pc2' => -2, 'pc3' => -3]]];
    }

    public function test_three_dimensional_followup_reaches_browser_chart_with_exact_coordinates(): void
    {
        $method = new \ReflectionMethod(ClinomicsChatAgentRunner::class, 'presentExecutions');
        $executions = $method->invoke(app(ClinomicsChatAgentRunner::class), [['tool' => 'getPCAData', 'result' => $this->pcaResult()]], 'show me a 3d plot?');
        $chart = $executions[0]['charts'][0];
        $this->assertSame('scatter3d', $chart['data'][0]['type']);
        $this->assertSame([1.0, -1.0], $chart['data'][0]['x']);
        $this->assertSame([2.0, -2.0], $chart['data'][0]['y']);
        $this->assertSame([3.0, -3.0], $chart['data'][0]['z']);
        $this->assertSame('PC3 (10%)', $chart['layout']['scene']['zaxis']['title']['text']);
        $this->assertSame('&lt;b&gt;B&lt;/b&gt;', $chart['data'][0]['text'][1]);
        $this->assertFalse($chart['truncated']);
    }

    public function test_default_2d_and_invalid_coordinates(): void
    {
        $presenter = new PcaPlotPresenter;
        $chart = $presenter->present($this->pcaResult());
        $this->assertSame('scatter', $chart['data'][0]['type']);
        $this->assertArrayNotHasKey('z', $chart['data'][0]);
        $result = $this->pcaResult();
        $result['coordinates'] = [['pc1' => 1, 'pc2' => 2, 'pc3' => null]];
        $this->assertNull($presenter->present($result, query: '3d plot'));
        $this->assertNotNull($presenter->present($result));
        $result['status'] = 'no_data';
        $this->assertNull($presenter->present($result));
    }

    public function test_adapter_preserves_3d_intent_and_compaction_keeps_chart_capability(): void
    {
        $adapter = new ScopedMcpTool(new GetPCADataTool, new ChatbotRunContext('project', 42, 'Study', 'show me a 3d plot?'), new ToolResultCompactor);
        $arguments = $adapter->applyScope([]);
        $this->assertSame('scatter3d', $arguments['plot_type']);
        $this->assertSame(42, $arguments['project_id']);
        $preview = (new ToolResultCompactor)->compact($this->pcaResult());
        $this->assertSame(2, $preview['coordinate_count']);
        $this->assertArrayNotHasKey('coordinates', $preview);
        $this->assertStringContainsString('scatter3d', $preview['chart_hint']);
    }

    public function test_diagnosis_colors_use_exact_metadata_index_and_entity_mapping_in_3d(): void
    {
        $result = $this->pcaResult();
        $result['plot_type'] = 'scatter3d';
        $result['sample_metadata'] = ['attr_list' => ['Tissue', 'Diagnosis'], 'data' => [
            'A' => ['Tumor', 'Rhabdomyosarcoma'], '<b>B</b>' => ['Tumor', 'Neuroblastoma'],
        ]];
        $chart = (new PcaPlotPresenter)->present($result, query: 'colored by diagnosis');
        $this->assertSame(['Rhabdomyosarcoma', 'Neuroblastoma'], array_column($chart['data'], 'name'));
        $this->assertTrue($chart['layout']['showlegend']);
        $this->assertSame('Diagnosis', $chart['layout']['legend']['title']['text']);
        $this->assertSame([3.0], $chart['data'][0]['z']);
        $this->assertSame([-3.0], $chart['data'][1]['z']);
        $this->assertNotSame($chart['data'][0]['marker']['color'], $chart['data'][1]['marker']['color']);
        $this->assertSame('scatter3d', $chart['data'][0]['type']);
    }

    public function test_diagnosis_resolves_to_sample_tissue_type_and_preserves_explicit_fields(): void
    {
        $result = $this->pcaResult();
        $result['plot_type'] = 'scatter3d';
        $result['sample_metadata'] = ['attr_list' => ['Tissue', 'Tissue Type', 'Sex'], 'data' => [
            'A' => ['Tumor', 'RMS', 'Female'], '<b>B</b>' => ['Tumor', 'Neuroblastoma', 'Male'],
        ]];
        $presenter = new PcaPlotPresenter;
        $chart = $presenter->present($result, query: 'color by diagnosis');
        $this->assertSame(['RMS', 'Neuroblastoma'], array_column($chart['data'], 'name'));
        $this->assertSame('Tissue Type', $chart['layout']['legend']['title']['text']);
        $this->assertSame([3.0], $chart['data'][0]['z']);
        $result['color_by'] = 'Sex';
        $chart = $presenter->present($result, query: 'color by diagnosis');
        $this->assertSame(['Female', 'Male'], array_column($chart['data'], 'name'));
        $result['color_by'] = 'Diagnosis';
        $result['sample_metadata']['attr_list'][] = 'Diagnosis';
        $result['sample_metadata']['data']['A'][] = 'Explicit diagnosis';
        $chart = $presenter->present($result);
        $this->assertSame('Explicit diagnosis', $chart['data'][0]['name']);
        $this->assertSame('Diagnosis', $chart['layout']['legend']['title']['text']);
    }

    public function test_production_tissue_diagnosis_label_resolves_color_aliases_without_changing_other_fields(): void
    {
        $result = $this->pcaResult();
        $result['sample_metadata'] = ['attr_list'=>['Tumor|Normal','Tissue|Diagnosis','Library Type'], 'data'=>[
            'A'=>['Tumor','RMS','Capture A'], '<b>B</b>'=>['Tumor','Neuroblastoma','Capture B'],
        ]];
        foreach (['Diagnosis','Tissue Type','tissue_type','Tissue|Diagnosis'] as $field) {
            foreach (['scatter','scatter3d'] as $type) {
                $result['color_by'] = $field;
                $result['plot_type'] = $type;
                $chart = (new PcaPlotPresenter)->present($result);
                $this->assertSame(['RMS','Neuroblastoma'],array_column($chart['data'],'name'));
                $this->assertSame('Tissue|Diagnosis',$chart['layout']['legend']['title']['text']);
                $this->assertNotSame('#999999',$chart['data'][0]['marker']['color']);
                $this->assertSame($type,$chart['data'][0]['type']);
            }
        }
        $result['color_by'] = 'Library Type';
        $chart = (new PcaPlotPresenter)->present($result);
        $this->assertSame(['Capture A','Capture B'],array_column($chart['data'],'name'));
    }

    public function test_missing_metadata_is_unknown_and_never_defaults_to_first_column(): void
    {
        $result = $this->pcaResult();
        $result['color_by'] = 'Diagnosis';
        $result['sample_metadata'] = ['attr_list' => ['Tissue'], 'data' => ['A' => ['Tumor']]];
        $chart = (new PcaPlotPresenter)->present($result);
        $this->assertSame(['Unknown'], array_column($chart['data'], 'name'));
        $this->assertStringContainsString('unavailable', $chart['summary']);
        $result['sample_metadata'] = ['attr_list' => ['Diagnosis'], 'data' => ['A' => ['RMS']]];
        $chart = (new PcaPlotPresenter)->present($result);
        $this->assertSame(['RMS', 'Unknown'], array_column($chart['data'], 'name'));
        $this->assertSame(2, $chart['displayed_point_count']);
    }

    public function test_coloring_followup_is_exposed_to_tool_and_llm_preview(): void
    {
        $adapter = new ScopedMcpTool(new GetPCADataTool, new ChatbotRunContext('project', 42, 'Study', 'colored by diagnosis'), new ToolResultCompactor);
        $args = $adapter->applyScope(['plot_type' => 'scatter3d']);
        $this->assertSame('Diagnosis', $args['color_by']);
        $this->assertSame('scatter3d', $args['plot_type']);
        $this->assertTrue(\App\Ai\Support\ChatbotToolPolicy::needsConversationContext('colored by diagnosis'));
        $result = $this->pcaResult() + ['metadata_fields' => ['Tissue', 'Diagnosis'], 'color_by' => 'Diagnosis'];
        $preview = (new ToolResultCompactor)->compact($result);
        $this->assertSame(['Tissue', 'Diagnosis'], $preview['metadata_fields']);
        $this->assertSame('Diagnosis', $preview['color_by']);
    }

    public function test_large_plot_is_bounded_with_explicit_sampling_notice(): void
    {
        $result = $this->pcaResult();
        $result['coordinates'] = array_fill(0, 21000, $result['coordinates'][0]);
        $chart = (new PcaPlotPresenter)->present($result, 4096, '3d plot');
        $this->assertTrue($chart['truncated']);
        $this->assertSame(21000, $chart['point_count']);
        $this->assertLessThanOrEqual(4096, strlen(json_encode($chart)));
        $this->assertStringContainsString('subsampled', $chart['summary']);
    }
}
