<?php

namespace Tests\Unit;

use App\Http\Controllers\ProjectController;
use ReflectionMethod;
use Tests\TestCase;

class DotplotPValuesTest extends TestCase
{
    public function test_unavailable_r_returns_null_pvalues_instead_of_missing_array_keys(): void
    {
        config()->set('services.rscript.binary', '/nonexistent/Rscript');
        $result = $this->calculate([1, 2, 3, 4], [1, 3, 2, 4]);
        $this->assertSame(['p_two' => null, 'p_great' => null, 'p_less' => null], $result);
    }

    public function test_insufficient_or_constant_values_do_not_run_a_test(): void
    {
        foreach ([[[1, 2], [2, 3]], [[1, 1, 1], [1, 2, 3]], [[1, 2, 3], [1, 2]]] as [$x, $y]) {
            $this->assertSame(['p_two' => null, 'p_great' => null, 'p_less' => null], $this->calculate($x, $y));
        }
    }

    public function test_real_r_returns_three_valid_pvalues(): void
    {
        if (!is_executable(config('services.rscript.binary'))) $this->markTestSkipped('Rscript is not installed.');
        $result = $this->calculate([1, 2, 3, 4], [1, 3, 2, 4]);
        $this->assertEqualsWithDelta(0.2, $result['p_two'], 0.00001);
        $this->assertEqualsWithDelta(0.1, $result['p_great'], 0.00001);
        $this->assertEqualsWithDelta(0.9, $result['p_less'], 0.00001);
    }

    private function calculate(array $x, array $y): array
    {
        return (new ReflectionMethod(ProjectController::class, 'dotplotPValues'))->invoke(new ProjectController, $x, $y);
    }
}
