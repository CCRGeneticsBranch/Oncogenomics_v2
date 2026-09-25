<?php

namespace App\Http\Controllers;

use App\Ai\Support\ChatbotToolPolicy;
use App\Ai\Support\ExplicitChatbotCohort;
use App\Models\User;
use App\Services\ChatbotConversationStore;
use App\Services\ChatbotMarkdownRenderer;
use App\Services\ClinomicsChatAgentRunner;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Redirect;
use Throwable;
use View;

/**
 * Scope-aware chatbot entry point.
 *
 * The mature project chatbot engine still lives in ProjectController for
 * backward compatibility. This controller owns the general HTTP contract and
 * restricts each page scope to its configured MCP catalog.
 */
class ChatbotController extends ProjectController
{
    public function view(
        Request $request,
        ?ChatbotConversationStore $conversations = null,
        ?ChatbotMarkdownRenderer $markdown = null,
    ) {
        $conversations ??= app(ChatbotConversationStore::class);
        $markdown ??= app(ChatbotMarkdownRenderer::class);
        $userId = $this->currentChatbotUserId();
        if ($userId === null) {
            return View::make('pages/error_no_header', [
                'message' => 'Please sign in before using the Clinomics chatbot.',
            ]);
        }

        $embedded = $request->boolean('embedded');
        $scope = $this->normalizeChatbotScope($request->input('scope', 'global'));
        if ($scope === null) {
            $scope = 'global';
        }

        $cohortId = trim((string) $request->input('cohort_id', $scope === 'global' ? 'all' : ''));
        $query = trim((string) $request->input('query', ''));
        $maxQueryCharacters = max(100, (int) config('chatbot.conversations.max_query_chars', 8000));
        if (mb_strlen($query) > $maxQueryCharacters) {
            return View::make('pages/error_no_header', [
                'message' => "The question may not exceed {$maxQueryCharacters} characters.",
            ]);
        }
        $conversationId = trim((string) $request->input('conversation_id', ''));
        $conversationId = $conversationId !== '' ? $conversationId : null;

        if ($conversationId !== null) {
            $conversation = $conversations->get($conversationId, $userId);
            if ($conversation === null) {
                return View::make('pages/error_no_header', [
                    'message' => 'This chatbot conversation was not found or is no longer available.',
                ]);
            }
            $scope = $this->normalizeChatbotScope($conversation['scope'] ?? null);
            $cohortId = (string) ($conversation['cohort_id'] ?? '');
        }

        if ($scope === null) {
            return View::make('pages/error_no_header', ['message' => 'Unsupported chatbot scope.']);
        }

        $context = $this->resolveChatbotContext($scope, $cohortId);
        if (($context['status'] ?? null) !== 'success') {
            return View::make('pages/error_no_header', [
                'message' => $context['message'] ?? 'The chatbot context is unavailable.',
            ]);
        }

        if ($conversationId === null) {
            $conversation = $conversations->open(
                $userId,
                $scope,
                $context['id'],
                $context['name'],
                startNew: $request->boolean('new'),
            );
        }

        $conversationId = (string) $conversation['id'];
        $conversationParameters = ['conversation_id' => $conversationId];
        $newConversationParameters = [
            'scope' => $scope,
            'cohort_id' => $context['id'],
            'new' => 1,
        ];
        if ($embedded) {
            $conversationParameters['embedded'] = 1;
            $newConversationParameters['embedded'] = 1;
        }
        $conversationUrl = url('/viewChatbot').'?'.http_build_query($conversationParameters);
        $newConversationUrl = url('/viewChatbot').'?'.http_build_query($newConversationParameters);

        $messages = array_map(static function (array $message) use ($markdown): array {
            if (($message['role'] ?? null) === 'assistant') {
                $message['content_html'] = $markdown->render((string) ($message['content'] ?? ''));
            }

            return $message;
        }, (array) ($conversation['messages'] ?? []));

        return View::make('pages/viewChatbot', [
            'chatbot_scope' => $scope,
            'chatbot_cohort_id' => (string) $context['id'],
            'chatbot_context_name' => $context['name'],
            'chatbot_query' => $query,
            'chatbot_conversation_id' => $conversationId,
            'chatbot_messages' => $messages,
            'chatbot_recent_conversations' => $conversations->recent($userId),
            'chatbot_stream_url' => url('/chatbot/conversations/'.$conversationId.'/messages'),
            'chatbot_conversation_url' => $conversationUrl,
            'chatbot_new_url' => $newConversationUrl,
            'chatbot_embedded' => $embedded,
        ]);
    }

    public function streamMessage(
        Request $request,
        string $conversationId,
        ClinomicsChatAgentRunner $runner,
        ChatbotConversationStore $conversations,
        ?ChatbotMarkdownRenderer $markdown = null,
    ) {
        $markdown ??= app(ChatbotMarkdownRenderer::class);
        $userId = $this->currentChatbotUserId();
        if ($userId === null) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }

        $conversation = $conversations->get($conversationId, $userId);
        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $scope = $this->normalizeChatbotScope($conversation['scope'] ?? null);
        if ($scope === null) {
            return response()->json(['message' => 'Conversation scope is invalid.'], 422);
        }

        $context = $this->resolveChatbotContext($scope, (string) ($conversation['cohort_id'] ?? ''));
        if (($context['status'] ?? null) !== 'success') {
            return response()->json([
                'message' => $context['message'] ?? 'The chatbot context is no longer available.',
            ], 403);
        }

        $query = trim((string) $request->input('query', ''));
        $maxQueryCharacters = max(100, (int) config('chatbot.conversations.max_query_chars', 8000));
        if ($query === '') {
            return response()->json(['message' => 'Please enter a question.'], 422);
        }
        if (mb_strlen($query) > $maxQueryCharacters) {
            return response()->json([
                'message' => "The question may not exceed {$maxQueryCharacters} characters.",
            ], 422);
        }

        $messageId = trim((string) $request->input('message_id', ''));
        if ($messageId !== '' && ! Str::isUuid($messageId)) {
            return response()->json(['message' => 'The message ID is invalid.'], 422);
        }

        $runLock = $conversations->acquireRunLock($conversationId, $userId);
        if ($runLock === null) {
            return response()->json([
                'message' => 'You already have a chatbot question running.',
            ], 409);
        }

        $rateLimitKey = 'clinomics-chat:user:'.$userId;
        $runsPerMinute = max(1, (int) config('chatbot.conversations.runs_per_minute', 10));
        if (RateLimiter::tooManyAttempts($rateLimitKey, $runsPerMinute)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);
            $runLock->release();

            return response()->json([
                'message' => "Too many chatbot questions. Please retry in {$retryAfter} second(s).",
            ], 429, ['Retry-After' => (string) $retryAfter]);
        }
        RateLimiter::hit($rateLimitKey, 60);

        $history = $conversations->historyForAgent($conversation);
        $agentQuery = $this->agentQueryWithExplicitCohort($scope, $query);
        $agentQuery = $this->agentQueryWithOmittedCohort($scope, $agentQuery, $history, $conversation);
        $userMessage = $conversations->appendUserMessage(
            $conversationId,
            $userId,
            $query,
            $messageId !== '' ? $messageId : null,
        );
        if ($userMessage === null) {
            $runLock->release();

            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        return response()->stream(function () use (
            $runner,
            $conversations,
            $runLock,
            $conversationId,
            $userId,
            $scope,
            $context,
            $query,
            $agentQuery,
            $history,
            $userMessage,
            $markdown,
        ): void {
            // Preserve the established in-process completion policy so a
            // disconnected browser does not leave a half-written turn.
            ignore_user_abort(true);
            $activity = [];
            $answerStarted = false;
            $toolAttempted = false;
            $toolCompleted = false;

            $emit = static function (array $event): void {
                echo json_encode(
                    $event,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                )."\n";

                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $emit([
                'type' => 'accepted',
                'message_id' => $userMessage['id'],
                'conversation_id' => $conversationId,
            ]);

            try {
                foreach ($runner->stream(
                    $scope,
                    $context['id'],
                    $context['name'],
                    $agentQuery,
                    $history,
                ) as $event) {
                    $type = (string) ($event['type'] ?? '');
                    if (in_array($type, ['status', 'tool_started', 'tool_finished'], true)) {
                        $activity[] = $event;
                    }
                    if ($type === 'answer_delta') {
                        $answerStarted = true;
                    }
                    if ($type === 'answer_reset') {
                        $answerStarted = false;
                    }
                    if ($type === 'tool_started') {
                        $toolAttempted = true;
                    }
                    if ($type === 'tool_finished') {
                        $toolAttempted = true;
                        $toolCompleted = true;
                    }

                    if ($type === 'complete') {
                        $event['answer_html'] = $markdown->render((string) ($event['answer'] ?? ''));
                        $meta = [
                            'provider' => $event['provider'] ?? null,
                            'model' => $event['model'] ?? null,
                            'steps' => $event['steps'] ?? 0,
                            'tool_calls' => $event['tool_calls'] ?? 0,
                            'used_summarizer' => $event['used_summarizer'] ?? false,
                            'usage' => $event['usage'] ?? [],
                            'activity' => $activity,
                            'executions' => $event['executions'] ?? [],
                        ];
                        $assistant = $conversations->appendAssistantMessage(
                            $conversationId,
                            $userId,
                            (string) ($event['answer'] ?? ''),
                            $meta,
                        );
                        $event['message_id'] = $assistant['id'] ?? null;
                    }

                    $emit($event);
                }
            } catch (Throwable $exception) {
                $publicException = $exception;
                Log::warning('Streaming Laravel AI chatbot failed.', [
                    'conversation_id' => $conversationId,
                    'scope' => $scope,
                    'cohort_id' => $context['id'],
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    ...$this->providerFailureLogContext($exception),
                ]);

                if (! $answerStarted && ! $toolAttempted && ! $toolCompleted) {
                    $fallbackStarted = [
                        'type' => 'tool_started',
                        'tool_id' => 'compatibility-fallback',
                        'tool' => 'compatibility_mcp_engine',
                        'arguments' => [],
                    ];
                    $activity[] = $fallbackStarted;
                    $emit([
                        'type' => 'status',
                        'message' => 'The primary AI provider is unavailable; trying the compatibility MCP engine.',
                    ]);
                    $emit($fallbackStarted);

                    try {
                        $legacyResult = $this->runMcpWithLlmToolSelection(
                            $context['id'],
                            $this->compatibilitySelectionQuery($agentQuery, $history),
                            $scope,
                        );
                        if (is_array($legacyResult) && ($legacyResult['status'] ?? null) !== 'error') {
                            $execution = $runner->presentCompatibilityResult($legacyResult, $query);
                            $fallbackFinished = [
                                'type' => 'tool_finished',
                                'tool_id' => 'compatibility-fallback',
                                'tool' => 'compatibility_mcp_engine',
                                'successful' => true,
                                'status' => $legacyResult['status'] ?? 'success',
                                'summary' => mb_substr((string) (
                                    $legacyResult['summary'] ?? $legacyResult['message'] ?? 'Compatibility result received.'
                                ), 0, 300),
                            ];
                            if (isset($execution['table']['row_count'])) {
                                $fallbackFinished['row_count'] = $execution['table']['row_count'];
                            }
                            $activity[] = $fallbackFinished;
                            $emit($fallbackFinished);

                            $answer = $this->compatibilityResultAnswer($legacyResult, $execution);
                            $complete = [
                                'type' => 'complete',
                                'answer' => $answer,
                                'provider' => $this->chatbotLlmTrace['provider'] ?? 'compatibility',
                                'model' => $this->chatbotLlmTrace['model'] ?? null,
                                'steps' => 1,
                                'tool_calls' => 1,
                                'used_summarizer' => false,
                                'usage' => [],
                                'executions' => $execution !== [] ? [$execution] : [],
                                'fallback' => true,
                            ];
                            $complete['answer_html'] = $markdown->render($answer);
                            $assistant = $conversations->appendAssistantMessage(
                                $conversationId,
                                $userId,
                                $answer,
                                [
                                    'provider' => $complete['provider'],
                                    'model' => $complete['model'],
                                    'steps' => 1,
                                    'tool_calls' => 1,
                                    'used_summarizer' => false,
                                    'usage' => [],
                                    'activity' => $activity,
                                    'executions' => $complete['executions'],
                                    'fallback' => true,
                                ],
                            );
                            $complete['message_id'] = $assistant['id'] ?? null;
                            $emit($complete);

                            return;
                        }
                    } catch (Throwable $fallbackException) {
                        if ($this->providerLimitMessage($fallbackException) !== null) {
                            $publicException = $fallbackException;
                        }
                        Log::warning('Compatibility MCP chatbot fallback failed.', [
                            'conversation_id' => $conversationId,
                            'scope' => $scope,
                            'cohort_id' => $context['id'],
                            'exception' => $fallbackException::class,
                            'message' => $fallbackException->getMessage(),
                        ]);
                    }
                }

                $message = $this->publicStreamingError($publicException, $answerStarted);
                $conversations->appendAssistantMessage(
                    $conversationId,
                    $userId,
                    $message,
                    ['failed' => true, 'activity' => $activity],
                );
                $emit(['type' => 'error', 'message' => $message]);
            } finally {
                $runLock->release();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function run($scope, $cohortId, $query)
    {
        $scope = $this->normalizeChatbotScope($scope);
        if ($scope === null) {
            return View::make('pages/error_no_header', ['message' => 'Unsupported chatbot scope.']);
        }

        $query = trim(urldecode((string) $query));
        if ($query === '') {
            return View::make('pages/error_no_header', ['message' => 'Please enter a query.']);
        }

        $context = $this->resolveChatbotContext($scope, $cohortId);
        if (($context['status'] ?? null) !== 'success') {
            return View::make('pages/error_no_header', [
                'message' => $context['message'] ?? 'The chatbot context is unavailable.',
            ]);
        }

        try {
            $agentResult = app(ClinomicsChatAgentRunner::class)->run(
                $scope,
                $context['id'],
                $context['name'],
                $query,
            );

            return View::make('pages/chatbotAgentResult', [
                'query' => $query,
                'context_name' => $context['name'],
                'scope' => $scope,
                'agent_result' => $agentResult,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Laravel AI chatbot failed; using the legacy chatbot engine.', [
                'scope' => $scope,
                'cohort_id' => $context['id'],
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($scope === 'project') {
            return $this->runProjectChatbot($context['id'], $query);
        }

        $this->resetChatbotLlmDiagnostics();
        $result = $this->runMcpWithLlmToolSelection($cohortId, $query, $scope);
        $trace = $this->buildChatbotTrace(
            'llm',
            $this->chatbotLlmTrace['provider'] ?? null,
            $this->chatbotLlmTrace['model'] ?? null
        );

        if (! is_array($result)) {
            return View::make('pages/error_no_header', [
                'message' => "No tool available in the {$scope} chatbot scope could answer this question.",
            ]);
        }
        if (($result['status'] ?? null) === 'error') {
            return View::make('pages/error_no_header', [
                'message' => $result['message'] ?? 'MCP tool execution failed.',
            ]);
        }
        if ($this->isGenericTableResult($result)) {
            return $this->displayScopedTableResult($context, $result, $trace);
        }
        if (isset($result['redirect_url'])) {
            return Redirect::to($this->appendChatbotTraceToUrl($result['redirect_url'], $trace));
        }

        return View::make('pages/chatbotStructuredResult', [
            'title' => $result['title'] ?? 'Results',
            'summary' => $result['summary'] ?? '',
            'context_name' => $context['name'],
            'result' => $result,
            'trace_mode' => $trace['mode'] ?? null,
            'trace_provider' => $trace['provider'] ?? null,
            'trace_model' => $trace['model'] ?? null,
        ]);
    }

    private function currentChatbotUserId(): ?int
    {
        $user = User::getCurrentUser();
        $userId = $user !== null ? (int) ($user->id ?? 0) : 0;

        return $userId > 0 ? $userId : null;
    }

    private function publicStreamingError(Throwable $exception, bool $answerStarted): string
    {
        $limitMessage = $this->providerLimitMessage($exception);
        if ($limitMessage !== null) {
            return $limitMessage;
        }

        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'The analysis timed out before it could finish. Please retry or narrow the question.';
        }
        if ($answerStarted) {
            return 'The response stream ended before the analysis could finish. Please retry the question.';
        }

        return 'The chatbot could not complete this request. Please try again.';
    }

    private function providerLimitMessage(Throwable $exception): ?string
    {
        $status = null;
        $providerMessage = '';
        if ($exception instanceof RequestException) {
            $status = $exception->response->status();
            $providerMessage = trim((string) data_get(
                $exception->response->json(),
                'error.message',
                '',
            ));
        }

        $combined = trim($exception->getMessage().' '.$providerMessage);
        $isLimit = $status === 429
            || preg_match('/\b429\b|rate[ _-]?limit|resource[_ -]?exhausted|tokens? per minute|too many requests|quota exceeded/i', $combined) === 1;
        if ($isLimit) {
            if (preg_match('/\bLimit\s+([\d,]+).*?\bRequested\s+([\d,]+)/i', $combined, $matches) === 1) {
                $limit = (int) str_replace(',', '', $matches[1]);
                $requested = (int) str_replace(',', '', $matches[2]);
                $period = preg_match('/tokens? per minute|\bTPM\b/i', $combined) === 1
                    ? ' per minute'
                    : '';

                return sprintf(
                    'The AI provider token limit was exceeded: requested %s tokens; limit %s tokens%s. Please narrow the question or retry after the limit resets.',
                    number_format($requested),
                    number_format($limit),
                    $period,
                );
            }

            return 'The AI provider rate or quota limit was reached. Please wait for the limit to reset, or narrow the question and try again.';
        }

        if ($status === 413 || preg_match('/request too large|context length|maximum input tokens/i', $combined) === 1) {
            return 'The AI provider request-size limit was exceeded. Please start a new chat or narrow the question.';
        }

        return null;
    }

    /** @return array<string, int|string> */
    private function providerFailureLogContext(Throwable $exception): array
    {
        if (! $exception instanceof RequestException) {
            return [];
        }

        $providerMessage = trim((string) data_get(
            $exception->response->json(),
            'error.message',
            '',
        ));

        return array_filter([
            'http_status' => $exception->response->status(),
            'provider_error' => $providerMessage !== '' ? mb_substr($providerMessage, 0, 500) : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @param array<string, mixed> $result */
    private function compatibilityResultAnswer(array $result, array $execution = []): string
    {
        $answer = trim((string) ($result['summary'] ?? $result['message'] ?? ''));
        if ($answer !== '') {
            return $answer;
        }

        $title = trim((string) ($result['title'] ?? ''));
        if ($title !== '') {
            return $title.' is shown in the result evidence below.';
        }

        $executionSummary = trim((string) ($execution['summary'] ?? ''));
        if ($executionSummary !== '') {
            return mb_substr($executionSummary, 0, 12000);
        }

        return 'The compatibility MCP engine returned the result evidence below.';
    }

    /**
     * The compatibility selector is stateless. Supply only a bounded set of
     * prior conversational turns so short follow-ups retain their gene,
     * expression unit, transform, plot, and grouping context.
     *
     * @param  array<int, array{role?: string, content?: string}>  $history
     */
    private function compatibilitySelectionQuery(string $query, array $history): string
    {
        if (! ChatbotToolPolicy::needsConversationContext($query)) {
            return $query;
        }

        $lines = [];
        foreach (array_slice($history, -6) as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $role = ($message['role'] ?? null) === 'assistant' ? 'Assistant' : 'User';
            $lines[] = $role.': '.mb_substr($content, 0, 2000);
        }

        if ($lines === []) {
            return $query;
        }

        return "Conversation context:\n"
            .implode("\n", $lines)
            ."\nCurrent user request: {$query}";
    }

    /**
     * Carry forward only a known omitted cohort for an expression-presentation
     * refinement. The current gene, modality, transform, and plot remain
     * authoritative and are never copied from history here.
     *
     * @param  array<int, array{role?: string, content?: string}>  $history
     */
    private function agentQueryWithOmittedCohort(
        string $scope,
        string $query,
        array $history,
        array $conversation = [],
    ): string {
        if ($scope !== 'global'
            || ExplicitChatbotCohort::hasResolvedProject($query)
            || ! ChatbotToolPolicy::asksForExpression($query)
            || preg_match('/\b(z[ -]?score|log\s*2|violin|box\s*plot|heat\s*map|bar\s*(?:plot|chart)|column\s*(?:plot|chart))\b/i', $query) !== 1) {
            return $query;
        }

        $aliases = (array) config('chatbot.cohort_aliases.cancer_type', []);
        foreach ($aliases as $alias => $canonical) {
            if (preg_match('/\b(?:'.preg_quote((string) $alias, '/').'|'.preg_quote((string) $canonical, '/').')\b/i', $query) === 1) {
                return $query;
            }
        }
        // An explicit "... project" or "in ..." phrase in the current
        // request wins over prior conversation context, even when the named
        // cohort is not one of the small configured alias sets.
        if (preg_match('/\bproject\b|\bin\s+(?!(?:ascending|descending|order)\b)[A-Za-z0-9]/i', $query) === 1) {
            return $query;
        }

        $previousCohort = $this->previousStructuredCohort($conversation);
        if (($previousCohort['type'] ?? null) === 'project') {
            return ExplicitChatbotCohort::appendProjectContext($query, [
                'id' => (int) $previousCohort['id'],
                'name' => (string) $previousCohort['name'],
            ]);
        }
        if (($previousCohort['type'] ?? null) === 'cancer_type') {
            return ExplicitChatbotCohort::appendCancerTypeContext(
                $query,
                (string) $previousCohort['id'],
            );
        }

        foreach (array_reverse($history) as $message) {
            if (($message['role'] ?? null) !== 'user') {
                continue;
            }
            $content = (string) ($message['content'] ?? '');
            foreach ($aliases as $alias => $canonical) {
                if (preg_match('/\b(?:'.preg_quote((string) $alias, '/').'|'.preg_quote((string) $canonical, '/').')\b/i', $content) !== 1) {
                    continue;
                }

                return $query."\nServer conversation context: the omitted cohort remains cancer type ".trim((string) $canonical).'. Use this only as the cohort; the current request controls every other option.';
            }
        }

        return $query;
    }

    /**
     * Read the last successfully executed cohort from this owned conversation.
     * This is stronger than inferring a cohort from generated assistant prose.
     *
     * @param  array<string, mixed>  $conversation
     * @return array{type: string, id: int|string, name: string}|null
     */
    private function previousStructuredCohort(array $conversation): ?array
    {
        $activeCohort = null;
        $pendingUserMessage = null;
        foreach ((array) ($conversation['messages'] ?? []) as $message) {
            if (($message['role'] ?? null) === 'user') {
                $pendingUserMessage = (string) ($message['content'] ?? '');

                continue;
            }
            if (($message['role'] ?? null) !== 'assistant' || $pendingUserMessage === null) {
                continue;
            }

            $candidate = $this->structuredCohortFromExecutions(
                (array) ($message['meta']['executions'] ?? []),
            );
            if ($candidate !== null
                && $this->userExplicitlyNamedCohort($pendingUserMessage, $candidate)) {
                $activeCohort = $candidate;
            }
            $pendingUserMessage = null;
        }

        return $activeCohort;
    }

    /**
     * @param  array<int, mixed>  $executions
     * @return array{type: string, id: int|string, name: string}|null
     */
    private function structuredCohortFromExecutions(array $executions): ?array
    {
        foreach (array_reverse($executions) as $execution) {
            if (($execution['status'] ?? null) !== 'success') {
                continue;
            }
            $arguments = (array) ($execution['arguments'] ?? []);
            $preview = (array) ($execution['preview'] ?? []);
            $cohortType = strtolower(trim((string) ($arguments['cohort_type'] ?? $preview['cohort_type'] ?? '')));
            $cohortId = $arguments['cohort_id'] ?? $preview['cohort_id'] ?? null;

            $projectId = $arguments['project_id'] ?? $preview['project_id'] ?? null;
            if ($cohortType === 'project') {
                $projectId = $cohortId;
            }
            $projectId = filter_var($projectId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($projectId !== false) {
                $projectName = trim((string) ($preview['project_name'] ?? $preview['cohort_name'] ?? ''));

                return [
                    'type' => 'project',
                    'id' => (int) $projectId,
                    'name' => $projectName !== '' ? $projectName : 'Project '.(int) $projectId,
                ];
            }

            $cancerType = trim((string) (
                $cohortType === 'cancer_type'
                    ? $cohortId
                    : ($arguments['cancer_type_id'] ?? $preview['cancer_type_id'] ?? '')
            ));
            if ($cancerType !== '') {
                return ['type' => 'cancer_type', 'id' => $cancerType, 'name' => $cancerType];
            }
        }

        return null;
    }

    /** @param array{type: string, id: int|string, name: string} $cohort */
    private function userExplicitlyNamedCohort(string $query, array $cohort): bool
    {
        $normalizedQuery = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $query));
        $normalizedName = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $cohort['name']));
        if ($normalizedName !== '' && str_contains(' '.$normalizedQuery.' ', ' '.$normalizedName.' ')) {
            return true;
        }
        if ($cohort['type'] === 'project' && preg_match('/\bproject\b/i', $query) === 1) {
            return true;
        }

        if ($cohort['type'] === 'cancer_type') {
            foreach ((array) config('chatbot.cohort_aliases.cancer_type', []) as $alias => $canonical) {
                if (strcasecmp(trim((string) $canonical), trim((string) $cohort['id'])) === 0
                    && preg_match('/\b'.preg_quote((string) $alias, '/').'\b/i', $query) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function agentQueryWithExplicitCohort(string $scope, string $query): string
    {
        if ($scope !== 'global') {
            return $query;
        }

        $project = ExplicitChatbotCohort::projectFromUserQuery($query);

        return $project === null
            ? $query
            : ExplicitChatbotCohort::appendProjectContext($query, $project);
    }
}
