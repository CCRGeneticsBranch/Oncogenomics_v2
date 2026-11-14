<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ChatbotConversationStore;
use App\Services\ChatbotQueryTelemetry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LogChatbotQuery
{
    public function handle(Request $request, Closure $next)
    {
        $user = User::getCurrentUser();
        $query = trim((string) $request->input('query', $request->route('query', '')));
        if ($user === null || $query === '') return $next($request);
        $context = [
            'scope' => $request->route('scope', 'project'),
            'cohort_id' => $request->route('cohort_id', $request->route('project_id')),
            'conversation_id' => $request->route('conversation_id'),
        ];
        if ($context['conversation_id']) {
            $conversation = app(ChatbotConversationStore::class)->get($context['conversation_id'], (int) $user->id);
            if ($conversation !== null) $context = array_merge($context, array_intersect_key($conversation, array_flip(['scope', 'cohort_id'])));
        }
        $log = app(ChatbotQueryTelemetry::class);
        $log->begin((int) $user->id, $query, $context);
        try {
            $response = $next($request);
            if ($response instanceof StreamedResponse) {
                $callback = $response->getCallback();
                $response->setCallback(function () use ($callback, $log): void {
                    $failed = false;
                    try { $callback(); }
                    catch (Throwable $exception) { $failed = true; throw $exception; }
                    finally { $log->finish(failed: $failed); }
                });
                return $response;
            }
            $data = method_exists($response, 'getOriginalContent') ? $response->getOriginalContent() : null;
            $data = $data instanceof \Illuminate\Contracts\View\View ? $data->getData() : (is_array($data) ? $data : []);
            $result = $data['agent_result'] ?? $data['result'] ?? [];
            $answer = $result['answer'] ?? $data['summary'] ?? $data['message'] ?? $result['summary'] ?? null;
            if ($response->isRedirect()) $answer ??= 'Opened analysis page: '.$response->headers->get('Location');
            $failed = $response->getStatusCode() >= 400 || ($result['status'] ?? '') === 'error';
            $log->finish(array_merge($result, ['answer' => $answer, 'message' => $answer]), $failed);
            return $response;
        } catch (Throwable $exception) {
            $log->finish(failed: true);
            throw $exception;
        }
    }
}
