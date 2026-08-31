<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestEnvironment();
    }

    private function assertSafeTestEnvironment(): void
    {
        if (! $this->app->environment('testing')) {
            throw new \LogicException('Automated tests must run with APP_ENV=testing.');
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \LogicException(sprintf(
                'Basic tests may only use the in-memory SQLite database; configured %s database is %s.',
                $connection ?: '(empty)',
                $database ?: '(empty)',
            ));
        }
    }
}
