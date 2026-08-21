<?php

namespace Tests\Unit;

use App\Http\Controllers\ChatbotController;
use App\Services\ChatbotConversationStore;
use App\Services\ClinomicsChatAgentRunner;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ChatbotStreamingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $auth = Mockery::mock('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface');
        $auth->shouldReceive('getLoggedUser')->zeroOrMoreTimes()->andReturn((object) ['id' => 81]);
        $this->app->instance('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface', $auth);
    }

    public function test_stream_endpoint_persists_the_turn_and_returns_ndjson_events(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $runner = $this->runner();
        $runner->shouldReceive('stream')->once()->andReturnUsing(static function (): \Generator {
            yield ['type' => 'status', 'message' => 'Selecting tools.'];
            yield ['type' => 'answer_delta', 'delta' => 'The answer'];
            yield [
                'type' => 'complete',
                'answer' => 'The answer',
                'provider' => 'fake',
                'model' => 'fake-model',
                'steps' => 1,
                'tool_calls' => 0,
                'used_summarizer' => false,
                'usage' => [],
                'executions' => [],
            ];
        });
        $request = Request::create('/chatbot', 'POST', [
            'query' => 'Show projects',
            'message_id' => (string) Str::uuid(),
        ]);

        $response = (new ChatbotController)->streamMessage(
            $request,
            $conversation['id'],
            $runner,
            $store,
        );

        $this->assertSame('application/x-ndjson; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));

        ob_start();
        ob_start();
        ($response->getCallback())();
        $tail = ob_get_clean();
        $output = ob_get_clean().$tail;

        $events = array_map(
            static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            array_values(array_filter(explode("\n", trim($output))))
        );
        $this->assertSame(['accepted', 'status', 'answer_delta', 'complete'], array_column($events, 'type'));
        $complete = collect($events)->firstWhere('type', 'complete');
        $this->assertStringContainsString('<p>The answer</p>', $complete['answer_html']);

        $stored = $store->get($conversation['id'], 81);
        $this->assertSame(['user', 'assistant'], array_column($stored['messages'], 'role'));
        $this->assertSame('The answer', $stored['messages'][1]['content']);
    }

    public function test_stream_complete_contains_sanitized_markdown_table_html(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $runner = $this->runner();
        $runner->shouldReceive('stream')->once()->andReturnUsing(static function (): \Generator {
            yield [
                'type' => 'complete',
                'answer' => "| Gene | TPM |\n| --- | ---: |\n| FGFR4 | 4.09 |",
                'provider' => 'fake',
                'model' => 'fake-model',
                'steps' => 1,
                'tool_calls' => 0,
                'used_summarizer' => false,
                'usage' => [],
                'executions' => [],
            ];
        });
        $response = (new ChatbotController)->streamMessage(
            Request::create('/chatbot', 'POST', ['query' => 'Show a table']),
            $conversation['id'],
            $runner,
            $store,
        );

        ob_start();
        ob_start();
        ($response->getCallback())();
        $tail = ob_get_clean();
        $output = ob_get_clean().$tail;
        $events = array_map(
            static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            array_values(array_filter(explode("\n", trim($output)))),
        );
        $complete = collect($events)->firstWhere('type', 'complete');

        $this->assertStringContainsString('<table>', $complete['answer_html']);
        $this->assertStringContainsString('<td>FGFR4</td>', $complete['answer_html']);
    }

    public function test_stream_endpoint_preserves_provider_handoff_and_only_persists_the_final_answer(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $runner = $this->runner();
        $runner->shouldReceive('stream')->once()->andReturnUsing(static function (): \Generator {
            yield ['type' => 'meta', 'provider' => 'groq', 'model' => 'llama-test'];
            yield ['type' => 'answer_delta', 'delta' => 'Partial answer'];
            yield ['type' => 'answer_reset'];
            yield ['type' => 'status', 'message' => 'Continuing with Gemini.'];
            yield ['type' => 'meta', 'provider' => 'gemini', 'model' => 'gemini-test'];
            yield ['type' => 'answer_delta', 'delta' => 'Final answer'];
            yield [
                'type' => 'complete',
                'answer' => 'Final answer',
                'provider' => 'gemini',
                'model' => 'gemini-test',
                'steps' => 2,
                'tool_calls' => 1,
                'used_summarizer' => false,
                'usage' => [],
                'executions' => [],
            ];
        });
        $response = (new ChatbotController)->streamMessage(
            Request::create('/chatbot', 'POST', ['query' => 'Show data']),
            $conversation['id'],
            $runner,
            $store,
        );

        ob_start();
        ob_start();
        ($response->getCallback())();
        $tail = ob_get_clean();
        $output = ob_get_clean().$tail;
        $events = array_map(
            static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            array_values(array_filter(explode("\n", trim($output)))),
        );

        $this->assertContains('answer_reset', array_column($events, 'type'));
        $this->assertSame('gemini', collect($events)->firstWhere('type', 'complete')['provider']);
        $stored = $store->get($conversation['id'], 81);
        $this->assertSame('Final answer', $stored['messages'][1]['content']);
        $this->assertSame('gemini', $stored['messages'][1]['meta']['provider']);
    }

    public function test_foreign_conversation_id_is_indistinguishable_from_a_missing_one(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(82, 'global', 'all', 'Clinomics');
        $runner = $this->runner();

        $response = (new ChatbotController)->streamMessage(
            Request::create('/chatbot', 'POST', ['query' => 'Show projects']),
            $conversation['id'],
            $runner,
            $store,
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Conversation not found.', $response->getData(true)['message']);
    }

    public function test_stream_endpoint_rate_limits_each_authenticated_user(): void
    {
        config()->set('chatbot.conversations.runs_per_minute', 1);
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $runner = $this->runner();
        $runner->shouldReceive('stream')->once()->andReturnUsing(static function (): \Generator {
            yield [
                'type' => 'complete', 'answer' => 'Done', 'provider' => 'fake', 'model' => 'fake',
                'steps' => 1, 'tool_calls' => 0, 'used_summarizer' => false,
                'usage' => [], 'executions' => [],
            ];
        });
        $controller = new ChatbotController;

        $first = $controller->streamMessage(
            Request::create('/chatbot', 'POST', ['query' => 'First']),
            $conversation['id'],
            $runner,
            $store,
        );
        ob_start();
        ob_start();
        ($first->getCallback())();
        ob_end_clean();
        ob_end_clean();

        $second = $controller->streamMessage(
            Request::create('/chatbot', 'POST', ['query' => 'Second']),
            $conversation['id'],
            $runner,
            $store,
        );

        $this->assertSame(429, $second->getStatusCode());
        $this->assertNotNull($second->headers->get('Retry-After'));
    }

    public function test_stream_uses_compatibility_engine_when_provider_fails_before_a_tool_completes(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $runner = $this->runner();
        $runner->shouldReceive('stream')->once()->andReturnUsing(static function (): \Generator {
            yield ['type' => 'status', 'message' => 'Selecting tools.'];
            throw new \RuntimeException('429 rate limit');
        });
        $runner->shouldReceive('presentCompatibilityResult')->once()->andReturn([
            'tool' => 'getProjects',
            'table' => ['columns' => ['Project'], 'rows' => [['Compass']], 'row_count' => 1],
        ]);
        $controller = new class extends ChatbotController
        {
            protected function runMcpWithLlmToolSelection($cohort_id, $query, $scope = 'project')
            {
                return [
                    'status' => 'success',
                    'action' => 'getProjects',
                    'summary' => 'One project was found.',
                    'table_json' => '{"cols":["Project"],"data":[["Compass"]]}',
                ];
            }
        };
        $response = $controller->streamMessage(
            Request::create('/chatbot', 'POST', ['query' => 'Show projects']),
            $conversation['id'],
            $runner,
            $store,
        );

        ob_start();
        ob_start();
        ($response->getCallback())();
        $tail = ob_get_clean();
        $output = ob_get_clean().$tail;
        $events = array_map(
            static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            array_values(array_filter(explode("\n", trim($output))))
        );

        $this->assertContains('complete', array_column($events, 'type'));
        $complete = collect($events)->firstWhere('type', 'complete');
        $this->assertTrue($complete['fallback']);
        $this->assertSame('One project was found.', $complete['answer']);
        $this->assertSame(1, $complete['executions'][0]['table']['row_count']);
        $stored = $store->get($conversation['id'], 81);
        $this->assertSame('One project was found.', $stored['messages'][1]['content']);
        $this->assertTrue($stored['messages'][1]['meta']['fallback']);
    }

    public function test_compatibility_engine_receives_history_for_a_short_expression_follow_up(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'project', 22112, 'Clinomics');
        $store->appendUserMessage(
            $conversation['id'],
            81,
            'show me the log2 FGFR4 TPM',
        );
        $store->appendAssistantMessage(
            $conversation['id'],
            81,
            'FGFR4 log2(TPM + 1) expression was returned.',
        );
        $runner = $this->runner();
        $runner->shouldReceive('stream')->once()->andReturnUsing(static function (): \Generator {
            throw new \RuntimeException('provider unavailable');
            yield;
        });
        $runner->shouldReceive('presentCompatibilityResult')->once()->andReturn([
            'tool' => 'expression_by_gene',
            'summary' => 'FGFR4 expression grouped by diagnosis.',
            'table' => ['columns' => ['Group'], 'rows' => [['Diagnosis A']], 'row_count' => 1],
        ]);
        $controller = new class extends ChatbotController
        {
            public string $selectionQuery = '';

            protected function resolveChatbotContext($scope, $cohortId)
            {
                return [
                    'status' => 'success',
                    'scope' => 'project',
                    'id' => 22112,
                    'name' => 'Clinomics',
                ];
            }

            protected function runMcpWithLlmToolSelection($cohort_id, $query, $scope = 'project')
            {
                $this->selectionQuery = $query;

                return [
                    'status' => 'success',
                    'action' => 'expression_by_gene',
                ];
            }
        };

        $response = $controller->streamMessage(
            Request::create('/chatbot', 'POST', ['query' => 'group by diagnosis']),
            $conversation['id'],
            $runner,
            $store,
        );
        ob_start();
        ob_start();
        ($response->getCallback())();
        ob_end_clean();
        ob_end_clean();

        $this->assertStringContainsString('show me the log2 FGFR4 TPM', $controller->selectionQuery);
        $this->assertStringContainsString('FGFR4 log2(TPM + 1)', $controller->selectionQuery);
        $this->assertStringContainsString('Current user request: group by diagnosis', $controller->selectionQuery);
    }

    public function test_compatibility_engine_does_not_mix_expression_history_into_a_new_alteration_request(): void
    {
        $controller = new ChatbotController;
        $method = new \ReflectionMethod($controller, 'compatibilitySelectionQuery');
        $history = [
            ['role' => 'user', 'content' => 'show me the FGFR4 expression'],
            ['role' => 'assistant', 'content' => 'FGFR4 expression was returned.'],
        ];

        $selectionQuery = $method->invoke($controller, 'show alteration of alk', $history);

        $this->assertSame('show alteration of alk', $selectionQuery);
        $this->assertStringNotContainsString('FGFR4', $selectionQuery);
        $this->assertStringNotContainsString('expression', $selectionQuery);
    }

    public function test_expression_plot_refinement_carries_only_the_omitted_known_cohort(): void
    {
        $controller = new ChatbotController;
        $method = new \ReflectionMethod($controller, 'agentQueryWithOmittedCohort');
        $history = [
            ['role' => 'user', 'content' => 'show me the expression of FGFR4 in NB'],
            ['role' => 'assistant', 'content' => 'FGFR4 expression in Neuroblastoma was returned.'],
        ];

        $agentQuery = $method->invoke(
            $controller,
            'global',
            'show me FGFR4 zscore TPM violin plot',
            $history,
        );

        $this->assertStringStartsWith('show me FGFR4 zscore TPM violin plot', $agentQuery);
        $this->assertStringContainsString('omitted cohort remains cancer type Neuroblastoma', $agentQuery);
        $this->assertStringNotContainsString('expression in Neuroblastoma was returned', $agentQuery);
    }

    public function test_expression_plot_follow_up_uses_the_previous_successful_project_execution(): void
    {
        $controller = new ChatbotController;
        $method = new \ReflectionMethod($controller, 'agentQueryWithOmittedCohort');
        $history = [
            ['role' => 'user', 'content' => 'Show PTEN pathogenic mutations in compass project'],
            ['role' => 'assistant', 'content' => 'PTEN mutations were returned for COMPASS.'],
        ];
        $conversation = ['messages' => [
            ['role' => 'user', 'content' => 'Show PTEN pathogenic mutations in compass project'],
            ['role' => 'assistant', 'meta' => ['executions' => [[
                'tool' => 'get_pathogeic_mutations',
                'status' => 'success',
                'arguments' => ['project_id' => 25062, 'gene_id' => 'PTEN'],
                'preview' => ['project_id' => 25062, 'project_name' => 'COMPASS'],
            ]]]],
        ]];

        $agentQuery = $method->invoke(
            $controller,
            'global',
            'Show violin plot for log2TPM FGFR4',
            $history,
            $conversation,
        );

        $this->assertStringContainsString(
            'Server-resolved explicit project cohort: id=25062; name=COMPASS',
            $agentQuery,
        );
        $this->assertStringNotContainsString('Neuroblastoma', $agentQuery);
    }

    public function test_current_explicit_cohort_wins_over_previous_project_context(): void
    {
        $controller = new ChatbotController;
        $method = new \ReflectionMethod($controller, 'agentQueryWithOmittedCohort');
        $conversation = ['messages' => [
            ['role' => 'user', 'content' => 'Show PTEN mutations in COMPASS project'],
            [
                'role' => 'assistant',
                'meta' => ['executions' => [[
                    'status' => 'success',
                    'arguments' => ['project_id' => 25062],
                    'preview' => ['project_name' => 'COMPASS'],
                ]]],
            ],
        ]];

        $agentQuery = $method->invoke(
            $controller,
            'global',
            'Show FGFR4 violin plot in NB',
            [],
            $conversation,
        );

        $this->assertSame('Show FGFR4 violin plot in NB', $agentQuery);
        $this->assertStringNotContainsString('COMPASS', $agentQuery);
    }

    public function test_unqualified_wrong_execution_does_not_replace_the_user_named_project(): void
    {
        $controller = new ChatbotController;
        $method = new \ReflectionMethod($controller, 'agentQueryWithOmittedCohort');
        $conversation = ['messages' => [
            ['role' => 'user', 'content' => 'Show PTEN pathogenic mutations in COMPASS project'],
            ['role' => 'assistant', 'meta' => ['executions' => [[
                'status' => 'success',
                'arguments' => ['project_id' => 25062],
                'preview' => ['project_name' => 'COMPASS'],
            ]]]],
            ['role' => 'user', 'content' => 'Show violin plot for log2TPM FGFR4'],
            ['role' => 'assistant', 'meta' => ['executions' => [[
                'status' => 'success',
                'arguments' => ['cohort_type' => 'cancer_type', 'cohort_id' => 'Neuroblastoma'],
                'preview' => ['cohort_type' => 'cancer_type', 'cohort_id' => 'Neuroblastoma'],
            ]]]],
        ]];

        $agentQuery = $method->invoke(
            $controller,
            'global',
            'Show FGFR4 log2TPM violin plot again',
            [],
            $conversation,
        );

        $this->assertStringContainsString('id=25062; name=COMPASS', $agentQuery);
        $this->assertStringNotContainsString('Neuroblastoma', $agentQuery);
    }

    public function test_provider_token_limit_details_are_safe_and_visible(): void
    {
        $exception = new RequestException(new ClientResponse(new PsrResponse(
            413,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => [
                    'message' => 'Request too large on tokens per minute (TPM): Limit 12000, Requested 13828. Upgrade at https://billing.example.',
                ],
            ], JSON_THROW_ON_ERROR),
        )));
        $method = new \ReflectionMethod(new ChatbotController, 'publicStreamingError');

        $message = $method->invoke(new ChatbotController, $exception, false);

        $this->assertStringContainsString('requested 13,828 tokens', $message);
        $this->assertStringContainsString('limit 12,000 tokens per minute', $message);
        $this->assertStringNotContainsString('billing.example', $message);
    }

    public function test_stream_does_not_repeat_an_analysis_after_a_tool_has_started(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $runner = $this->runner();
        $runner->shouldReceive('stream')->once()->andReturnUsing(static function (): \Generator {
            yield ['type' => 'tool_started', 'tool_id' => 'tool-1', 'tool' => 'getProjects'];
            throw new \RuntimeException('process stopped');
        });
        $controller = new class extends ChatbotController
        {
            public bool $fallbackCalled = false;

            protected function runMcpWithLlmToolSelection($cohort_id, $query, $scope = 'project')
            {
                $this->fallbackCalled = true;

                return ['status' => 'success'];
            }
        };
        $response = $controller->streamMessage(
            Request::create('/chatbot', 'POST', ['query' => 'Show projects']),
            $conversation['id'],
            $runner,
            $store,
        );

        ob_start();
        ob_start();
        ($response->getCallback())();
        $tail = ob_get_clean();
        $output = ob_get_clean().$tail;

        $this->assertFalse($controller->fallbackCalled);
        $this->assertStringContainsString('"type":"tool_started"', $output);
        $this->assertStringContainsString('"type":"error"', $output);
        $this->assertStringNotContainsString('compatibility_mcp_engine', $output);
    }

    private function runner(): MockInterface
    {
        return Mockery::mock(ClinomicsChatAgentRunner::class);
    }
}
