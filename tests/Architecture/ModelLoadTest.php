<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelLoadTest extends TestCase
{
    #[DataProvider('modelProvider')]
    public function test_active_model_can_be_autoloaded(string $class): void
    {
        $this->assertTrue(class_exists($class), "Model {$class} cannot be autoloaded");
        $reflection = new \ReflectionClass($class);
        $this->assertFalse($reflection->isAnonymous());
    }

    public static function modelProvider(): iterable
    {
        foreach (glob(dirname(__DIR__, 2).'/app/Models/*.php') as $file) {
            $class = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);
            yield $class => [$class];
        }
    }
}
