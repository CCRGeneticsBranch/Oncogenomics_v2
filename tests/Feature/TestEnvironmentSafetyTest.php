<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestEnvironmentSafetyTest extends TestCase
{
    public function test_basic_suite_is_isolated_from_external_state(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertStringStartsWith('http://', url('/login'));
    }
}
