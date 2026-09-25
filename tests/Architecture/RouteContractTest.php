<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteContractTest extends TestCase
{
    #[DataProvider('routeProvider')]
    public function test_route_contract_is_registered(array $expected): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(function ($route) use ($expected): bool {
                $methods = implode('|', array_values(array_intersect($route->methods(), ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])));

                return $route->uri() === $expected['uri'] && $methods === $expected['method'];
            });

        $this->assertNotNull($route, "Missing route {$expected['method']} {$expected['uri']}");
        $this->assertSame($expected['action'], $route->getActionName());
        $this->assertSame($expected['name'], $route->getName());

        $this->assertNotEmpty($route->gatherMiddleware(), "Route {$expected['uri']} has no middleware group");

        $action = $route->getActionName();
        if ($action !== 'Closure' && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            $this->assertTrue(class_exists($controller), "Controller {$controller} cannot be autoloaded");
            $this->assertTrue(method_exists($controller, $method), "Missing action {$action}");
        }
    }

    public static function routeProvider(): iterable
    {
        $routes = json_decode(file_get_contents(__DIR__.'/../Fixtures/routes.json'), true, 512, JSON_THROW_ON_ERROR);

        foreach ($routes as $route) {
            yield $route['method'].' '.$route['uri'] => [$route];
        }
    }

    public function test_route_catalog_has_no_stale_or_missing_entries(): void
    {
        $expected = json_decode(file_get_contents(__DIR__.'/../Fixtures/routes.json'), true, 512, JSON_THROW_ON_ERROR);
        $actual = collect(app('router')->getRoutes()->getRoutes())
            ->map(function ($route): array {
                return [
                    'method' => implode('|', array_values(array_intersect($route->methods(), ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']))),
                    'uri' => $route->uri(),
                ];
            })
            ->unique(fn (array $route): string => $route['method'].' '.$route['uri'])
            ->values();

        $this->assertGreaterThanOrEqual(count($expected), $actual->count());
    }
}
