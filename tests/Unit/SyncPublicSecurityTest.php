<?php

namespace Tests\Unit;

use App\Http\Controllers\SyncPublicController;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncPublicSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('site.public_sync_url', null);
    }

    private function controller(bool $allowed = true): SyncPublicController
    {
        return new class($allowed) extends SyncPublicController {
            public function __construct(private bool $allowed) {}
            protected function isSuperAdmin(): bool { return $this->allowed; }
        };
    }

    public function test_only_admins_can_list_sync_projects_and_credentials_stay_on_the_server(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://public.test/getProjects' => Http::response([['name' => 'Public cohort', 'description' => 'Test']])]);
        config()->set('site.public_token', 'public-test-secret');
        config()->set('site.url_public', 'https://public.test/');
        $this->assertSame(403, $this->controller(false)->projects('public')->getStatusCode());
        Http::assertNothingSent();
        $response = $this->controller()->projects('public');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('public-test-secret', $response->getContent());
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer public-test-secret')
            && $request['token'] === 'public-test-secret');
        $view = file_get_contents(resource_path('views/pages/viewSyncPublic.blade.php'));
        $this->assertStringNotContainsString('site.token', $view);
        $this->assertStringNotContainsString('site.public_token', $view);
    }

    public function test_upstream_auth_failures_do_not_disclose_either_token(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://public.test/getProjects' => Http::response('invalid token: presented => actual-upstream-secret', 403)]);
        config()->set('site.public_token', 'public-test-secret');
        config()->set('site.url_public', 'https://public.test');
        $response = $this->controller()->projects('public');
        $this->assertSame(502, $response->getStatusCode());
        $this->assertStringNotContainsString('upstream-secret', $response->getContent());
        $this->assertStringNotContainsString('public-test-secret', $response->getContent());
    }

    public function test_missing_public_token_is_not_sent_upstream(): void
    {
        Http::fake();
        config()->set('site.public_token', '');
        $this->assertSame(503, $this->controller()->projects('public')->getStatusCode());
        Http::assertNothingSent();
    }

    public function test_sync_uses_the_configured_local_runtime_while_public_links_stay_on_production(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://local.test/clinomics_pub/public/getProjects' => Http::response([['name' => 'Production cohort']])]);
        config()->set('site.public_token', 'public-test-secret');
        config()->set('site.url_public', 'https://production.test/clinomics/public');
        config()->set('site.public_sync_url', 'https://local.test/clinomics_pub/public/');
        $this->assertSame(200, $this->controller()->projects('public')->getStatusCode());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://local.test/clinomics_pub/public/getProjects');
        $this->assertSame('https://production.test/clinomics/public', config('site.url_public'));
    }
}
