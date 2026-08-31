<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApplicationSmokeTest extends TestCase
{
    public function test_core_application_routes_are_registered(): void
    {
        $uris = collect(app('router')->getRoutes()->getRoutes())
            ->map(static fn ($route): string => $route->uri())
            ->all();

        $this->assertContains('/', $uris);
        $this->assertContains('login', $uris);
        $this->assertContains('viewChatbot', $uris);
        $this->assertContains('getProjects', $uris);
        $this->assertContains('mcp/onco', $uris);
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_guests_are_redirected_from_protected_routes(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    public static function protectedRouteProvider(): array
    {
        return [
            'chatbot' => ['/viewChatbot'],
            'project resolver' => ['/getProjects'],
        ];
    }
}
