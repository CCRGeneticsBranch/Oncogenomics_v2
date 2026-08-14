<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class McpTokenAuth
{
    /**
     * Protect the MCP server with a bearer token and establish any user mapped
     * to that token as the request-scoped Sentry principal.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $internal = (string) config('mcp_auth.internal_token', '');
        $hashes = (array) config('mcp_auth.token_hashes', []);
        $tokenUsers = (array) config('mcp_auth.token_users', []);

        if ($internal === '' && $this->normalizedHashes($hashes) === [] && $tokenUsers === []) {
            Log::warning('MCP endpoint is UNPROTECTED: no MCP tokens configured.');

            return $next($request);
        }

        $presented = trim((string) $request->bearerToken());

        if ($presented === '' || ! $this->isValidToken($presented, $internal, $hashes, $tokenUsers)) {
            return $this->unauthorized('Unauthorized: a valid MCP bearer token is required.');
        }

        $presentedHash = hash('sha256', $presented);
        $userId = $tokenUsers[$presentedHash] ?? null;

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
            // Use the application's configured user model/connection, then bridge
            // that principal into the legacy Sentry authenticator used by models.
            $user = User::find($userId);

            if ($user === null || (! empty($user->banned))) {
                return false;
            }

            app()->make('sentry')->setUser($user);
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
