<?php

namespace Tests\Unit;

use App\Http\Controllers\ProjectController;
use Tests\TestCase;

class ChatbotDeterministicRedirectTest extends TestCase
{
    public function test_mutation_queries_skip_the_gene_label(): void
    {
        $controller = new class extends ProjectController {
            public array $candidates = [];
            protected function resolveGeneSymbol($candidate) {
                $this->candidates[] = $candidate;
                return strtoupper($candidate) === 'TP53' ? 'TP53' : 'GEN1';
            }
            public function redirectFor($query) {
                return $this->deterministicChatbotRedirect('project', 42, $query);
            }
        };
        foreach ([
            'Show me the somatic mutation of gene tp53' => 'somatic',
            'Show me the germline mutations of gene tp53.' => 'germline',
            'Show me the RNAseq mutation of gene TP53' => 'rnaseq',
            'Show me the somatic mutation of tp53' => 'somatic',
        ] as $query => $type) {
            $controller->candidates = [];
            $this->assertSame(url("/viewVarAnnotationByGene/42/TP53/{$type}/0"), $controller->redirectFor($query));
            $this->assertSame(['TP53'], $controller->candidates);
        }
        $controller->candidates = [];
        $method = new \ReflectionMethod(ProjectController::class, 'extractMutationIntentFromQuery');
        $this->assertNull($method->invoke($controller, 'Show me the somatic mutation of gene'));
        $this->assertSame([], $controller->candidates);
    }

    public function test_gene_labels_are_never_fuzzy_matched_to_symbols(): void
    {
        $method = new \ReflectionMethod(ProjectController::class, 'resolveGeneSymbol');
        foreach (['gene', 'GENES'] as $label) {
            $this->assertNull($method->invoke(new ProjectController, $label));
        }
    }

    public function test_simple_queries_open_the_existing_pages(): void
    {
        $controller = new class extends ProjectController {
            protected function resolveGeneSymbol($candidate) {
                return strtoupper(trim($candidate)) === 'FGFR4' ? 'FGFR4' : null;
            }
            public function redirectFor($query, $scope = 'project') {
                return $this->deterministicChatbotRedirect($scope, 42, $query);
            }
        };
        foreach ([
            'what genes similar to FGFR4' => '/viewCorrelation/42/FGFR4',
            'show correlation of FGFR4' => '/viewCorrelation/42/FGFR4',
            'show survival of FGFR4' => '/viewSurvivalByExpression/42/FGFR4/Y',
            'show somatic mutations of FGFR4' => '/viewVarAnnotationByGene/42/FGFR4/somatic/0',
            'show germline mutations of FGFR4' => '/viewVarAnnotationByGene/42/FGFR4/germline/0',
            'show RNAseq mutations of FGFR4' => '/viewVarAnnotationByGene/42/FGFR4/rnaseq/0',
            'show heatmap of FGFR4' => '/viewProjectExpressionByGene/42/FGFR4',
            'show fusion of FGFR4' => '/viewFusionGenes/42/FGFR4',
        ] as $query => $path) {
            $this->assertSame(url($path), $controller->redirectFor($query), $query);
        }
        $this->assertNull($controller->redirectFor('show violin plot of FGFR4'));
        $this->assertNull($controller->redirectFor('count mutations of FGFR4'));
        $this->assertNull($controller->redirectFor('show fusion of FGFR4', 'global'));
    }
}
