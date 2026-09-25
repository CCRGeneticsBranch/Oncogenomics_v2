<?php

namespace Tests\Architecture;

use PhpToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BladeCompilationTest extends TestCase
{
    #[DataProvider('bladeViewProvider')]
    public function test_blade_view_compiles_to_valid_php(string $relativePath): void
    {
        $source = file_get_contents(resource_path('views/'.$relativePath));
        $compiled = app('blade.compiler')->compileString($source);

        PhpToken::tokenize($compiled, TOKEN_PARSE);
        $this->addToAssertionCount(1);
    }

    public static function bladeViewProvider(): iterable
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            yield str_replace(DIRECTORY_SEPARATOR, '/', $relative) => [$relative];
        }
    }
}
