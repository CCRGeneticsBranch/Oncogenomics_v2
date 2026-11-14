<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Request-local metrics; populated by SDK events and legacy provider adapters. */
class ChatbotQueryTelemetry
{
    private bool $active = false;
    private ?string $id = null;
    private int $started = 0;
    private array $calls = [];
    private array $tools = [];
    private array $result = [];
    private string $partialAnswer = '';

    public function __construct(private readonly ChatbotQueryLogStore $store) {}

    public function begin(int $userId, string $question, array $context = []): void
    {
        $this->active = true;
        $this->id = null;
        $this->calls = $this->tools = $this->result = [];
        $this->partialAnswer = '';
        $this->started = hrtime(true);
        $id = (string) Str::uuid();
        if ($this->store->insert([
            'id' => $id, 'user_id' => $userId, 'question' => $question,
            'conversation_id' => $context['conversation_id'] ?? null,
            'scope' => $context['scope'] ?? null, 'cohort_id' => isset($context['cohort_id']) ? (string) $context['cohort_id'] : null,
            'started_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            'status' => 'running', 'tools_json' => '[]', 'llm_calls_json' => '[]', 'usage_status' => 'unavailable',
        ])) $this->id = $id;
    }

    public function id(): ?string { return $this->active ? $this->id : null; }

    public function tool(string $name): void
    {
        if ($this->active && $name !== '') $this->tools[$name] = true;
    }

    public function llm(string $key, ?string $provider, ?string $model, array $usage): void
    {
        if (!$this->active) return;
        $number = static fn ($value) => is_numeric($value) && $value >= 0 ? (int) $value : null;
        $input = $number($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null);
        $output = $number($usage['completion_tokens'] ?? $usage['output_tokens'] ?? null);
        $this->calls[$key] = [
            'provider' => $provider, 'model' => $model,
            'input_tokens' => $input, 'output_tokens' => $output,
            'total_tokens' => $number($usage['total_tokens'] ?? ($input !== null && $output !== null ? $input + $output : null)),
            'cache_read_tokens' => $number($usage['cache_read_input_tokens'] ?? null),
            'cache_write_tokens' => $number($usage['cache_write_input_tokens'] ?? null),
            'reasoning_tokens' => $number($usage['reasoning_tokens'] ?? null),
        ];
    }

    public function legacyUsage(string $provider, string $model, array $body): void
    {
        $usage = (array) ($body['usage'] ?? []);
        if ($provider === 'gemini') {
            $raw = $body['usageMetadata'] ?? [];
            $usage = ['prompt_tokens' => $raw['promptTokenCount'] ?? null,
                'completion_tokens' => $raw['candidatesTokenCount'] ?? null, 'total_tokens' => $raw['totalTokenCount'] ?? null,
                'reasoning_tokens' => $raw['thoughtsTokenCount'] ?? null, 'cache_read_input_tokens' => $raw['cachedContentTokenCount'] ?? null];
        } else {
            $usage['reasoning_tokens'] = $usage['completion_tokens_details']['reasoning_tokens'] ?? null;
            $usage['cache_read_input_tokens'] = $usage['cache_read_input_tokens'] ?? $usage['prompt_tokens_details']['cached_tokens'] ?? null;
            $usage['cache_write_input_tokens'] = $usage['cache_creation_input_tokens'] ?? null;
        }
        $this->llm((string) Str::uuid(), $provider, $model, $usage);
    }

    public function observe(array $event): void
    {
        if (!$this->active) return;
        $type = $event['type'] ?? '';
        if ($type === 'tool_started' || $type === 'tool_finished') $this->tool((string) ($event['tool'] ?? ''));
        if ($type === 'answer_delta') $this->partialAnswer .= (string) ($event['delta'] ?? '');
        if ($type === 'answer_reset') $this->partialAnswer = '';
        if ($type === 'meta') {
            $this->result['provider'] = $event['provider'] ?? null;
            $this->result['model'] = $event['model'] ?? null;
        }
        if ($type === 'complete' || $type === 'error') {
            $this->result = array_merge($this->result, $event, ['status' => $type === 'error' ? 'error' : 'completed']);
            $this->result['answer'] = $type === 'error' ? (string) ($event['message'] ?? '') : (string) ($event['answer'] ?? '');
            foreach ($event['executions'] ?? [] as $execution) $this->tool((string) ($execution['tool'] ?? $execution['name'] ?? ''));
            // SDK listeners capture all completed attempts and synthesis calls.
            // Event usage is a fallback for mocked/custom runners only.
            if ($this->calls === [] && !empty($event['usage'])) {
                $this->llm('completion', $event['provider'] ?? null, $event['model'] ?? null, $event['usage']);
            }
            $this->persist();
        }
    }

    public function finish(?array $result = null, bool $failed = false): void
    {
        if (!$this->active) return;
        if ($result !== null) $this->observe($result + ['type' => $failed ? 'error' : 'complete']);
        if (!isset($this->result['status'])) {
            $this->result['status'] = $failed ? 'error' : 'interrupted';
            $this->result['answer'] = $this->partialAnswer !== '' ? $this->partialAnswer : null;
        }
        $this->persist();
        $this->active = false;
    }

    private function persist(): void
    {
        if ($this->id === null) return;
        $values = [
            'completed_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            'duration_ms' => (int) round((hrtime(true) - $this->started) / 1000000),
            'status' => $this->result['status'], 'answer' => $this->result['answer'] ?? null,
            'provider' => isset($this->result['provider']) ? mb_substr($this->result['provider'], 0, 128) : null,
            'model' => isset($this->result['model']) ? mb_substr($this->result['model'], 0, 255) : null,
            'tools_json' => json_encode(array_keys($this->tools), JSON_INVALID_UTF8_SUBSTITUTE),
            'llm_calls_json' => json_encode(array_values($this->calls), JSON_INVALID_UTF8_SUBSTITUTE),
        ];
        $calls = array_values($this->calls);
        $last = $calls === [] ? [] : $calls[array_key_last($calls)];
        $values['provider'] ??= $last['provider'] ?? null;
        $values['model'] ??= $last['model'] ?? null;
        foreach (['input_tokens', 'output_tokens', 'total_tokens', 'cache_read_tokens', 'cache_write_tokens', 'reasoning_tokens'] as $field) {
            $known = array_values(array_filter(array_column($calls, $field), static fn ($value) => $value !== null));
            $values[$field] = $known === [] ? null : array_sum($known);
        }
        $known = count(array_filter($calls, static fn ($call) => $call['total_tokens'] !== null));
        $values['usage_status'] = $known === 0 ? 'unavailable' : ($known === count($calls) ? 'reported' : 'partial');
        if ($calls === [] && ($values['provider'] ?? '') === 'rules') {
            $values['input_tokens'] = $values['output_tokens'] = $values['total_tokens'] = 0;
            $values['usage_status'] = 'not_applicable';
        }
        $this->store->finish($this->id, $values);
    }
}
