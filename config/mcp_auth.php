<?php

$tokenUsers = [];
$mappingFile = storage_path('app/mcp_token_users.json');

if (is_file($mappingFile)) {
    $storedMappings = json_decode((string) file_get_contents($mappingFile), true);
    if (is_array($storedMappings)) {
        foreach ($storedMappings as $hash => $userId) {
            $hash = strtolower(trim((string) $hash));
            $userId = trim((string) $userId);
            if (preg_match('/^[a-f0-9]{64}$/', $hash) && ctype_digit($userId)) {
                $tokenUsers[$hash] = (int) $userId;
            }
        }
    }
}

foreach (explode(',', (string) env('MCP_TOKEN_USERS', '')) as $mapping) {
    [$hash, $userId] = array_pad(explode(':', trim($mapping), 2), 2, null);
    $hash = strtolower(trim((string) $hash));
    $userId = trim((string) $userId);
    if (preg_match('/^[a-f0-9]{64}$/', $hash) && ctype_digit($userId)) {
        $tokenUsers[$hash] = (int) $userId;
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Accepted client token hashes
    |--------------------------------------------------------------------------
    |
    | Legacy client tokens without a mapped user. Prefer token_users for new
    | clients so controller permission checks receive an authenticated user.
    |
    */
    'token_hashes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_TOKEN_HASHES', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Client token user mapping
    |--------------------------------------------------------------------------
    |
    | Each SHA-256 token hash maps to the Clinomics user whose existing project
    | permissions are used for that MCP client. MCP_TOKEN_USERS format:
    |
    |     <sha256>:<user_id>,<sha256>:<user_id>
    |
    | The server-side issue command stores mappings in
    | storage/app/mcp_token_users.json. Plaintext tokens are never stored.
    |
    */
    'token_users' => $tokenUsers,

    /*
    |--------------------------------------------------------------------------
    | Internal application token
    |--------------------------------------------------------------------------
    |
    | The token this application's own chatbot uses to call the MCP server
    | internally over HTTP. Set MCP_INTERNAL_TOKEN in your .env to any secret
    | string. It is accepted directly by the middleware, so its hash does NOT
    | need to be listed in token_hashes above.
    |
    */
    'internal_token' => (string) env('MCP_INTERNAL_TOKEN', ''),

];
