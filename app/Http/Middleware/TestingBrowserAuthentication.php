<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelAcl\Authentication\Interfaces\AuthenticateInterface;
use Symfony\Component\HttpFoundation\Response;

class TestingBrowserAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = config('testing.browser_user_id');
        $expectedToken = config('testing.browser_auth_token');

        if (! app()->environment('testing') || ! is_numeric($userId) || ! is_string($expectedToken) || $expectedToken === '') {
            return $next($request);
        }

        $auth = app(AuthenticateInterface::class);
        if ($auth->getLoggedUser() !== null) {
            return $next($request);
        }

        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            abort(403, 'Test browser authentication is restricted to loopback requests.');
        }

        $providedToken = (string) $request->header('X-Clinomics-Test-Auth');
        if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403, 'Invalid test browser authentication token.');
        }

        if (! $auth->loginById((int) $userId)) {
            abort(500, "Unable to authenticate configured browser test user {$userId}.");
        }

        return $next($request);
    }
}
