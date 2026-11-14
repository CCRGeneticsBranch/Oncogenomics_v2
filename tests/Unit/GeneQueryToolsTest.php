<?php

namespace Tests\Unit;

use App\Ai\Support\ChatbotRunContext;
use App\Ai\Support\ChatbotScopeArguments;
use App\Ai\Support\ScopedMcpToolCatalog;
use App\Mcp\Tools\BaseGeneQueryTool;
use App\Mcp\Tools\GetExpGeneSummaryTool;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Tests\TestCase;

class GeneQueryToolsTest extends TestCase
{
    private function tool(string $name = 'getVarGeneSummary', bool $authenticated = true): BaseGeneQueryTool
    {
        return new class($name, $authenticated) extends BaseGeneQueryTool {
            public array $received = [];
            public function __construct(string $name, private bool $authenticated) {
                $this->name = $name;
                $this->patientDetails = str_starts_with($name, 'getPatients');
                $this->fusion = str_contains($name, 'Fusion');
                $this->argumentNames = match ($name) {
                    'getPatientsByVarGene' => ['gene_id', 'type', 'cat_type', 'category', 'tiers'],
                    'getPatientsByCNVGene' => ['gene_id', 'cat_type', 'category', 'min_amplified', 'max_deleted'],
                    'getFusionGeneSummary' => ['gene_id', 'value_type', 'category', 'min_pat', 'fusion_type', 'tiers'],
                    default => ['gene_id', 'value_type', 'category', 'min_pat', 'tiers'],
                };
            }
            protected function isAuthenticated(): bool { return $this->authenticated; }
            protected function fetchData(array $arguments): ?array {
                $this->received = $arguments;
                if ($this->patientDetails) return [['P1', 'RMS', 'Alpha']];
                $plot = ['category' => ['RMS'], 'series' => [['name' => 'Tier 1', 'data' => [['y' => 2, 'raw' => '']]]]];
                return $this->fusion ? $plot : ['somatic' => $plot];
            }
        };
    }

    public function test_gene_only_query_uses_authorized_global_defaults_and_returns_summary_data(): void
    {
        $tool = $this->tool();
        $result = $tool->handle(new Request(['gene_id' => 'alk', 'output' => ['mode' => 'compact']]))->getStructuredContent();
        $this->assertSame('all_authorized', $result['scope']);
        $this->assertSame([['somatic', 'RMS', 'Tier 1', 2]], $result['rows']);
        $this->assertSame(['gene_id' => 'ALK', 'value_type' => 'count', 'category' => 'diagnosis', 'min_pat' => 0, 'tiers' => 'Tier 1,Tier 2'], $tool->received);
        $this->assertSame(['gene_id'], $tool->schemaDefinition()['required']);
    }

    public function test_details_without_category_and_compact_pagination_require_no_cohort(): void
    {
        $tool = $this->tool('getPatientsByVarGene');
        $result = $tool->handle(new Request(['gene_id' => 'ALK', 'output' => ['limit' => 1]]))->getStructuredContent();
        $this->assertSame([['P1', 'RMS', 'Alpha']], $result['rows']);
        $this->assertNull($tool->received['category']);
        $this->assertSame('somatic', $tool->received['type']);
        $summary = $tool->handle(new Request(['gene_id' => 'ALK', 'output' => ['mode' => 'summary']]))->getStructuredContent();
        $this->assertSame(1, $summary['total_rows']);
        $this->assertArrayNotHasKey('rows', $summary);
    }

    public function test_filters_are_forwarded_and_tier_notation_matches_the_legacy_queries(): void
    {
        $tool = $this->tool('getFusionGeneSummary');
        $tool->handle(new Request(['gene_id' => 'alk', 'category' => 'project', 'value_type' => 'frequency', 'min_pat' => 3,
            'tiers' => 'Tier 1,Tier2', 'fusion_type' => 'in-frame']));
        $this->assertSame('Tier1,Tier2', $tool->received['tiers']);
        $this->assertSame('frequency', $tool->received['value_type']);
        $this->assertSame(3, $tool->received['min_pat']);
        $this->assertSame('project', $tool->received['category']);
        $this->assertSame('in-frame', $tool->received['fusion_type']);
    }

    public function test_authentication_and_invalid_thresholds_do_not_read_data(): void
    {
        $tool = $this->tool(authenticated: false);
        $this->assertSame('error', $tool->handle(new Request(['gene_id' => 'ALK']))->getStructuredContent()['status']);
        $this->assertSame([], $tool->received);
        $tool = $this->tool('getPatientsByCNVGene');
        $this->assertSame('error', $tool->handle(new Request(['gene_id' => 'ALK', 'min_amplified' => 1, 'max_deleted' => 2]))->getStructuredContent()['status']);
        $this->assertSame([], $tool->received);
    }

    public function test_gene_sql_fragments_are_rejected_before_querying(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool()->handle(new Request(['gene_id' => "ALK' OR 1=1 --"]));
    }

    public function test_cross_cohort_adapter_cannot_broaden_fixed_scope(): void
    {
        $context = new ChatbotRunContext('project', 42, 'Alpha', 'ALK mutations');
        $tool = $this->tool();
        $adapter = new \App\Ai\Tools\ScopedMcpTool($tool, $context, new \App\Ai\Support\ToolResultCompactor);
        $result = json_decode((string) $adapter->handle(new \Laravel\Ai\Tools\Request(['gene_id' => 'ALK'])), true);
        $this->assertSame('error', $result['status']);
        $this->assertSame([], $tool->received);
    }

    public function test_advertised_wrappers_match_the_shared_model_parameter_order(): void
    {
        foreach (glob(app_path('Mcp/Tools/Get*Tool.php')) as $file) {
            $class = 'App\\Mcp\\Tools\\'.pathinfo($file, PATHINFO_FILENAME);
            if (!is_subclass_of($class, BaseGeneQueryTool::class)) continue;
            $tool = new $class;
            $properties = array_keys($tool->schemaDefinition()['properties']);
            $this->assertNotContains('project_id', $properties);
            $this->assertNotContains('cohort_id', $properties);
            unset($properties[array_search('output', $properties, true)]);
            $method = new \ReflectionMethod(\App\Models\VarAnnotation::class, $tool->name());
            $expected = array_map(fn ($parameter) => $parameter->getName() === 'tier_str' ? 'tiers' : $parameter->getName(), $method->getParameters());
            $this->assertSame($expected, array_values($properties));
        }
    }

    public function test_real_tool_contracts_are_discoverable_only_in_global_chatbot_scope(): void
    {
        $names = ['getExpGeneSummary', 'getVarGeneSummary', 'getCNVGeneSummary', 'getFusionGeneSummary', 'getFusionGenePairSummary',
            'getPatientsByVarGene', 'getPatientsByCNVGene', 'getPatientsByFusionGene', 'getPatientsByFusionPair'];
        $catalog = app(ScopedMcpToolCatalog::class);
        $global = array_map(fn ($t) => $t->name(), $catalog->forContext(new ChatbotRunContext('global', 'all', 'All', 'ALK alterations')));
        foreach (array_diff($names, ['getExpGeneSummary']) as $name) {
            $this->assertContains($name, $global);
            $this->assertSame([], ChatbotScopeArguments::injectedFields($name, 'project'));
            $this->assertSame(['gene_id' => 'ALK'], ChatbotScopeArguments::apply($name,
                ['gene_id' => 'ALK', 'project_id' => 99, 'cohort_type' => 'project', 'cohort_id' => 99], 'global', 'all'));
        }
        $project = array_map(fn ($t) => $t->name(), $catalog->forContext(new ChatbotRunContext('project', 42, 'Alpha', 'ALK alterations')));
        $this->assertSame([], array_values(array_intersect($names, $project)));
        $this->assertSame(['gene_id'], (new GetExpGeneSummaryTool)->schemaDefinition()['required']);
        $this->assertContains('getExpGeneSummary', array_map(fn ($t) => $t->name(), $catalog->forContext(new ChatbotRunContext('global', 'all', 'All', 'ALK expression'))));
    }
}
