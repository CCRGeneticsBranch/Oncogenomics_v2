<?php

namespace Tests\Unit;

use App\Ai\Agents\ClinomicsChatAgent;
use App\Ai\Agents\ClinomicsResultSummarizer;
use App\Ai\Support\ChatbotRunContext;
use App\Ai\Support\ChatbotToolPolicy;
use App\Ai\Support\ExplicitChatbotCohort;
use App\Ai\Support\ScopedMcpToolCatalog;
use App\Ai\Support\ToolResultCompactor;
use App\Ai\Tools\ScopedMcpTool;
use App\Services\ClinomicsChatAgentRunner;
use App\Services\ExpressionPlotPresenter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Streaming\Events\Error as StreamError;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool as McpTool;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class LaravelAiChatbotTest extends TestCase
{
    public function test_scoped_adapter_overrides_model_supplied_project_context(): void
    {
        $context = new ChatbotRunContext('project', 25062, 'RNA landscape', 'show PCA');
        $tool = new class extends McpTool
        {
            protected string $name = 'getPCAData';

            public function handle(McpRequest $request): ResponseFactory
            {
                return Response::structured([
                    'status' => 'success',
                    'received' => $request->all(),
                ]);
            }
        };
        $adapter = new ScopedMcpTool($tool, $context, new ToolResultCompactor);

        $decoded = json_decode((string) $adapter->handle(new AiRequest([
            'project_id' => 1,
            'value_type' => 'zscore',
        ])), true);

        $this->assertSame(25062, $decoded['received']['project_id']);
        $this->assertSame('zscore', $decoded['received']['value_type']);
        $this->assertSame(25062, $context->executions()[0]['arguments']['project_id']);
    }

    public function test_scoped_catalog_only_exposes_tools_allowed_for_that_scope(): void
    {
        $context = new ChatbotRunContext('global', 'all', 'All accessible cohorts', 'show projects');
        $names = array_map(
            static fn (ScopedMcpTool $tool): string => $tool->name(),
            app(ScopedMcpToolCatalog::class)->forContext($context)
        );

        $expected = config('chatbot.scope_tools.global');
        sort($names);
        sort($expected);
        $this->assertSame($expected, $names);
        $this->assertContains('getCohortSamples', $names);
        $this->assertContains('getPCAData', $names);
    }

    public function test_global_agent_requires_unique_cohort_resolution_or_specific_clarification(): void
    {
        $agent = new ClinomicsChatAgent(
            new ChatbotRunContext('global', 'all', 'All accessible cohorts', 'show all RNAseq samples'),
            [],
            new ClinomicsResultSummarizer('gemini', 'test-model', 10),
            'gemini',
            'test-model',
            10,
            4,
            0.0,
        );
        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Before calling any non-resolver data tool', $instructions);
        $this->assertStringContainsString('one unique exact authorized match', $instructions);
        $this->assertStringContainsString('ask the user specifically which project or cancer type', $instructions);
        $this->assertStringNotContainsString('Neuroblastoma', $instructions);
    }

    public function test_agent_adapter_canonicalizes_the_nb_cancer_type_alias(): void
    {
        $context = new ChatbotRunContext('global', 'all', 'All accessible cohorts', 'show MYCN ChIP-seq in NB');
        $tool = new class extends McpTool
        {
            protected string $name = 'getCohortChIPseq';
        };
        $adapter = new ScopedMcpTool($tool, $context, new ToolResultCompactor);

        $arguments = $adapter->applyScope([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'NB',
            'target' => 'MYCN',
        ]);

        $this->assertSame('cancer_type', $arguments['cohort_type']);
        $this->assertSame('Neuroblastoma', $arguments['cohort_id']);
        $this->assertSame('MYCN', $arguments['target']);
    }

    public function test_agent_adapter_canonicalizes_nb_for_cohort_expression(): void
    {
        $context = new ChatbotRunContext('global', 'all', 'All accessible cohorts', 'show FGFR4 expression in NB');
        $tool = new class extends McpTool
        {
            protected string $name = 'getCohortExpression';
        };
        $adapter = new ScopedMcpTool($tool, $context, new ToolResultCompactor);

        $arguments = $adapter->applyScope([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'NB',
            'gene' => 'FGFR4',
        ]);

        $this->assertSame('cancer_type', $arguments['cohort_type']);
        $this->assertSame('Neuroblastoma', $arguments['cohort_id']);
        $this->assertSame('FGFR4', $arguments['gene']);
    }

    public function test_explicit_rnaseq_landscape_query_cannot_be_routed_to_neuroblastoma(): void
    {
        $query = 'Show me FGFR4 log2 tpm violin plot in RNAseq landscape group by diagnosis';
        $project = ExplicitChatbotCohort::projectFromUserQuery($query);
        $this->assertSame(['id' => 24421, 'name' => 'RNAseq_Landscape_Manuscript'], $project);

        $agentQuery = ExplicitChatbotCohort::appendProjectContext($query, $project);
        $context = new ChatbotRunContext('global', 'all', 'All accessible cohorts', $agentQuery);
        $tool = new class extends McpTool
        {
            protected string $name = 'getCohortExpression';
        };
        $adapter = new ScopedMcpTool($tool, $context, new ToolResultCompactor);
        $arguments = $adapter->applyScope([
            'cohort_type' => 'cancer_type',
            'cohort_id' => 'Neuroblastoma',
            'gene' => 'FGFR4',
        ]);

        $this->assertSame('project', $arguments['cohort_type']);
        $this->assertSame(24421, $arguments['cohort_id']);
        $this->assertSame('violin', $arguments['plot_type']);
        $this->assertSame('log2p1', $arguments['transform']);
        $this->assertSame('diagnosis', $arguments['group_by']);
        $this->assertFalse(ChatbotToolPolicy::allows('getCancerTypes', $agentQuery));

        $names = array_map(
            static fn (ScopedMcpTool $scopedTool): string => $scopedTool->name(),
            app(ScopedMcpToolCatalog::class)->forContext($context),
        );
        $this->assertNotContains('getCancerTypes', $names);
        $this->assertContains('getProjects', $names);
        $this->assertContains('getCohortExpression', $names);

        $projectTool = new class extends McpTool
        {
            protected string $name = 'expression_by_gene';
        };
        $projectAdapter = new ScopedMcpTool($projectTool, $context, new ToolResultCompactor);
        $projectArguments = $projectAdapter->applyScope([
            'project_id' => 999,
            'gene' => 'FGFR4',
        ]);
        $this->assertSame(24421, $projectArguments['project_id']);
    }

    public function test_scoped_adapter_replays_only_the_exact_completed_invocation(): void
    {
        $firstContext = new ChatbotRunContext('global', 'all', 'Clinomics', 'resolve cohorts');
        $tool = new class extends McpTool
        {
            protected string $name = 'fakeResolver';

            public int $invocations = 0;

            public function handle(McpRequest $request): ResponseFactory
            {
                $this->invocations++;

                return Response::structured([
                    'status' => 'success',
                    'term' => $request->get('term'),
                ]);
            }
        };
        $firstAdapter = new ScopedMcpTool($tool, $firstContext, new ToolResultCompactor);
        $firstAdapter->handle(new AiRequest(['term' => 'NB']));
        $execution = $firstContext->executions()[0];
        $cache = [
            ScopedMcpTool::invocationKey($execution['arguments']) => $execution['result'],
        ];

        $secondContext = new ChatbotRunContext('global', 'all', 'Clinomics', 'continue');
        $replayingAdapter = (new ScopedMcpTool(
            $tool,
            $secondContext,
            new ToolResultCompactor,
        ))->withReplayResults($cache);
        $replayed = json_decode((string) $replayingAdapter->handle(
            new AiRequest(['term' => 'NB']),
        ), true, 512, JSON_THROW_ON_ERROR);
        $newResult = json_decode((string) $replayingAdapter->handle(
            new AiRequest(['term' => 'ARMS']),
        ), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(2, $tool->invocations);
        $this->assertSame('NB', $replayed['term']);
        $this->assertSame('ARMS', $newResult['term']);
        $this->assertCount(1, $secondContext->executions());
        $this->assertSame('ARMS', $secondContext->executions()[0]['arguments']['term']);
    }

    public function test_laravel_ai_executes_an_mcp_adapter_during_the_agent_loop(): void
    {
        $context = new ChatbotRunContext('global', 'all', 'All accessible cohorts', 'resolve cohort');
        $tool = new class extends McpTool
        {
            protected string $name = 'fakeResolver';

            public function handle(McpRequest $request): ResponseFactory
            {
                return Response::structured([
                    'status' => 'success',
                    'term' => $request->get('term'),
                ]);
            }
        };
        $adapter = new ScopedMcpTool($tool, $context, new ToolResultCompactor);
        $summarizer = new ClinomicsResultSummarizer('gemini', 'test-model', 30);
        $agent = new ClinomicsChatAgent(
            $context,
            [$adapter],
            $summarizer,
            'gemini',
            'test-model',
            30,
            8,
            0,
        );

        ClinomicsChatAgent::fake([
            new ToolCall('call-1', 'fakeResolver', ['term' => 'Compass']),
            'Compass was resolved.',
        ]);

        $response = $agent->prompt('Resolve Compass.');

        $this->assertSame('Compass was resolved.', $response->text);
        $this->assertCount(1, $context->executions());
        $this->assertSame('Compass', $context->executions()[0]['result']['term']);
        $this->assertTrue(collect($agent->tools())->contains(
            static fn (mixed $available): bool => $available instanceof ClinomicsResultSummarizer
        ));
    }

    public function test_agent_accepts_prior_user_and_assistant_messages(): void
    {
        $context = new ChatbotRunContext('global', 'all', 'Clinomics', 'follow up');
        $summarizer = new ClinomicsResultSummarizer('gemini', 'test-model', 30);
        $history = [new UserMessage('first question'), new AssistantMessage('first answer')];
        $agent = new ClinomicsChatAgent(
            $context,
            [],
            $summarizer,
            'gemini',
            'test-model',
            30,
            8,
            0,
            $history,
        );

        $this->assertInstanceOf(Conversational::class, $agent);
        $this->assertSame($history, $agent->messages());
    }

    public function test_runner_configures_groq_then_gemini_failover_for_agent_and_summarizer(): void
    {
        config()->set('chatbot.agent.providers', [
            'groq' => 'llama-test',
            'gemini' => 'gemini-test',
        ]);
        config()->set('ai.providers.groq.key', 'groq-test-key');
        config()->set('ai.providers.gemini.key', 'gemini-test-key');

        $runner = app(ClinomicsChatAgentRunner::class);
        $method = new ReflectionMethod($runner, 'prepare');
        [$agent, , $providers] = $method->invoke(
            $runner,
            'global',
            'all',
            'Clinomics',
            'show projects',
            [],
        );
        $summarizer = collect($agent->tools())->first(
            static fn (mixed $tool): bool => $tool instanceof ClinomicsResultSummarizer,
        );

        $this->assertSame([
            'groq' => 'llama-test',
            'gemini' => 'gemini-test',
        ], $providers);
        $this->assertSame($providers, $agent->provider());
        $this->assertNull($agent->model());
        $this->assertInstanceOf(ClinomicsResultSummarizer::class, $summarizer);
        $this->assertSame($providers, $summarizer->provider());
        $this->assertNull($summarizer->model());
    }

    public function test_streaming_runner_fails_over_from_groq_rate_limit_to_gemini(): void
    {
        config()->set('chatbot.agent.providers', [
            'groq' => 'llama-test',
            'gemini' => 'gemini-test',
        ]);
        config()->set('ai.providers.groq.key', 'groq-test-key');
        config()->set('ai.providers.groq.url', 'https://groq.test/v1');
        config()->set('ai.providers.gemini.key', 'gemini-test-key');
        config()->set('ai.providers.gemini.url', 'https://gemini.test/v1beta/');
        Event::fake([AgentFailedOver::class]);
        Http::fake([
            'https://groq.test/*' => Http::response([
                'error' => ['message' => 'rate limited'],
            ], 429),
            'https://gemini.test/*' => Http::response(
                "data: {\"modelVersion\":\"gemini-test\",\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Gemini fallback answer.\"}]},\"finishReason\":\"STOP\"}],\"usageMetadata\":{\"promptTokenCount\":1,\"candidatesTokenCount\":2}}\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $events = iterator_to_array(app(ClinomicsChatAgentRunner::class)->stream(
            'global',
            'all',
            'Clinomics',
            'show projects',
        ));
        $meta = collect($events)->firstWhere('type', 'meta');
        $complete = collect($events)->firstWhere('type', 'complete');

        $this->assertSame('gemini', $meta['provider']);
        $this->assertSame('gemini-test', $meta['model']);
        $this->assertSame('Gemini fallback answer.', $complete['answer']);
        $this->assertSame('gemini', $complete['provider']);
        Event::assertDispatched(AgentFailedOver::class);
        Http::assertSentCount(2);
    }

    public function test_global_cohort_tool_schema_is_valid_for_gemini(): void
    {
        config()->set('chatbot.agent.providers', ['gemini' => 'gemini-test']);
        config()->set('ai.providers.gemini.key', 'gemini-test-key');
        config()->set('ai.providers.gemini.url', 'https://gemini.test/v1beta/');
        $requestBody = null;
        Http::fake(function (Request $request) use (&$requestBody) {
            $requestBody = $request->data();

            return Http::response(
                "data: {\"modelVersion\":\"gemini-test\",\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Schema accepted.\"}]},\"finishReason\":\"STOP\"}]}\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            );
        });

        iterator_to_array(app(ClinomicsChatAgentRunner::class)->stream(
            'global',
            'all',
            'Clinomics',
            'Compare FGFR4 expression with ChIP-seq data in Neuroblastoma.',
        ));

        $declarations = data_get($requestBody, 'tools.0.function_declarations', []);
        $cohortTool = collect($declarations)->firstWhere('name', 'getCohortChIPseq');
        $expressionTool = collect($declarations)->firstWhere('name', 'getCohortExpression');

        $this->assertIsArray($cohortTool);
        $this->assertSame('string', data_get($cohortTool, 'parameters.properties.cohort_id.type'));
        $this->assertContains('cohort_id', data_get($cohortTool, 'parameters.required', []));
        $this->assertIsArray($expressionTool);
        $this->assertSame('string', data_get($expressionTool, 'parameters.properties.cohort_id.type'));
        $this->assertContains('gene', data_get($expressionTool, 'parameters.required', []));
    }

    public function test_nested_project_tool_schemas_are_valid_for_gemini(): void
    {
        config()->set('chatbot.agent.providers', ['gemini' => 'gemini-test']);
        config()->set('ai.providers.gemini.key', 'gemini-test-key');
        config()->set('ai.providers.gemini.url', 'https://gemini.test/v1beta/');
        $requestBody = null;
        Http::fake(function (Request $request) use (&$requestBody) {
            $requestBody = $request->data();

            return Http::response(
                "data: {\"modelVersion\":\"gemini-test\",\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Schema accepted.\"}]},\"finishReason\":\"STOP\"}]}\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            );
        });

        iterator_to_array(app(ClinomicsChatAgentRunner::class)->stream(
            'project',
            22112,
            'Clinomics',
            'Group the log2 FGFR4 TPM expression by diagnosis.',
        ));

        $declarations = data_get($requestBody, 'tools.0.function_declarations', []);
        $differentialExpression = collect($declarations)->firstWhere('name', 'runDifferentialExpression');

        $this->assertIsArray($differentialExpression);
        $this->assertSame(
            'object',
            data_get($differentialExpression, 'parameters.properties.group_a.type'),
        );
        $this->assertStringNotContainsString(
            'additionalProperties',
            json_encode($declarations, JSON_THROW_ON_ERROR),
        );
    }

    public function test_streaming_runner_hands_off_after_a_completed_tool_without_rerunning_it(): void
    {
        config()->set('chatbot.agent.providers', [
            'groq' => 'llama-test',
            'gemini' => 'gemini-test',
        ]);
        config()->set('ai.providers.groq.key', 'groq-test-key');
        config()->set('ai.providers.groq.url', 'https://groq.test/v1');
        config()->set('ai.providers.gemini.key', 'gemini-test-key');
        config()->set('ai.providers.gemini.url', 'https://gemini.test/v1beta/');

        $tool = new class extends McpTool
        {
            protected string $name = 'fakeResolver';

            public int $invocations = 0;

            public function handle(McpRequest $request): ResponseFactory
            {
                $this->invocations++;

                return Response::structured([
                    'status' => 'success',
                    'resolved' => 'Neuroblastoma',
                ]);
            }
        };
        $dataTool = new class extends McpTool
        {
            protected string $name = 'fakeData';

            public int $invocations = 0;

            public function handle(McpRequest $request): ResponseFactory
            {
                $this->invocations++;

                return Response::structured([
                    'status' => 'success',
                    'target' => $request->get('target'),
                    'sample_count' => 5,
                ]);
            }
        };
        $catalog = Mockery::mock(ScopedMcpToolCatalog::class);
        $catalog->shouldReceive('forContext')->atLeast()->once()->andReturnUsing(
            static fn (ChatbotRunContext $context): array => [
                new ScopedMcpTool($tool, $context, new ToolResultCompactor),
                new ScopedMcpTool($dataTool, $context, new ToolResultCompactor),
            ],
        );

        $groqRequests = 0;
        $geminiRequests = 0;
        $geminiBodies = [];
        Http::fake(function (Request $request) use (&$groqRequests, &$geminiRequests, &$geminiBodies) {
            if (str_starts_with($request->url(), 'https://groq.test/')) {
                $groqRequests++;

                if ($groqRequests === 1) {
                    return Http::response(implode("\n\n", [
                        'data: '.json_encode([
                            'model' => 'llama-test',
                            'choices' => [[
                                'index' => 0,
                                'delta' => [
                                    'role' => 'assistant',
                                    'tool_calls' => [[
                                        'index' => 0,
                                        'id' => 'call-resolver',
                                        'type' => 'function',
                                        'function' => [
                                            'name' => 'fakeResolver',
                                            'arguments' => '{}',
                                        ],
                                    ]],
                                ],
                                'finish_reason' => 'tool_calls',
                            ]],
                        ], JSON_THROW_ON_ERROR),
                        'data: [DONE]',
                    ])."\n\n", 200, ['Content-Type' => 'text/event-stream']);
                }

                return Http::response([
                    'error' => ['message' => 'rate limited after tool execution'],
                ], 429);
            }

            if (str_starts_with($request->url(), 'https://gemini.test/')) {
                $geminiRequests++;
                $geminiBodies[] = $request->data();

                if ($geminiRequests === 1) {
                    return Http::response(
                        'data: '.json_encode([
                            'modelVersion' => 'gemini-test',
                            'candidates' => [[
                                'content' => ['parts' => [[
                                    'functionCall' => [
                                        'name' => 'fakeResolver',
                                        'args' => (object) [],
                                    ],
                                ]]],
                                'finishReason' => 'STOP',
                            ]],
                            'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
                        ], JSON_THROW_ON_ERROR)."\n\n",
                        200,
                        ['Content-Type' => 'text/event-stream'],
                    );
                }

                if ($geminiRequests === 2) {
                    return Http::response(
                        'data: '.json_encode([
                            'modelVersion' => 'gemini-test',
                            'candidates' => [[
                                'content' => ['parts' => [[
                                    'functionCall' => [
                                        'name' => 'fakeData',
                                        'args' => ['target' => 'MYCN'],
                                    ],
                                ]]],
                                'finishReason' => 'STOP',
                            ]],
                            'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
                        ], JSON_THROW_ON_ERROR)."\n\n",
                        200,
                        ['Content-Type' => 'text/event-stream'],
                    );
                }

                return Http::response(
                    "data: {\"modelVersion\":\"gemini-test\",\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"Five MYCN ChIP-seq samples were found.\"}]},\"finishReason\":\"STOP\"}],\"usageMetadata\":{\"promptTokenCount\":1,\"candidatesTokenCount\":2}}\n\n",
                    200,
                    ['Content-Type' => 'text/event-stream'],
                );
            }

            return Http::response(['error' => ['message' => 'Unexpected request']], 500);
        });

        $events = iterator_to_array((new ClinomicsChatAgentRunner($catalog, new ExpressionPlotPresenter))->stream(
            'global',
            'all',
            'Clinomics',
            'Show me the ChIP-seq samples targeting MYCN in NB.',
        ));
        $types = array_column($events, 'type');
        $complete = collect($events)->firstWhere('type', 'complete');
        $lastMeta = collect($events)->where('type', 'meta')->last();

        $this->assertContains('tool_finished', $types);
        $this->assertContains('answer_reset', $types);
        $this->assertSame(
            ['groq', 'gemini'],
            collect($events)->where('type', 'meta')->pluck('provider')->unique()->values()->all(),
        );
        $this->assertSame('gemini', $lastMeta['provider']);
        $this->assertSame('Five MYCN ChIP-seq samples were found.', $complete['answer']);
        $this->assertSame('gemini', $complete['provider']);
        $this->assertSame(3, $complete['tool_calls']);
        $this->assertSame(1, $tool->invocations, 'The completed tool must not run again during handoff.');
        $this->assertSame(1, $dataTool->invocations, 'Gemini should be able to call a remaining tool after handoff.');
        $this->assertSame(2, $groqRequests);
        $this->assertSame(3, $geminiRequests);
        $this->assertStringContainsString(
            'Neuroblastoma',
            json_encode($geminiBodies[0], JSON_THROW_ON_ERROR),
            'The fallback provider must receive the completed resolver evidence.',
        );
        $this->assertCount(2, $complete['executions']);
        $this->assertSame('fakeResolver', $complete['executions'][0]['tool']);
        $this->assertSame('fakeData', $complete['executions'][1]['tool']);
    }

    public function test_streaming_runner_emits_only_public_progress_and_answer_events(): void
    {
        ClinomicsChatAgent::fake(['A streamed Clinomics answer.']);

        $events = iterator_to_array(app(ClinomicsChatAgentRunner::class)->stream(
            'global',
            'all',
            'Clinomics',
            'show projects',
            [
                ['role' => 'user', 'content' => 'Earlier question'],
                ['role' => 'assistant', 'content' => 'Earlier answer'],
            ],
        ));
        $types = array_column($events, 'type');

        $this->assertContains('status', $types);
        $this->assertContains('answer_delta', $types);
        $this->assertSame('complete', end($events)['type']);
        $this->assertSame('A streamed Clinomics answer.', end($events)['answer']);
        $this->assertNotContains('reasoning_delta', $types);
        $this->assertNotContains('tool_result', $types);
    }

    public function test_streaming_runner_turns_in_band_provider_errors_into_failures(): void
    {
        $runner = app(ClinomicsChatAgentRunner::class);
        $method = new ReflectionMethod($runner, 'throwIfStreamError');
        $event = new StreamError('event-1', 'error', 'provider unavailable', false, time());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider unavailable');

        $method->invoke($runner, $event);
    }

    public function test_compactor_replaces_large_table_json_with_bounded_preview(): void
    {
        $rows = array_map(static fn (int $index): array => [$index, 'sample-'.$index], range(1, 20));
        $result = (new ToolResultCompactor(5, 10000))->compact([
            'status' => 'success',
            'table_json' => json_encode([
                'cols' => [['title' => 'Number'], ['title' => 'Sample']],
                'data' => $rows,
            ]),
        ]);

        $this->assertArrayNotHasKey('table_json', $result);
        $this->assertSame(20, $result['table_preview']['row_count']);
        $this->assertCount(5, $result['table_preview']['rows']);
        $this->assertTrue($result['table_preview']['truncated']);
    }

    public function test_compactor_replaces_expression_matrix_with_numeric_group_summary(): void
    {
        $result = (new ToolResultCompactor(5, 10000))->compact([
            'status' => 'success',
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'value_type' => 'tmm-rpkm',
            'transform' => 'none',
            'plot_rows' => [
                ['group' => 'Tumor', 'raw_expression' => 1],
                ['group' => 'Tumor', 'raw_expression' => 3],
                ['group' => 'Normal', 'raw_expression' => 15],
                ['group' => 'Normal', 'raw_expression' => 31],
            ],
            'expression_data_json' => str_repeat('large raw matrix', 1000),
        ]);

        $this->assertArrayNotHasKey('plot_rows', $result);
        $this->assertArrayNotHasKey('expression_data_json', $result);
        $this->assertSame(
            ['Group', 'Samples', 'Mean', 'Median', 'Minimum', 'Maximum'],
            $result['expression_summary']['columns'],
        );
        $this->assertSame([
            ['Tumor', 2, 2.0, 2.0, 1.0, 3.0],
            ['Normal', 2, 23.0, 23.0, 15.0, 31.0],
        ], $result['expression_summary']['rows']);
        $this->assertStringContainsString('FGFR4 expression summary', $result['summary']);
    }

    public function test_compactor_preserves_all_exact_cancer_type_names_for_resolution(): void
    {
        $result = (new ToolResultCompactor(1, 1000))->compact([
            'status' => 'success',
            'action' => 'getCancerTypes',
            'cancer_type_count' => 3,
            'cancer_types' => [
                ['diagnosis' => 'ARMS', 'sample_types' => ['RNAseq' => 20]],
                ['diagnosis' => 'Neuroblastoma', 'sample_types' => ['ChIPseq' => 10]],
                ['diagnosis' => 'Osteosarcoma', 'sample_types' => ['Exome' => 30]],
            ],
        ]);

        $this->assertSame(['ARMS', 'Neuroblastoma', 'Osteosarcoma'], $result['cancer_types']);
    }

    public function test_presented_result_keeps_a_bounded_table_plot_and_safe_link(): void
    {
        $presented = app(ClinomicsChatAgentRunner::class)->presentCompatibilityResult([
            'status' => 'success',
            'action' => 'runDifferentialExpression',
            'summary' => 'Differential expression completed.',
            'table_json' => json_encode([
                'cols' => [['title' => 'Gene']],
                'data' => [['FGFR4']],
            ]),
            'volcano_plot' => [
                'mime_type' => 'image/png',
                'base64' => base64_encode('small-test-image'),
            ],
            'redirect_url' => 'https://example.test/result',
        ]);

        $this->assertSame(1, $presented['table']['row_count']);
        $this->assertSame('image', $presented['artifacts'][0]['type']);
        $this->assertStringStartsWith('data:image/png;base64,', $presented['artifacts'][0]['data_url']);
        $this->assertSame('https://example.test/result', $presented['links'][0]['url']);
        $this->assertArrayNotHasKey('volcano_plot', $presented['preview']);
        $this->assertArrayNotHasKey('table_json', $presented['preview']);
    }

    public function test_presented_table_converts_safe_anchor_cells_to_structured_links(): void
    {
        $presented = app(ClinomicsChatAgentRunner::class)->presentCompatibilityResult([
            'status' => 'success',
            'action' => 'getCohortChIPseq',
            'table_json' => json_encode([
                'cols' => [['title' => 'Library'], ['title' => 'Target']],
                'data' => [
                    ['<a href="https://example.test/viewChIPseqSample/P1/S1" target=_blank>LIB-1</a>', 'MYCN'],
                    ['<a href="javascript:alert(1)">Unsafe</a>', 'MYCN'],
                ],
            ]),
        ]);

        $this->assertSame([
            'type' => 'link',
            'label' => 'LIB-1',
            'url' => 'https://example.test/viewChIPseqSample/P1/S1',
        ], $presented['table']['rows'][0][0]);
        $this->assertSame('Unsafe', $presented['table']['rows'][1][0]);
        $this->assertStringNotContainsString('<a ', json_encode($presented['table'], JSON_THROW_ON_ERROR));
    }

    public function test_presented_table_decodes_json_encoded_link_cells(): void
    {
        $link = json_encode([
            'type' => 'link',
            'label' => 'TR14_H3K27ac_C',
            'url' => 'https://example.test/viewChIPseqSample/TR14/TR14_H3K27ac_C_GSE90683',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $unsafe = json_encode([
            'type' => 'link',
            'label' => 'Unsafe',
            'url' => 'javascript:alert(1)',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $presented = app(ClinomicsChatAgentRunner::class)->presentCompatibilityResult([
            'status' => 'success',
            'action' => 'getCohortChIPseq',
            'table_json' => json_encode([
                'cols' => [['title' => 'Library']],
                'data' => [[$link], [$unsafe]],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame([
            'type' => 'link',
            'label' => 'TR14_H3K27ac_C',
            'url' => 'https://example.test/viewChIPseqSample/TR14/TR14_H3K27ac_C_GSE90683',
        ], $presented['table']['rows'][0][0]);
        $this->assertSame('Unsafe', $presented['table']['rows'][1][0]);
    }

    public function test_presented_expression_result_contains_an_interactive_plotly_chart(): void
    {
        $presented = app(ClinomicsChatAgentRunner::class)->presentCompatibilityResult([
            'status' => 'success',
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'value_type' => 'tmm-rpkm',
            'dataset_scope' => 'all',
            'plot_rows' => [
                ['group' => 'Tumor', 'raw_expression' => 1],
                ['group' => 'Tumor', 'raw_expression' => 3],
                ['group' => 'Normal', 'raw_expression' => 15],
                ['group' => 'Normal', 'raw_expression' => 31],
            ],
            'expression_data_json' => str_repeat('large raw result', 1000),
        ], 'Show log2 FGFR4 expression as a violin plot ordered by median descending.');

        $this->assertSame('plotly', $presented['charts'][0]['type']);
        $this->assertSame(['Normal', 'Tumor'], $presented['charts'][0]['layout']['xaxis']['categoryarray']);
        $this->assertSame(4, $presented['charts'][0]['value_count']);
        $this->assertArrayNotHasKey('plot_rows', $presented['preview']);
        $this->assertArrayNotHasKey('expression_data_json', $presented['preview']);
        $this->assertStringContainsString('Violin plot of FGFR4', $presented['summary']);
    }

    public function test_presented_cohort_expression_follow_up_contains_a_violin_chart(): void
    {
        $presented = app(ClinomicsChatAgentRunner::class)->presentCompatibilityResult([
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
            'table_json' => json_encode([
                'cols' => [['title' => 'Patient ID'], ['title' => 'Sample ID'], ['title' => 'TPM']],
                'data' => [['P1', 'S1', 0.5], ['P2', 'S2', 2.22], ['P3', 'S3', 125.99]],
            ], JSON_THROW_ON_ERROR),
        ], 'plot violin');

        $this->assertSame('plotly', $presented['charts'][0]['type']);
        $this->assertSame('violin', $presented['charts'][0]['data'][0]['type']);
        $this->assertSame('Neuroblastoma', $presented['charts'][0]['data'][0]['name']);
        $this->assertSame(3, $presented['charts'][0]['value_count']);
    }

    public function test_presented_expression_result_without_a_plot_contains_a_summary_table(): void
    {
        $presented = app(ClinomicsChatAgentRunner::class)->presentCompatibilityResult([
            'status' => 'success',
            'action' => 'expression_by_gene',
            'gene' => 'FGFR4',
            'value_type' => 'tmm-rpkm',
            'dataset_scope' => 'all',
            'plot_rows' => [
                ['group' => 'Tumor', 'raw_expression' => 1],
                ['group' => 'Tumor', 'raw_expression' => 3],
                ['group' => 'Normal', 'raw_expression' => 15],
                ['group' => 'Normal', 'raw_expression' => 31],
            ],
            'expression_data_json' => str_repeat('large raw result', 1000),
        ], 'Show me the FGFR4 expression');

        $this->assertSame([], $presented['charts']);
        $this->assertSame(2, $presented['table']['row_count']);
        $this->assertSame(['Tumor', 2, 2.0, 2.0, 1.0, 3.0], $presented['table']['rows'][0]);
        $this->assertSame(['Normal', 2, 23.0, 23.0, 15.0, 31.0], $presented['table']['rows'][1]);
        $this->assertStringContainsString('FGFR4 expression summary', $presented['summary']);
    }

    public function test_presented_evidence_hides_resolvers_after_a_data_tool_runs(): void
    {
        $runner = app(ClinomicsChatAgentRunner::class);
        $method = new ReflectionMethod($runner, 'presentExecutions');
        $resolver = [
            'tool' => 'getCancerTypes',
            'arguments' => [],
            'result' => [
                'status' => 'success',
                'table_json' => json_encode([
                    'cols' => [['title' => 'Cancer Type']],
                    'data' => [['Neuroblastoma']],
                ]),
            ],
        ];
        $expression = [
            'tool' => 'getCohortExpression',
            'arguments' => ['cohort_type' => 'cancer_type', 'cohort_id' => 'Neuroblastoma', 'gene' => 'FGFR4'],
            'result' => [
                'status' => 'success',
                'table_json' => json_encode([
                    'cols' => [['title' => 'Sample'], ['title' => 'TPM']],
                    'data' => [['S1', 4.5]],
                ]),
            ],
        ];

        $presented = $method->invoke($runner, [$resolver, $expression]);
        $resolverOnly = $method->invoke($runner, [$resolver]);

        $this->assertSame(['getCohortExpression'], array_column($presented, 'tool'));
        $this->assertSame(['getCancerTypes'], array_column($resolverOnly, 'tool'));
    }

    public function test_expression_data_request_requires_continuation_after_resolver_only(): void
    {
        $runner = app(ClinomicsChatAgentRunner::class);
        $method = new ReflectionMethod($runner, 'needsDataContinuation');
        $resolverOnly = [[
            'tool' => 'getCancerTypes',
            'result' => ['status' => 'success', 'action' => 'getCancerTypes'],
        ]];
        $withExpression = [...$resolverOnly, [
            'tool' => 'getCohortExpression',
            'result' => ['status' => 'success', 'action' => 'getCohortExpression'],
        ]];

        $query = 'show me FGFR4 zscore TPM violin plot in Neuroblastoma';

        $this->assertTrue($method->invoke($runner, $query, $resolverOnly));
        $this->assertFalse($method->invoke($runner, $query, $withExpression));
    }
}
