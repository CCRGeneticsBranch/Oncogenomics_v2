<?php

$legacyEndpoint = strtolower(trim((string) env('LLM_ENDPOINT', '')));
$legacyProvider = strtolower(trim((string) env('LLM_PROVIDER', 'gemini')));
$inferredProvider = str_contains($legacyEndpoint, 'api.groq.com') ? 'groq' : $legacyProvider;
$primaryProvider = strtolower(trim((string) env('AI_PROVIDER', $inferredProvider)));
$legacyModel = trim((string) env('LLM_MODEL', ''));
$explicitAiModel = trim((string) env('AI_MODEL', ''));
$providerModels = [
    'groq' => trim((string) env(
        'GROQ_MODEL',
        $inferredProvider === 'groq' && $legacyModel !== '' ? $legacyModel : 'llama-3.3-70b-versatile',
    )),
    'gemini' => trim((string) env('GEMINI_MODEL', 'gemini-3.5-flash-lite')),
    'openai' => trim((string) env('OPENAI_MODEL', 'gpt-4o-mini')),
    'anthropic' => trim((string) env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest')),
];
if ($explicitAiModel !== '') {
    $providerModels[$primaryProvider] = $explicitAiModel;
} elseif ($primaryProvider === $inferredProvider && $legacyModel !== '') {
    $providerModels[$primaryProvider] = $legacyModel;
}

$defaultProviderOrder = [$primaryProvider];
$dedicatedProviderKeys = [
    'gemini' => trim((string) env('GEMINI_API_KEY', '')),
    'groq' => trim((string) env('GROQ_API_KEY', '')),
    'openai' => trim((string) env('OPENAI_API_KEY', '')),
    'anthropic' => trim((string) env('ANTHROPIC_API_KEY', '')),
];
foreach (['gemini', 'groq', 'openai', 'anthropic'] as $providerName) {
    $usesLegacyKey = $providerName === $inferredProvider
        && trim((string) env('LLM_API_KEY', '')) !== '';
    if ($providerName !== $primaryProvider
        && ($dedicatedProviderKeys[$providerName] !== '' || $usesLegacyKey)) {
        $defaultProviderOrder[] = $providerName;
    }
}

$configuredProviderOrder = trim((string) env('CHATBOT_AI_PROVIDERS', ''));
$providerOrder = $configuredProviderOrder !== ''
    ? array_values(array_filter(array_map(
        static fn (string $provider): string => strtolower(trim($provider)),
        explode(',', $configuredProviderOrder),
    )))
    : array_values(array_unique($defaultProviderOrder));
$orderedProviderModels = [];
foreach ($providerOrder as $providerName) {
    if (isset($providerModels[$providerName]) && $providerModels[$providerName] !== '') {
        $orderedProviderModels[$providerName] = $providerModels[$providerName];
    }
}

$projectScopeTools = [
    'expression_by_gene',
    'mutation_by_gene',
    'fusion_by_gene',
    'cnv_by_gene',
    'correlation_by_gene',
    'survival_by_expression',
    'getPCAData',
    'getCorrelationData',
    'getExpGeneSummary',
    'get_fusion_genes',
    'get_pathogeic_mutations',
    'get_project_cnv',
    'get_project_cases',
    'get_project_hla',
    'get_project_patients',
    'get_project_qc',
    'get_project_str',
    'get_project_sample_cases',
    'getCohortSamples',
    'getCohortChIPseq',
    'getCohortMutationGenes',
    'getCohortSchema',
    'runDifferentialExpression',
];

$cancerTypeScopeTools = [
    'getCohortSamples',
    'getCohortExpression',
    'getCohortChIPseq',
    'getCohortMutationGenes',
    'getFusionCancerTypeDetail',
];

// The home/global chatbot can resolve a named authorized cohort and then use
// every data tool available in either fixed page scope. Resolver tools remain
// first so their purpose is obvious in diagnostics and generated tool lists.
$globalScopeTools = array_values(array_unique([
    'getProjects',
    'getCancerTypes',
    ...$projectScopeTools,
    ...$cancerTypeScopeTools,
]));

return [
    'enabled' => (bool) env('CHATBOT', false),

    'agent' => [
        'enabled' => env('CHATBOT_AI_ENABLED', true),
        'provider' => $primaryProvider,
        'model' => $orderedProviderModels[$primaryProvider] ?? null,
        // Laravel AI retries failoverable startup failures (including HTTP
        // 429) in this order. Override with CHATBOT_AI_PROVIDERS when needed.
        'providers' => $orderedProviderModels,
        'max_steps' => (int) env('CHATBOT_AI_MAX_STEPS', 8),
        'timeout' => (int) env('CHATBOT_AI_TIMEOUT', 180),
        'temperature' => (float) env('CHATBOT_AI_TEMPERATURE', env('LLM_TEMPERATURE', 0)),
        'tool_preview_rows' => (int) env('CHATBOT_AI_TOOL_PREVIEW_ROWS', 75),
        'tool_preview_chars' => (int) env('CHATBOT_AI_TOOL_PREVIEW_CHARS', 30000),
        'artifact_max_bytes' => (int) env('CHATBOT_ARTIFACT_MAX_BYTES', 5242880),
    ],

    'conversations' => [
        // Conversation bodies live in the server-side cache because this app
        // uses cookie sessions and chat/tool output can be much larger than a
        // browser cookie. The default retains a thread for 30 days.
        'ttl_minutes' => (int) env('CHATBOT_CONVERSATION_TTL_MINUTES', 43200),
        'max_messages' => (int) env('CHATBOT_CONVERSATION_MAX_MESSAGES', 100),
        'recent_conversations' => (int) env('CHATBOT_RECENT_CONVERSATIONS', 30),
        'max_message_chars' => (int) env('CHATBOT_CONVERSATION_MAX_MESSAGE_CHARS', 100000),
        'max_document_bytes' => (int) env('CHATBOT_CONVERSATION_MAX_BYTES', 10485760),
        'max_query_chars' => (int) env('CHATBOT_QUERY_MAX_CHARACTERS', 8000),
        'agent_history_messages' => (int) env('CHATBOT_AGENT_HISTORY_MESSAGES', 24),
        'agent_history_chars' => (int) env('CHATBOT_AGENT_HISTORY_CHARACTERS', 48000),
        // Keep this slightly above the current 300-second PHP-FPM request
        // ceiling so a hard timeout does not block the user for many minutes.
        'run_lock_seconds' => (int) env('CHATBOT_RUN_LOCK_SECONDS', 360),
        'runs_per_minute' => (int) env('CHATBOT_RUNS_PER_MINUTE', 10),
    ],

    /*
    | MCP tools exposed to the LLM in each page scope. Tool names must match
    | the names advertised by the Onco MCP server exactly.
    */
    'scope_tools' => [
        'global' => $globalScopeTools,
        'project' => $projectScopeTools,
        'cancer_type' => $cancerTypeScopeTools,
    ],

    /*
    | Common user-facing diagnosis aliases. These are applied only after the
    | chatbot has classified the cohort as a cancer type; the MCP tool still
    | verifies that the canonical diagnosis is available to the current user.
    */
    'cohort_aliases' => [
        'project' => [
            'rnaseq landscape' => [
                'id' => 24421,
                'name' => 'RNAseq_Landscape_Manuscript',
            ],
            'rna landscape' => [
                'id' => 24421,
                'name' => 'RNAseq_Landscape_Manuscript',
            ],
        ],
        'cancer_type' => [
            'nb' => 'Neuroblastoma',
        ],
    ],
];
