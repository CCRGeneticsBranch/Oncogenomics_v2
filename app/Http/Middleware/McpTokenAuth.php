<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class McpTokenAuth
{
    public const INTERNAL_USER_ID_HEADER = 'X-Clinomics-Mcp-User-Id';

    /**
     * Protect the MCP server with a bearer token and establish any user mapped
     * to that token as the request-scoped Sentry principal.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $internal = (string) config('mcp_auth.internal_token', '');
        $hashes = (array) config('mcp_auth.token_hashes', []);
        $tokenUsers = (array) config('mcp_auth.token_users', []);
        $presented = trim((string) $request->bearerToken());

        if ($internal === ''
            && $this->normalizedHashes($hashes) === []
            && $tokenUsers === []) {
            if (! (bool) config('mcp_auth.allow_unprotected', false)) {
                Log::error('MCP request rejected because no authentication tokens are configured.');

                return $this->unauthorized('Unauthorized: MCP authentication is not configured.');
            }

            Log::warning('MCP endpoint is UNPROTECTED by explicit configuration.');

            return $next($request);
        }

        if ($presented === '' || ! $this->isValidToken($presented, $internal, $hashes, $tokenUsers)) {
            return $this->unauthorized('Unauthorized: a valid MCP bearer token is required.');
        }

        $presentedHash = hash('sha256', $presented);
        $isInternalRequest = $internal !== '' && hash_equals($internal, $presented);
        $userId = $tokenUsers[$presentedHash] ?? null;

        // The browser chatbot calls this endpoint through the application's
        // private internal token. Carry its already-authenticated Sentry user
        // into that second HTTP request. Never honor this header for external
        // tokens: their identity remains fixed by token_users.
        if ($isInternalRequest) {
            $delegatedUserId = trim((string) $request->header(self::INTERNAL_USER_ID_HEADER, ''));
            if ($delegatedUserId !== '') {
                if (! ctype_digit($delegatedUserId) || (int) $delegatedUserId < 1) {
                    return $this->unauthorized('Unauthorized: the delegated MCP user ID is invalid.');
                }

                $userId = (int) $delegatedUserId;
            }
        }

        if ($userId !== null && ! $this->setRequestUser($request, (int) $userId, $presentedHash)) {
            return $this->unauthorized('Unauthorized: the MCP token user is unavailable.');
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $hashes
     * @param  array<string, int>  $tokenUsers
     */
    private function isValidToken(string $presented, string $internal, array $hashes, array $tokenUsers): bool
    {
        if ($internal !== '' && hash_equals($internal, $presented)) {
            return true;
        }

        $presentedHash = hash('sha256', $presented);
        if (array_key_exists($presentedHash, $tokenUsers)) {
            return true;
        }

        foreach ($this->normalizedHashes($hashes) as $allowedHash) {
            if (hash_equals($allowedHash, $presentedHash)) {
                return true;
            }
        }

        return false;
    }

    private function setRequestUser(Request $request, int $userId, string $tokenHash): bool
    {
        try {
            // Resolve through Sentry's configured provider so the same user
            // model and connection are used by browser and MCP authentication.
            $sentry = app()->make('sentry');
            $user = $sentry->findUserById($userId);

            if ($user === null || (! empty($user->banned))) {
                return false;
            }

            $sentry->setUser($user);
            $request->attributes->set('mcp_user', $user);
            $request->attributes->set('mcp_user_id', $userId);
            $request->attributes->set('mcp_token_hash', $tokenHash);
            $request->setUserResolver(static fn () => $user);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Unable to establish MCP user context.', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<int, string>  $hashes
     * @return array<int, string>
     */
    private function normalizedHashes(array $hashes): array
    {
        return array_values(array_filter(array_map(
            static fn ($hash): string => strtolower(trim((string) $hash)),
            $hashes
        )));
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => $message,
            ],
            'id' => null,
        ], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
