<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuestAuthorizationMatrixTest extends TestCase
{
    #[DataProvider('protectedGetRouteProvider')]
    public function test_logged_middleware_routes_redirect_guests(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    public static function protectedGetRouteProvider(): iterable
    {
        $routes = json_decode(file_get_contents(__DIR__.'/../Fixtures/routes.json'), true, 512, JSON_THROW_ON_ERROR);

        foreach ($routes as $route) {
            if (! str_contains($route['method'], 'GET') || ! in_array('App\\Http\\Middleware\\Logged', $route['middleware'], true) || $route['uri'] === '/') {
                continue;
            }

            $uri = preg_replace_callback('/\{([^}?]+)(\?)?\}/', static function (array $match): string {
                $name = $match[1];
                if (str_contains($name, 'conversation')) return '00000000-0000-4000-8000-000000000000';
                if ($name === 'scope') return 'global';
                if (preg_match('/(^|_)(id|start|end|position|count|cutoff|topn|tier|maf|vaf)($|_)/i', $name)) return '1';
                return 'test';
            }, $route['uri']);

            yield $route['uri'] => ['/'.ltrim($uri, '/')];
        }
    }
}
