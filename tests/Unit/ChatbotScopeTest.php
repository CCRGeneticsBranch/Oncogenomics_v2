<?php

namespace Tests\Unit;

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ProjectController;
use App\Http\Middleware\McpTokenAuth;
use App\Mcp\Servers\OncoServer;
use App\Mcp\Tools\GetProjectsTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ChatbotScopeTest extends TestCase
{
    public function test_full_page_chatbot_view_preserves_global_context(): void
    {
        $this->mockLoggedInUser();
        $response = (new ChatbotController)->view(Request::create('/viewChatbot', 'GET', [
            'scope' => 'global',
            'cohort_id' => 'all',
        ]));

        $this->assertSame('pages.viewChatbot', $response->name());
        $this->assertSame('global', $response->getData()['chatbot_scope']);
        $this->assertSame('all', $response->getData()['chatbot_cohort_id']);
        $this->assertSame('', $response->getData()['chatbot_query']);
        $this->assertNotEmpty($response->getData()['chatbot_conversation_id']);

        $source = (string) file_get_contents(resource_path('views/pages/viewChatbot.blade.php'));
        $this->assertStringContainsString("@section('title', 'OncoGenomics Chatbot')", $source);
        $this->assertStringContainsString('>OncoGenomics Chatbot</h1>', $source);

        $this->assertStringContainsString(
            "strtolower((string) \$chatbot_scope) !== 'global'",
            $source,
        );
        $this->assertStringNotContainsString(
            "scope · {{ \$chatbot_context_name }}",
            $source,
        );
    }

    public function test_embedded_chatbot_removes_chrome_and_preserves_embedded_navigation(): void
    {
        $this->mockLoggedInUser();
        $response = (new ChatbotController)->view(Request::create('/viewChatbot', 'GET', [
            'scope' => 'global',
            'cohort_id' => 'all',
            'embedded' => 1,
        ]));

        $this->assertTrue($response->getData()['chatbot_embedded']);
        $this->assertStringContainsString('embedded=1', $response->getData()['chatbot_conversation_url']);
        $this->assertStringContainsString('embedded=1', $response->getData()['chatbot_new_url']);
        $this->assertStringContainsString('scope=global', $response->getData()['chatbot_new_url']);
        $this->assertStringContainsString('cohort_id=all', $response->getData()['chatbot_new_url']);
        $this->assertStringContainsString('new=1', $response->getData()['chatbot_new_url']);

        $html = $response->render();
        $this->assertStringNotContainsString('<header class="clinomics-chat-header">', $html);
        $this->assertStringNotContainsString('site-footer', $html);
        $this->assertStringContainsString('clinomics-chat-page is-embedded', $html);
        $this->assertStringContainsString('<title>OncoGenomics Chatbot</title>', $html);
        $this->assertStringContainsString('aria-label="OncoGenomics chatbot conversation"', $html);
        $this->assertStringContainsString('aria-label="Conversation actions"', $html);
        $this->assertStringContainsString('aria-label="Start a new conversation"', $html);
        $this->assertSame(1, substr_count($html, '>New chat</a>'));
        $this->assertSame(1, substr_count($html, 'id="chat_query"'));
    }

    public function test_project_details_uses_only_the_embedded_chatbot_composer(): void
    {
        $source = (string) file_get_contents(resource_path('views/pages/viewProjectDetails.blade.php'));

        $this->assertStringNotContainsString('id="chatbot_query"', $source);
        $this->assertStringNotContainsString('id="btnRunChatbotQuery"', $source);
        $this->assertStringNotContainsString('runChatbotQuery()', $source);
        $this->assertStringContainsString("'embedded' => 1", $source);
        $this->assertStringContainsString('src="{{ $embeddedChatbotUrl }}"', $source);
    }

    public function test_submitted_home_query_opens_the_live_conversation_page(): void
    {
        $this->mockLoggedInUser();
        $controller = new ChatbotController;
        $existing = $controller->view(Request::create('/viewChatbot', 'GET', [
            'scope' => 'global',
            'cohort_id' => 'all',
        ]));
        $response = $controller->view(Request::create('/viewChatbot', 'POST', [
            'scope' => 'global',
            'cohort_id' => 'all',
            'new' => 1,
            'query' => 'show ChIP-seq targeting MYCN in NB',
        ]));

        $this->assertSame('pages.viewChatbot', $response->name());
        $this->assertNotSame(
            $existing->getData()['chatbot_conversation_id'],
            $response->getData()['chatbot_conversation_id']
        );
        $this->assertSame(
            'show ChIP-seq targeting MYCN in NB',
            $response->getData()['chatbot_query']
        );
        $this->assertStringContainsString(
            '/chatbot/conversations/',
            $response->getData()['chatbot_stream_url']
        );

        $route = collect(Route::getRoutes()->getRoutes())->first(
            static fn ($route): bool => $route->uri() === 'viewChatbot'
        );
        $this->assertContains('POST', $route->methods());

        $homeSource = (string) file_get_contents(resource_path('views/pages/viewHome.blade.php'));
        $this->assertStringContainsString('name="new" value="1"', $homeSource);
    }

    public function test_general_chatbot_routes_do_not_require_a_project_route_parameter(): void
    {
        foreach (['viewChatbot', 'chatbot/conversations/{conversation_id}/messages', 'runChatbot/{scope}/{cohort_id}/{query}'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())->first(
                static fn ($route): bool => $route->uri() === $uri
            );

            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('logged', $middleware);
            $this->assertContains('can_see', $middleware);
            $this->assertNotContains('authorized_project', $middleware);
        }
    }

    public function test_legacy_internal_mcp_request_delegates_the_logged_in_user(): void
    {
        config()->set('mcp_auth.internal_token', 'internal-secret');
        $user = (object) ['id' => 3803];
        $auth = \Mockery::mock('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface');
        $auth->shouldReceive('getLoggedUser')->once()->andReturn($user);
        $this->app->instance('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface', $auth);

        $request = $this->invoke('mcpAuthorizedRequest', [20]);
        $headers = $request->getOptions()['headers'];

        $this->assertSame('Bearer internal-secret', $headers['Authorization']);
        $this->assertSame('3803', $headers[McpTokenAuth::INTERNAL_USER_ID_HEADER]);
    }

    public function test_global_scope_exposes_project_tools_while_fixed_cancer_type_scope_does_not(): void
    {
        $this->assertContains('getPCAData', config('chatbot.scope_tools.project'));
        $this->assertNotContains('getPCAData', config('chatbot.scope_tools.cancer_type'));
        $this->assertContains('getPCAData', config('chatbot.scope_tools.global'));
        $this->assertContains('getCohortSamples', config('chatbot.scope_tools.global'));
        $this->assertContains('getFusionCancerTypeDetail', config('chatbot.scope_tools.global'));
    }

    public function test_live_mcp_catalog_is_filtered_by_scope_case_insensitively(): void
    {
        $tools = [
            ['name' => 'getPCAData'],
            ['name' => 'getCohortChIPseq'],
            ['name' => 'getCohortExpression'],
            ['name' => 'getCancerTypes'],
        ];

        $project = $this->invoke('filterMcpToolsForChatbotScope', [$tools, 'project']);
        $cancerType = $this->invoke('filterMcpToolsForChatbotScope', [$tools, 'cancer_type']);
        $global = $this->invoke('filterMcpToolsForChatbotScope', [$tools, 'global']);

        $this->assertSame(['getPCAData', 'getCohortChIPseq'], array_column($project, 'name'));
        $this->assertSame(['getCohortChIPseq', 'getCohortExpression'], array_column($cancerType, 'name'));
        $this->assertSame(
            ['getPCAData', 'getCohortChIPseq', 'getCohortExpression', 'getCancerTypes'],
            array_column($global, 'name'),
        );
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

    public function test_global_context_preserves_resolved_context_for_cohort_tools(): void
    {
        $arguments = $this->invoke('applyChatbotScopeArguments', [
            'getCohortChIPseq',
            ['cohort_type' => 'cancer_type', 'cohort_id' => 'Neuroblastoma', 'target' => 'MYCN'],
            'global',
            'all',
        ]);

        $this->assertSame('cancer_type', $arguments['cohort_type']);
        $this->assertSame('Neuroblastoma', $arguments['cohort_id']);
        $this->assertSame('MYCN', $arguments['target']);
    }

    public function test_global_context_preserves_resolver_derived_project_id_for_project_tools(): void
    {
        $arguments = $this->invoke('applyChatbotScopeArguments', [
            'getPCAData',
            ['project_id' => 24421, 'cohort_type' => 'project', 'cohort_id' => 999],
            'global',
            'all',
        ]);

        $this->assertSame(24421, $arguments['project_id']);
        $this->assertArrayNotHasKey('cohort_type', $arguments);
        $this->assertArrayNotHasKey('cohort_id', $arguments);
    }

    public function test_global_context_canonicalizes_the_nb_cancer_type_alias(): void
    {
        $arguments = $this->invoke('applyChatbotScopeArguments', [
            'getCohortChIPseq',
            ['cohort_type' => 'cancer_type', 'cohort_id' => 'NB', 'target' => 'MYCN'],
            'global',
            'all',
        ]);

        $this->assertSame('cancer_type', $arguments['cohort_type']);
        $this->assertSame('Neuroblastoma', $arguments['cohort_id']);
        $this->assertSame('MYCN', $arguments['target']);
    }

    public function test_global_context_preserves_expression_gene_and_canonicalizes_nb(): void
    {
        $arguments = $this->invoke('applyChatbotScopeArguments', [
            'getCohortExpression',
            ['cohort_type' => 'cancer_type', 'cohort_id' => 'NB', 'gene' => 'FGFR4'],
            'global',
            'all',
        ]);

        $this->assertSame('cancer_type', $arguments['cohort_type']);
        $this->assertSame('Neuroblastoma', $arguments['cohort_id']);
        $this->assertSame('FGFR4', $arguments['gene']);
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
            $tool = new $toolClass;
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

        $globalTools = config('chatbot.scope_tools.global');
        sort($globalTools);
        sort($advertisedNames);
        $this->assertSame($advertisedNames, $globalTools, 'The global chatbot must expose every advertised Onco MCP tool.');
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

        return $reflection->invokeArgs(new ProjectController, $arguments);
    }

    private function mockLoggedInUser(int $userId = 3803): void
    {
        $auth = \Mockery::mock('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface');
        $auth->shouldReceive('getLoggedUser')->zeroOrMoreTimes()->andReturn((object) ['id' => $userId]);
        $this->app->instance('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface', $auth);
    }
}
