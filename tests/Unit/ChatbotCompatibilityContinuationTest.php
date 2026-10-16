<?php

namespace Tests\Unit;

use App\Http\Controllers\ProjectController;
use App\Services\ExpressionPlotPresenter;
use Tests\TestCase;

class ChatbotCompatibilityContinuationTest extends TestCase
{
    public function test_project_resolution_continues_to_the_requested_plot(): void
    {
        $controller = $this->controller();
        $result = $controller->runQuery();

        $this->assertSame('getCohortExpression', $result['action']);
        $chart = (new ExpressionPlotPresenter)->present($result, null,
            'Show me the violin plot of FGFR4 in RNAseq landscape project');
        $this->assertNotNull($chart);
        $this->assertSame('violin', $chart['data'][0]['type']);
        $this->assertCount(2, $controller->queries);
        $this->assertStringContainsString('RNAseq landscape', $controller->queries[1]);
        $this->assertStringContainsString('"project_id":42', $controller->queries[1]);
        $this->assertSame(['getProjects', 'getCohortExpression'], $controller->executed);
    }

    public function test_unresolved_request_does_not_loop_or_execute_another_resolver(): void
    {
        $controller = $this->controller();
        $controller->repeatResolver = true;
        $result = $controller->runQuery();

        $this->assertSame('getProjects', $result['action']);
        $this->assertCount(2, $controller->queries);
        $this->assertSame(['getProjects'], $controller->executed);
    }

    private function controller(): ProjectController
    {
        return new class extends ProjectController {
            public array $queries = [];
            public array $executed = [];
            public bool $repeatResolver = false;

            public function runQuery(): ?array
            {
                return $this->runMcpWithLlmToolSelection('all', 'Show me the violin plot of FGFR4 in RNAseq landscape project', 'global');
            }

            protected function mcpInitialize($mcpUrl) { return 'test-session'; }
            protected function callMcpToolsList($mcpUrl, $sessionId)
            {
                return [['name' => 'getProjects'], ['name' => 'getCohortExpression']];
            }
            protected function selectToolsByLlm($query, $tools, $cohort_id, $scope = 'project')
            {
                $this->queries[] = $query;
                return [['tool_name' => count($this->queries) === 1 || $this->repeatResolver
                    ? 'getProjects' : 'getCohortExpression', 'arguments' => []]];
            }
            protected function executeLlmToolSelection($mcpUrl, $sessionId, $cohort_id, $query, $selection, $scope = 'project')
            {
                $this->executed[] = $selection['tool_name'];
                return $selection['tool_name'] === 'getProjects'
                    ? ['status' => 'success', 'action' => 'getProjects', 'projects' => [
                        ['project_id' => 42, 'project_name' => 'RNAseq landscape'],
                    ]]
                    : ['status' => 'success', 'action' => 'getCohortExpression',
                        'gene' => 'FGFR4', 'cohort_name' => 'RNAseq landscape',
                        'rows' => [['sample_id' => 's1', 'tpm' => 2], ['sample_id' => 's2', 'tpm' => 8]],
                    ];
            }
        };
    }
}
