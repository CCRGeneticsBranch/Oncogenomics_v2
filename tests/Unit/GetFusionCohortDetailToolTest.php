<?php

namespace Tests\Unit;

use App\Ai\Support\ChatbotRunContext;
use App\Ai\Support\ChatbotScopeArguments;
use App\Ai\Support\ScopedMcpToolCatalog;
use App\Ai\Support\ToolResultCompactor;
use App\Ai\Tools\ScopedMcpTool;
use App\Mcp\Tools\GetFusionCohortDetailTool;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request;
use Tests\TestCase;

class GetFusionCohortDetailToolTest extends TestCase
{
    private function tool(): GetFusionCohortDetailTool
    {
        return new class extends GetFusionCohortDetailTool {
            public array $reads = [];
            protected function isAccessibleProject(int $id): bool { return $id === 42; }
            protected function isAvailableCancerType(string $id): bool { return $id === 'RMS'; }
            protected function fusionPairs(string $type, int|string $id, array $filters): array
            {
                $this->reads[] = [$type, $id, $filters];
                return [['left_chr' => '2', 'left_gene' => 'PAX3', 'right_chr' => '13',
                    'right_gene' => 'FOXO1', 'tier' => '1', 'patient_count' => 3]];
            }
        };
    }

    public function test_both_cohort_types_default_to_two_patients_without_requiring_a_gene(): void
    {
        foreach ([['project', '42', 42], ['cancer_type', 'RMS', 'RMS']] as [$type, $input, $id]) {
            $tool = $this->tool();
            $r = $tool->handle(new Request(['cohort_type' => $type, 'cohort_id' => $input, 'tier' => '1']))
                ->getStructuredContent();
            $this->assertSame('success', $r['status']);
            $this->assertSame('getFusionCohortDetail', $r['action']);
            $this->assertSame($id, $r['cohort_id']);
            $this->assertSame(2, $r['cutoff']);
            $this->assertSame(2, $tool->reads[0][2]['cutoff']);
            $this->assertSame('1', $tool->reads[0][2]['tier']);
            $this->assertSame('distinct_patients', $r['count_unit']);
            $this->assertSame([['2', 'PAX3', '13', 'FOXO1', 'Tier 1', 3]], json_decode($r['table_json'], true)['data']);
            $this->assertStringContainsString('at least 2 distinct patients per pair/tier', $r['summary']);
        }
    }

    public function test_filters_and_explicit_cutoff_survive_compact_output(): void
    {
        $tool = $this->tool();
        $r = $tool->handle(new Request([
            'cohort_type' => 'project', 'cohort_id' => 42,
            'gene' => 'FOXO1', 'partner' => 'PAX3', 'caller' => 'Arriba',
            'tier' => '1', 'diagnosis' => 'RMS', 'cutoff' => 1,
            'output' => ['mode' => 'compact', 'limit' => 1],
        ]))->getStructuredContent();
        $this->assertSame('success', $r['status']);
        $this->assertSame(1, $r['cutoff']);
        $this->assertSame('Arriba', $tool->reads[0][2]['caller']);
        $this->assertSame('RMS', $tool->reads[0][2]['diagnosis']);
        $this->assertSame('PAX3', $tool->reads[0][2]['partner']);
        $this->assertSame(1, $r['total_rows']);
        $this->assertCount(1, $r['rows']);
    }

    public function test_invalid_cohorts_and_partner_without_gene_do_not_query(): void
    {
        foreach ([
            ['cohort_type' => 'project', 'cohort_id' => 99],
            ['cohort_type' => 'project', 'cohort_id' => 'RMS'],
            ['cohort_type' => 'cancer_type', 'cohort_id' => 'RMS subtype'],
            ['cohort_type' => 'cancer_type', 'cohort_id' => 'RMS', 'diagnosis' => 'Other'],
            ['cohort_type' => 'project', 'cohort_id' => 42, 'partner' => 'FOXO1'],
        ] as $input) {
            $tool = $this->tool();
            $this->assertSame('error', $tool->handle(new Request($input))->getStructuredContent()['status']);
            $this->assertSame([], $tool->reads);
        }
    }

    public function test_zero_cutoff_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool()->handle(new Request(['cohort_type' => 'project', 'cohort_id' => 42, 'cutoff' => 0]));
    }

    public function test_fixed_scope_overrides_model_cohort_and_tool_is_available_everywhere(): void
    {
        foreach ([['project', 42], ['cancer_type', 'RMS']] as [$scope, $id]) {
            $tool = $this->tool();
            $adapter = new ScopedMcpTool($tool,
                new ChatbotRunContext($scope, $id, 'Test cohort', 'which Tier 1 fusion genes?'),
                new ToolResultCompactor);
            $adapter->handle(new AiRequest([
                'cohort_type' => 'project', 'cohort_id' => 99, 'project_id' => 99,
                'tier' => '1',
            ]));
            $this->assertSame($scope, $tool->reads[0][0]);
            $this->assertSame($id, $tool->reads[0][1]);
        }
        foreach (['project', 'cancer_type', 'global'] as $scope) {
            $tools = app(ScopedMcpToolCatalog::class)->forContext(
                new ChatbotRunContext($scope, $scope === 'project' ? 42 : 'RMS', 'Test', 'Tier 1 fusion genes')
            );
            $this->assertContains('getFusionCohortDetail', array_map(fn ($tool) => $tool->name(), $tools));
        }
        $this->assertSame(['cohort_type', 'cohort_id'], ChatbotScopeArguments::injectedFields('getFusionCohortDetail', 'project'));
    }
}
