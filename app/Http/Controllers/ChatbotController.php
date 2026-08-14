<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Redirect;
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
    public function view(Request $request)
    {
        $scope = $this->normalizeChatbotScope($request->query('scope', 'global'));
        if ($scope === null) {
            $scope = 'global';
        }

        $cohortId = trim((string) $request->query('cohort_id', $scope === 'global' ? 'all' : ''));
        $query = trim((string) $request->query('query', ''));

        return View::make('pages/viewChatbot', [
            'chatbot_scope' => $scope,
            'chatbot_cohort_id' => $cohortId,
            'chatbot_query' => $query,
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

        if (!is_array($result)) {
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
}
