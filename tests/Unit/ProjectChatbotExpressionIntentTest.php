<?php

namespace Tests\Unit;

use App\Http\Controllers\ProjectController;
use ReflectionMethod;
use Tests\TestCase;

class ProjectChatbotExpressionIntentTest extends TestCase
{
    public function test_median_order_understands_in_descending_order_wording(): void
    {
        $method = new ReflectionMethod(ProjectController::class, 'extractExpressionGroupOrderFromQuery');
        $controller = app(ProjectController::class);

        $this->assertSame(
            'median_desc',
            $method->invoke(
                $controller,
                'Show me log2 FGFR4 expression and plot a violin plot order by median value in descending order.',
            ),
        );
        $this->assertSame(
            'median_asc',
            $method->invoke($controller, 'Plot FGFR4 ordered by the median value in ascending order.'),
        );
    }
}
