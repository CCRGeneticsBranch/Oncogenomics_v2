<?php

namespace Tests\Unit;

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ProjectController;
use Illuminate\Http\Request;
use App\Mcp\Servers\OncoServer;
use App\Mcp\Tools\GetProjectsTool;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ChatbotScopeTest extends TestCase
{
    public function test_full_page_chatbot_view_preserves_global_query_context(): void
    {
        $response = (new ChatbotController())->view(Request::create('/viewChatbot', 'GET', [
            'scope' => 'global',
            'cohort_id' => 'all',
            'query' => 'show available projects',
        ]));

        $this->assertSame('pages.viewChatbot', $response->name());
        $this->assertSame('global', $response->getData()['chatbot_scope']);
        $this->assertSame('all', $response->getData()['chatbot_cohort_id']);
        $this->assertSame('show available projects', $response->getData()['chatbot_query']);
    }

    public function test_project_scope_exposes_pca_but_other_scopes_do_not(): void
    {
        $this->assertContains('getPCAData', config('chatbot.scope_tools.project'));
        $this->assertNotContains('getPCAData', config('chatbot.scope_tools.cancer_type'));
        $this->assertNotContains('getPCAData', config('chatbot.scope_tools.global'));
    }

    public function test_live_mcp_catalog_is_filtered_by_scope_case_insensitively(): void
    {
        $tools = [
            ['name' => 'getPCAData'],
            ['name' => 'getCohortChIPseq'],
            ['name' => 'getCancerTypes'],
        ];

        $project = $this->invoke('filterMcpToolsForChatbotScope', [$tools, 'project']);
        $cancerType = $this->invoke('filterMcpToolsForChatbotScope', [$tools, 'cancer_type']);
        $global = $this->invoke('filterMcpToolsForChatbotScope', [$tools, 'global']);

        $this->assertSame(['getPCAData', 'getCohortChIPseq'], array_column($project, 'name'));
        $this->assertSame(['getCohortChIPseq'], array_column($cancerType, 'name'));
        $this->assertSame(['getCancerTypes'], array_column($global, 'name'));
    }

    public function test_project_context_is_injected_into_project_and_cohort_tools(): void
    {
        $projectArgs = $this->invoke('applyChatbotScopeArguments', [
            'getPCAData', ['project_id' => 1], 'project', 25062,
        ]);
        $cohortArgs = $this->invoke('applyChatbotScopeArguments', [
            'getCohortSamples', ['cohort_id' => 'wrong', 'exp_type' => 'RNAseq'], 'project', 25062,
        ]);

        $this->assertSame(25062, $projectArgs['project_id']);
        $this->assertSame('project', $cohortArgs['cohort_type']);
        $this->assertSame(25062, $cohortArgs['cohort_id']);
        $this->assertArrayNotHasKey('project_id', $cohortArgs);
    }

    public function test_cancer_type_context_overrides_llm_supplied_context(): void
    {
        $arguments = $this->invoke('applyChatbotScopeArguments', [
            'getCohortChIPseq',
            ['project_id' => 25062, 'cohort_type' => 'project', 'cohort_id' => 25062],
            'cancer_type',
            'Neuroblastoma',
        ]);

        $this->assertSame('cancer_type', $arguments['cohort_type']);
        $this->assertSame('Neuroblastoma', $arguments['cohort_id']);
        $this->assertArrayNotHasKey('project_id', $arguments);
    }

    public function test_global_context_removes_invented_cohort_arguments(): void
    {
        $arguments = $this->invoke('applyChatbotScopeArguments', [
            'getProjects',
            ['project_id' => 25062, 'cohort_type' => 'project', 'cohort_id' => 25062],
            'global',
            'all',
        ]);

        $this->assertSame([], $arguments);
    }

    public function test_every_scoped_tool_is_advertised_by_the_onco_server(): void
    {
        $serverReflection = new ReflectionClass(OncoServer::class);
        $server = $serverReflection->newInstanceWithoutConstructor();
        $boot = new ReflectionMethod(OncoServer::class, 'boot');
        $boot->setAccessible(true);
        $boot->invoke($server);
        $toolsProperty = new ReflectionProperty(OncoServer::class, 'tools');
        $toolsProperty->setAccessible(true);

        $advertisedNames = [];
        foreach ($toolsProperty->getValue($server) as $toolClass) {
            $tool = new $toolClass();
            $nameProperty = new ReflectionProperty(Tool::class, 'name');
            $nameProperty->setAccessible(true);
            $advertisedNames[] = $nameProperty->getValue($tool);
        }

        foreach (config('chatbot.scope_tools') as $scope => $configuredTools) {
            foreach ($configuredTools as $configuredTool) {
                $this->assertContains(
                    $configuredTool,
                    $advertisedNames,
                    "Tool {$configuredTool} configured for {$scope} is not advertised by OncoServer."
                );
            }
        }
    }

    public function test_all_onco_tools_fit_on_the_initial_mcp_discovery_page(): void
    {
        $serverReflection = new ReflectionClass(OncoServer::class);
        $server = $serverReflection->newInstanceWithoutConstructor();
        $boot = new ReflectionMethod(OncoServer::class, 'boot');
        $boot->setAccessible(true);
        $boot->invoke($server);
        $toolsProperty = new ReflectionProperty(OncoServer::class, 'tools');
        $toolsProperty->setAccessible(true);

        $advertisedTools = $toolsProperty->getValue($server);

        $this->assertLessThanOrEqual(
            $server->defaultPaginationLength,
            count($advertisedTools),
            'Every Onco tool must fit on the initial tools/list page for clients that do not follow nextCursor.'
        );
        $this->assertContains(GetProjectsTool::class, $advertisedTools);
    }

    private function invoke(string $method, array $arguments)
    {
        $reflection = new ReflectionMethod(ProjectController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(new ProjectController(), $arguments);
    }
}
