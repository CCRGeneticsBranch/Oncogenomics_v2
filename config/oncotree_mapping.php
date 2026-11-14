<?php

return [
    // Dev controls recipients, not database writes. Use --dry-run to prevent writes.
    'mode' => env('ONCOTREE_MAPPING_MODE', 'dev'),
    'taxonomy' => base_path('docs/oncotree.tsv'),
    'directory' => storage_path('app/oncotree-mapping'),
    'codex_binary' => env('ONCOTREE_CODEX_BINARY', 'codex'),
    // Empty uses the authenticated CLI user's default model.
    'model' => env('ONCOTREE_CODEX_MODEL', ''),
    'reasoning_effort' => env('ONCOTREE_CODEX_REASONING_EFFORT', 'high'),
    'timeout' => (int) env('ONCOTREE_CODEX_TIMEOUT', 1200),
    // Zero means all eligible unmapped diagnoses.
    'limit' => (int) env('ONCOTREE_MAPPING_LIMIT', 0),
    'retry_days' => (int) env('ONCOTREE_MAPPING_RETRY_DAYS', 30),
];
