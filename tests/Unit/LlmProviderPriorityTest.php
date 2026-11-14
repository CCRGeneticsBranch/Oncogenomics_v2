<?php

namespace Tests\Unit;

use App\Ai\Agents\ClinomicsChatAgent;
use App\Ai\Agents\ClinomicsResultSummarizer;
use App\Ai\Support\ChatbotRunContext;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class LlmProviderPriorityTest extends TestCase
{
    public function test_luna_agent_and_summarizer_omit_unsupported_temperature(): void
    {
        config()->set('ai.providers.openai.key', 'test-key');
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test',
                'status' => 'completed',
                'model' => 'gpt-6-luna',
                'output' => [[
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'OK', 'annotations' => []]],
                ]],
            ]),
        ]);
        $providers = ['openai' => 'gpt-6-luna', 'gemini' => 'gemini-test'];
        $summarizer = new ClinomicsResultSummarizer($providers, null, 10);
        $agent = new ClinomicsChatAgent(
            new ChatbotRunContext('global', 'all', 'All cohorts', 'Test'),
            [], $summarizer, $providers, null, 10, 2, 0.0,
            includeSummarizer: false,
        );

        foreach ([$agent, $summarizer] as $testAgent) {
            $this->assertSame('OK', $testAgent->prompt('Reply OK')->text);
        }
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame('gpt-6-luna', $request['model']);
            $this->assertArrayNotHasKey('temperature', $request->data());
        }
    }

    public function test_legacy_luna_request_uses_completion_token_limit_without_temperature(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]]),
        ]);
        $config = $this->llmConfig();
        $config['openai']['model'] = 'gpt-6-luna';

        $answer = (new ReflectionMethod(ProjectController::class, 'dispatchLlmTextRequest'))
            ->invoke(new ProjectController, 'Test prompt', $config);

        $this->assertSame('OK', $answer);
        $body = Http::recorded()[0][0]->data();
        $this->assertArrayHasKey('max_completion_tokens', $body);
        $this->assertArrayNotHasKey('max_tokens', $body);
        $this->assertArrayNotHasKey('temperature', $body);
    }

    private function llmConfig(): array
    {
        return [
            'provider' => 'openai',
            'api_key' => 'gsk_test',
            'endpoint' => 'https://api.groq.com/openai/v1',
            'openai' => [
                'api_key' => 'openai-test-key',
                'endpoint' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o-mini',
            ],
            'gemini' => [
                'api_key' => 'gemini-test-key',
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
                'model' => 'gemini-3.5-flash-lite',
            ],
        ];
    }

    public function test_openai_is_used_before_gemini_despite_legacy_groq_settings(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'OpenAI answer']]]]),
        ]);

        $answer = (new ReflectionMethod(ProjectController::class, 'dispatchLlmTextRequest'))
            ->invoke(new ProjectController, 'Test prompt', $this->llmConfig());

        $this->assertSame('OpenAI answer', $answer);
        Http::assertSentCount(1);
    }

    public function test_gemini_is_next_when_openai_fails(): void
    {
        $requests = [];
        Http::preventStrayRequests();
        Http::fake(function ($request) use (&$requests) {
            $requests[] = parse_url($request->url(), PHP_URL_HOST);
            if (count($requests) === 1) {
                return Http::response(['error' => ['message' => 'Unavailable']], 503);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Gemini answer']]]]]]);
        });

        $answer = (new ReflectionMethod(ProjectController::class, 'dispatchLlmTextRequest'))
            ->invoke(new ProjectController, 'Test prompt', $this->llmConfig());

        $this->assertSame('Gemini answer', $answer);
        $this->assertSame(['api.openai.com', 'generativelanguage.googleapis.com'], $requests);
    }

    public function test_plot_generation_tries_openai_then_gemini(): void
    {
        $providers = (new ReflectionMethod(ProjectController::class, 'availablePlotLlmProviders'))
            ->invoke(new ProjectController, $this->llmConfig());

        $this->assertSame(['openai', 'gemini'], array_slice($providers, 0, 2));
    }
}
