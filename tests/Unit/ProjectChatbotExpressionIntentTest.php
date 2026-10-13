<?php

namespace Tests\Unit;

use App\Http\Controllers\ProjectController;
use ReflectionMethod;
use Tests\TestCase;

class ProjectChatbotExpressionIntentTest extends TestCase
{
    public function test_expression_redirect_preserves_every_requested_gene(): void
    {
        $controller = new class extends ProjectController {
            public array $candidates = [];
            protected function resolveGeneSymbol($candidate) {
                $this->candidates[] = strtoupper($candidate);
                // Reproduce the old fuzzy resolver's misleading match for "gene".
                if (strtolower($candidate) === 'gene') return 'GEN1';
                return in_array(strtoupper($candidate), ['FGFR4', 'ALK', 'EGFR', 'MYCN'], true)
                    ? strtoupper($candidate) : null;
            }
            public function redirectFor($query) {
                return $this->deterministicChatbotRedirect('project', 24421, $query);
            }
        };
        foreach ([
            'Show me the expression of gene FGFR4,ALK,EGFR,MYCN',
            'Show me the expression of genes FGFR4, ALK, EGFR and MYCN.',
            'Show me the expression of FGFR4, ALK, EGFR, and MYCN',
            'Show me the heatmap of genes FGFR4,ALK,EGFR,MYCN',
        ] as $query) {
            $controller->candidates = [];
            $this->assertSame(url('/viewProjectExpressionByGene/24421/FGFR4%20ALK%20EGFR%20MYCN'), $controller->redirectFor($query));
            $this->assertSame(['FGFR4', 'ALK', 'EGFR', 'MYCN'], $controller->candidates);
        }
        $method = new ReflectionMethod(ProjectController::class, 'extractExpressionGeneFromQuery');
        $this->assertNull($method->invoke($controller, 'Show expression of gene'));
        $this->assertNull($method->invoke($controller, 'Show expression of FGFR4, UNKNOWN_SYMBOL'));
    }

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
