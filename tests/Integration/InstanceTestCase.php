<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\CreatesApplication;

abstract class InstanceTestCase extends BaseTestCase
{
    use CreatesApplication;

    protected array $fixtures;

    protected function setUp(): void
    {
        if (getenv('RUN_INSTANCE_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_INSTANCE_TESTS=1 to query the configured instance.');
        }

        parent::setUp();

        $path = (string) env('TEST_INSTANCE_FIXTURES', '');
        if ($path === '') {
            $this->fail('Set TEST_INSTANCE_FIXTURES in .env to an instance fixture JSON file.');
        }

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        $this->assertFileExists($path, "Instance fixture file does not exist: {$path}");

        $this->fixtures = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotSame('sqlite', config('database.default'), 'Instance tests must not use the portable SQLite connection.');
    }
}
