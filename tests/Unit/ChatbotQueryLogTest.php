<?php

namespace Tests\Unit;

use App\Http\Controllers\ChatbotLogController;
use App\Http\Middleware\LogChatbotQuery;
use App\Services\ChatbotQueryLogStore;
use App\Services\ChatbotQueryTelemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChatbotQueryLogTest extends TestCase
{
    private ChatbotQueryLogStore $logs;
    private ChatbotQueryTelemetry $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        config(['chatbot.logs.connection' => 'sqlite', 'chatbot.logs.display_timezone' => 'America/New_York']);
        (require database_path('migrations/2026_11_12_000001_create_chatbot_query_logs_table.php'))->up();
        Schema::create('user_profile', function ($table) {
            $table->integer('user_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
        Schema::create('projects', function ($table) {
            $table->integer('id');
            $table->string('name');
        });
        $this->logs = app(ChatbotQueryLogStore::class);
        $this->metrics = app(ChatbotQueryTelemetry::class);
        $this->authenticate();
    }

    private function authenticate(bool $admin = false): void
    {
        $user = new class($admin) {
            public int $id = 81;
            public string $email = 'admin@example.test';
            public function __construct(private bool $admin) {}
            public function hasPermission($permission) { return $permission === '_superadmin' && $this->admin; }
        };
        $auth = Mockery::mock('LaravelAcl\Authentication\Interfaces\AuthenticateInterface');
        $auth->shouldReceive('getLoggedUser')->zeroOrMoreTimes()->andReturn($user);
        $this->app->instance('LaravelAcl\Authentication\Interfaces\AuthenticateInterface', $auth);
    }

    private function record(string $question = 'Question'): string
    {
        $this->metrics->begin(81, $question);
        $id = $this->metrics->id();
        $this->metrics->finish(['answer' => 'Answer', 'provider' => 'rules']);
        return $id;
    }

    public function test_metrics_sum_calls_without_double_counting_start_events(): void
    {
        $this->metrics->begin(81, str_repeat('Gene α ', 1000), ['scope' => 'global']);
        $id = $this->metrics->id();
        $this->metrics->llm('agent', 'openai', 'model-a', []);
        $this->metrics->llm('agent', 'openai', 'model-a', ['prompt_tokens' => 100, 'completion_tokens' => 20]);
        $this->metrics->llm('summary', 'openai', 'model-b', ['prompt_tokens' => 30, 'completion_tokens' => 5]);
        $this->metrics->tool('getExpGeneSummary');
        $this->metrics->tool('getExpGeneSummary');
        $answer = str_repeat('Answer β ', 1000);
        $this->metrics->finish(['answer' => $answer]);
        $row = $this->logs->query()->find($id);
        $this->assertSame($answer, $row->answer);
        $this->assertSame(155, (int) $row->total_tokens);
        $this->assertSame('reported', $row->usage_status);
        $this->assertCount(2, json_decode($row->llm_calls_json, true));
        $this->assertSame(['getExpGeneSummary'], json_decode($row->tools_json, true));
        $this->assertSame('model-b', $row->model);
        $this->assertSame('completed', $row->status);
        $this->assertNotNull($row->completed_at);
        $this->assertGreaterThanOrEqual(0, $row->duration_ms);
        $this->assertNull($row->rating);
        $this->assertNull($row->feedback);
    }

    public function test_partial_unknown_and_rules_usage_are_distinct(): void
    {
        $this->metrics->begin(81, 'Partial');
        $id = $this->metrics->id();
        $this->metrics->llm('failed', 'openai', 'a', []);
        $this->metrics->legacyUsage('gemini', 'b', ['usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 18, 'thoughtsTokenCount' => 3]]);
        $this->metrics->finish(['answer' => 'Answer']);
        $row = $this->logs->query()->find($id);
        $this->assertSame('partial', $row->usage_status);
        $this->assertSame(18, (int) $row->total_tokens);
        $this->metrics->begin(81, 'Unknown');
        $id = $this->metrics->id();
        $this->metrics->finish(['answer' => 'Answer']);
        $this->assertNull($this->logs->query()->find($id)->total_tokens);
        $id = $this->record();
        $this->assertSame(0, (int) $this->logs->query()->find($id)->total_tokens);
        $this->assertSame('not_applicable', $this->logs->query()->find($id)->usage_status);
    }

    public function test_feedback_is_optional_editable_and_does_not_modify_answer(): void
    {
        $id = $this->record();
        $controller = app(ChatbotLogController::class);
        $response = $controller->feedback(Request::create('/', 'POST', ['rating' => 5, 'feedback' => ' Useful ', 'answer' => 'Forged', 'user_id' => 99]), $id, $this->logs);
        $this->assertSame(200, $response->getStatusCode());
        $row = $this->logs->query()->find($id);
        $this->assertSame('Useful', $row->feedback);
        $this->assertSame('Answer', $row->answer);
        $this->assertSame(81, (int) $row->user_id);
        $this->assertSame([], $this->logs->feedbackFor(99, [$id]));
        $controller->feedback(Request::create('/', 'POST', []), $id, $this->logs);
        $this->assertNull($this->logs->query()->find($id)->rating);
        $this->assertNull($this->logs->query()->find($id)->feedback);
    }

    public function test_other_users_cannot_submit_feedback(): void
    {
        $id = $this->record();
        try { $this->logs->feedback($id, 99, 1, 'Forged'); $this->fail('Expected ownership rejection'); }
        catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        $this->assertNull($this->logs->query()->find($id)->rating);
    }

    public function test_invalid_rating_and_oversize_feedback_are_rejected(): void
    {
        $id = $this->record();
        foreach ([['rating' => 0], ['rating' => 6], ['rating' => 2.5], ['feedback' => str_repeat('x', 5001)]] as $values) {
            try { app(ChatbotLogController::class)->feedback(Request::create('/', 'POST', $values), $id, $this->logs); $this->fail('Expected invalid feedback'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }

    public function test_running_response_rejects_feedback(): void
    {
        $this->metrics->begin(81, 'Running');
        try { $this->logs->feedback($this->metrics->id(), 81, 5, null); $this->fail('Expected running rejection'); }
        catch (HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
    }

    public function test_search_escapes_wildcards_and_combines_filters(): void
    {
        $id = $this->record('BRCA1 100%_match');
        $this->logs->feedback($id, 81, 4, 'Useful');
        $this->logs->finish($id, ['started_at' => '2026-11-13 04:59:59']);
        $this->record('BRCA1 100Xmatch');
        $this->assertSame(1, $this->logs->search(['q' => '%_'])->total());
        $this->assertSame(0, $this->logs->search(['q' => 'BRCA1', 'user_id' => 99])->total());
        $rows = $this->logs->search(['q' => 'useful', 'rating' => '4', 'from' => '2026-11-12', 'to' => '2026-11-12']);
        $this->assertSame(1, $rows->total());
        $this->assertSame($id, $rows->items()[0]->id);
        $this->assertSame(1, $this->logs->search(['rating' => 'unrated'])->total());
    }

    public function test_report_rejects_non_admin_before_reading_logs(): void
    {
        $store = Mockery::mock(ChatbotQueryLogStore::class);
        $store->shouldNotReceive('search');
        try { app(ChatbotLogController::class)->report(Request::create('/'), $store); $this->fail('Expected admin rejection'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_stream_is_finalized_after_callback_and_keeps_partial_answer(): void
    {
        $response = app(LogChatbotQuery::class)->handle(Request::create('/', 'POST', ['query' => 'Question']), function () {
            return new StreamedResponse(function () { $this->metrics->observe(['type' => 'answer_delta', 'delta' => 'Partial answer']); });
        });
        $this->assertSame('running', $this->logs->query()->first()->status);
        ($response->getCallback())();
        $row = $this->logs->query()->first();
        $this->assertSame('interrupted', $row->status);
        $this->assertSame('Partial answer', $row->answer);
        $this->assertSame(1, $this->logs->query()->count());
    }

    public function test_sync_response_and_exceptions_are_logged(): void
    {
        app(LogChatbotQuery::class)->handle(Request::create('/', 'POST', ['query' => 'Question']), fn () => response()->json(['summary' => 'Summary']));
        $this->assertSame('Summary', $this->logs->query()->first()->answer);
        try {
            app(LogChatbotQuery::class)->handle(Request::create('/', 'POST', ['query' => 'Failure']), fn () => throw new \RuntimeException('Failure'));
        } catch (\RuntimeException) {}
        $this->assertSame('error', $this->logs->query()->where('question', 'Failure')->first()->status);
    }

    public function test_completed_stream_exposes_log_id_and_persists_it_with_message(): void
    {
        $store = app(\App\Services\ChatbotConversationStore::class);
        $conversation = $store->create(81, 'project', '42', 'Study');
        $runner = Mockery::mock(\App\Services\ClinomicsChatAgentRunner::class);
        $runner->shouldNotReceive('stream');
        $controller = new class extends \App\Http\Controllers\ChatbotController {
            protected function resolveChatbotContext($scope, $id) {
                return ['status' => 'success', 'id' => 42, 'name' => 'Study'];
            }
            protected function resolveGeneSymbol($gene) { return 'FGFR4'; }
        };
        $request = Request::create('/chatbot', 'POST', ['query' => 'what genes similar to FGFR4']);
        $response = app(LogChatbotQuery::class)->handle($request, fn ($request) => $controller->streamMessage($request, $conversation['id'], $runner, $store));
        ob_start();
        ob_start();
        ($response->getCallback())();
        $tail = ob_get_clean();
        $output = ob_get_clean().$tail;
        $row = $this->logs->query()->first();
        $this->assertStringContainsString('"query_log_id":"'.$row->id.'"', $output);
        $this->assertSame($row->id, $store->get($conversation['id'], 81)['messages'][1]['meta']['query_log_id']);
        $this->assertSame('completed', $row->status);
        $this->assertNotEmpty($row->answer);
        $this->assertSame('rules', $row->provider);
    }

    public function test_admin_report_renders_escaped_text_and_accepts_end_date_only(): void
    {
        $this->authenticate(true);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $id = $this->record('<script>alert("question")</script>');
        $this->logs->feedback($id, 81, 5, '<img src=x onerror=alert(1)>');
        $response = app(ChatbotLogController::class)->report(Request::create('/', 'GET', ['to' => '2099-01-01']), $this->logs);
        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getContent();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_report_displays_profile_and_project_names_with_missing_record_fallbacks(): void
    {
        \Illuminate\Support\Facades\DB::table('user_profile')->insert(['user_id' => 81, 'first_name' => 'Jane', 'last_name' => 'Doe']);
        \Illuminate\Support\Facades\DB::table('projects')->insert(['id' => 42, 'name' => 'Example Study']);
        $id = $this->record();
        $this->logs->finish($id, ['scope' => 'project', 'cohort_id' => '42']);
        $row = $this->logs->search([])->items()[0];
        $this->assertSame('Jane Doe', $row->user_name);
        $this->assertSame('Example Study', $row->cohort_name);
        $this->authenticate(true);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $response = app(ChatbotLogController::class)->report(Request::create('/'), $this->logs);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Jane Doe', $response->getContent());
        $this->assertStringContainsString('Example Study', $response->getContent());
        $this->logs->finish($id, ['user_id' => 999, 'cohort_id' => '999']);
        $row = $this->logs->search([])->items()[0];
        $this->assertSame('User 999', $row->user_name);
        $this->assertSame('Project 999', $row->cohort_name);
        $this->logs->finish($id, ['scope' => 'cancer_type', 'cohort_id' => 'Neuroblastoma']);
        $this->assertSame('Neuroblastoma', $this->logs->search([])->items()[0]->cohort_name);
        $this->logs->finish($id, ['scope' => 'global', 'cohort_id' => 'all']);
        $this->assertSame('All authorized cohorts', $this->logs->search([])->items()[0]->cohort_name);
    }

    public function test_name_filter_matches_first_last_and_full_names_before_pagination(): void
    {
        \Illuminate\Support\Facades\DB::table('user_profile')->insert([
            ['user_id' => 81, 'first_name' => 'Jane', 'last_name' => 'Doe'],
            ['user_id' => 82, 'first_name' => 'John', 'last_name' => 'Doe'],
            ['user_id' => 83, 'first_name' => 'Jane', 'last_name' => null],
        ]);
        $jane = $this->record('Jane question');
        $john = $this->record('John question');
        $this->logs->finish($john, ['user_id' => 82]);
        foreach (['jAn', '  JANE   doE  ', 'Doe Jane'] as $name) {
            $rows = $this->logs->search(['user_name' => $name]);
            $this->assertSame(1, $rows->total());
            $this->assertSame($jane, $rows->items()[0]->id);
        }
        $this->assertSame(2, $this->logs->search(['user_name' => 'doe'])->total());
        $this->assertSame(0, $this->logs->search(['user_name' => 'Nobody'])->total());
        $this->assertSame(0, $this->logs->search(['user_name' => '%_'])->total());
        $this->assertSame(0, $this->logs->search(['user_name' => 'Jane', 'q' => 'John'])->total());
        $this->assertSame(2, $this->logs->search(['user_name' => '  '])->total());
        $this->authenticate(true);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $response = app(ChatbotLogController::class)->report(Request::create('/', 'GET', ['user_name' => 'Jane Doe']), $this->logs);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('name="user_name"', $response->getContent());
        $this->assertStringContainsString('value="Jane Doe"', $response->getContent());
        $this->assertStringContainsString('Jane question', $response->getContent());
        $this->assertStringNotContainsString('John question', $response->getContent());
    }

    public function test_results_are_paginated_and_sortable(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $id = $this->record('Question '.$i);
            $this->logs->finish($id, ['duration_ms' => $i]);
        }
        $rows = $this->logs->search(['sort' => 'slowest']);
        $this->assertSame(26, $rows->total());
        $this->assertCount(25, $rows->items());
        $this->assertSame('Question 25', $rows->items()[0]->question);
    }

    public function test_missing_table_does_not_break_chatbot(): void
    {
        Schema::connection('sqlite')->drop('chatbot_query_logs');
        $response = app(LogChatbotQuery::class)->handle(Request::create('/', 'POST', ['query' => 'Question']), fn () => response()->json(['summary' => 'Answer']));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($this->metrics->id());
    }
}
