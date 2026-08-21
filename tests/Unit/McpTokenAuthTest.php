<?php

namespace Tests\Unit;

use App\Http\Middleware\McpTokenAuth;
use Illuminate\Http\Request;
use LaravelAcl\Authentication\Models\User;
use Mockery;
use Tests\TestCase;

class McpTokenAuthTest extends TestCase
{
    public function test_endpoint_fails_closed_when_no_tokens_are_configured(): void
    {
        config()->set('mcp_auth.internal_token', '');
        config()->set('mcp_auth.token_hashes', []);
        config()->set('mcp_auth.token_users', []);
        config()->set('mcp_auth.allow_unprotected', false);

        $request = Request::create('/mcp/onco', 'POST');
        $response = (new McpTokenAuth)->handle($request, fn () => response('unexpected'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            'Unauthorized: MCP authentication is not configured.',
            $response->getData(true)['error']['message'],
        );
    }

    public function test_unprotected_mode_requires_an_explicit_opt_out(): void
    {
        config()->set('mcp_auth.internal_token', '');
        config()->set('mcp_auth.token_hashes', []);
        config()->set('mcp_auth.token_users', []);
        config()->set('mcp_auth.allow_unprotected', true);

        $request = Request::create('/mcp/onco', 'POST');
        $response = (new McpTokenAuth)->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_internal_token_can_delegate_an_authenticated_browser_user(): void
    {
        config()->set('mcp_auth.internal_token', 'internal-secret');
        config()->set('mcp_auth.token_hashes', []);
        config()->set('mcp_auth.token_users', []);

        $user = $this->user(3803);
        $sentry = Mockery::mock();
        $sentry->shouldReceive('findUserById')->once()->with(3803)->andReturn($user);
        $sentry->shouldReceive('setUser')->once()->with($user);
        $this->app->instance('sentry', $sentry);

        $request = $this->request('internal-secret', '3803');
        $response = (new McpTokenAuth)->handle($request, function (Request $request) use ($user) {
            $this->assertSame($user, $request->user());
            $this->assertSame($user, $request->attributes->get('mcp_user'));
            $this->assertSame(3803, $request->attributes->get('mcp_user_id'));

            return response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_external_token_cannot_override_its_mapped_user_with_internal_header(): void
    {
        $externalToken = 'external-secret';
        config()->set('mcp_auth.internal_token', 'internal-secret');
        config()->set('mcp_auth.token_hashes', []);
        config()->set('mcp_auth.token_users', [hash('sha256', $externalToken) => 81]);

        $user = $this->user(81);
        $sentry = Mockery::mock();
        $sentry->shouldReceive('findUserById')->once()->with(81)->andReturn($user);
        $sentry->shouldReceive('setUser')->once()->with($user);
        $this->app->instance('sentry', $sentry);

        $request = $this->request($externalToken, '3803');
        $response = (new McpTokenAuth)->handle($request, function (Request $request) use ($user) {
            $this->assertSame($user, $request->user());
            $this->assertSame(81, $request->attributes->get('mcp_user_id'));

            return response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_internal_token_rejects_an_invalid_delegated_user_id(): void
    {
        config()->set('mcp_auth.internal_token', 'internal-secret');
        config()->set('mcp_auth.token_hashes', []);
        config()->set('mcp_auth.token_users', []);

        $request = $this->request('internal-secret', '../81');
        $response = (new McpTokenAuth)->handle($request, fn () => response('unexpected'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            'Unauthorized: the delegated MCP user ID is invalid.',
            $response->getData(true)['error']['message']
        );
    }

    private function request(string $token, string $delegatedUserId): Request
    {
        return Request::create('/mcp/onco', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_'.strtoupper(str_replace('-', '_', McpTokenAuth::INTERNAL_USER_ID_HEADER)) => $delegatedUserId,
        ]);
    }

    private function user(int $id): User
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'banned' => 0]);

        return $user;
    }
}
