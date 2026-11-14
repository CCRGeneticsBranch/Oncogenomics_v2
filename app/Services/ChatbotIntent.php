<?php

namespace App\Services;

use App\Ai\Agents\ClinomicsIntentClassifier;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Request-scoped routing decision. No user-controlled routing markers. */
class ChatbotIntent
{
    private ?string $intent = null;

    public function current(): ?string { return $this->intent; }

    public function classify(string $question, array $history = []): string
    {
        $this->intent = 'unknown';
        $recent = [];
        foreach (array_slice($history, -6) as $message) {
            if (!in_array($message['role'] ?? '', ['user', 'assistant'], true)) continue;
            $recent[] = ['role' => $message['role'], 'content' => mb_substr((string) ($message['content'] ?? ''), 0, 1500)];
        }
        try {
            $providers = (array) config('chatbot.agent.providers', []);
            if ($providers === []) $providers = [(string) config('chatbot.agent.provider', 'gemini') => config('chatbot.agent.model')];
            $response = (new ClinomicsIntentClassifier)->prompt(
                json_encode(['current_question' => $question, 'recent_history' => $recent], JSON_INVALID_UTF8_SUBSTITUTE),
                provider: $providers, model: null, timeout: 20,
            );
            $intent = $response['intent'] ?? null;
            if (in_array($intent, ['documentation', 'data', 'mixed'], true)) $this->intent = $intent;
        } catch (Throwable $exception) {
            Log::warning('Chatbot intent classification unavailable; deferring tool selection to the agent.', [
                'exception' => $exception::class,
                'http_status' => $exception instanceof \Illuminate\Http\Client\RequestException ? $exception->response->status() : null,
            ]);
        }
        return $this->intent;
    }

    public function allowsRedirect(): bool { return $this->intent === 'data'; }
}
