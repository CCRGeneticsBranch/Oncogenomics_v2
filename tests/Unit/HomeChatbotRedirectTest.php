<?php
namespace Tests\Unit;

use App\Http\Controllers\ChatbotController;
use Tests\TestCase;

class HomeChatbotRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ChatbotIntent::class)->classify('Open a gene result');
    }

    public function test_home_redirect_resolves_named_project_and_retains_it_for_follow_ups(): void
    {
        $controller = $this->controller();
        $page = $controller->page('Show me the somatic mutation of gene tp53 in COMPASS project');
        $this->assertSame(url('/viewVarAnnotationByGene/42/TP53/somatic/0'), $page['url']);
        $conversation = ['messages' => [
            ['role'=>'user', 'content'=>'Show TP53 in COMPASS project'],
            ['role'=>'assistant', 'meta'=>['redirect_cohort'=>$page['cohort']]],
        ]];
        $followUp = $controller->page('Show me the somatic mutation of gene tp53', $conversation);
        $this->assertSame($page['url'], $followUp['url']);
        $this->assertNull($controller->page('Show me the somatic mutation of gene tp53 in Unknown project', $conversation));
        $controller->authorized = false;
        $this->assertNull($controller->page('Show me the somatic mutation of gene tp53', $conversation));
    }

    public function test_home_does_not_guess_an_omitted_or_ambiguous_project(): void
    {
        $controller = $this->controller();
        $this->assertNull($controller->page('Show me the somatic mutation of gene tp53'));
        $controller->duplicate = true;
        $this->assertNull($controller->page('Show me the somatic mutation of gene tp53 in COMPASS project'));
    }

    public function test_rnaseq_landscape_resolves_project_id_before_fusion_redirect(): void
    {
        $controller = new class extends ChatbotController {
            public bool $ambiguous = false;
            public bool $exactMatch = false;
            public array $resolvedIds = [];
            protected function redirectProjects(): array {
                $projects = [(object)['id'=>24421, 'name'=>'RNAseq_Landscape_Manuscript']];
                if ($this->ambiguous) $projects[] = (object)['id'=>99, 'name'=>'RNAseq_Landscape_Validation'];
                if ($this->exactMatch) $projects[] = (object)['id'=>100, 'name'=>'RNAseq_Landscape'];
                return $projects;
            }
            protected function resolveChatbotContext($scope, $id) {
                $this->resolvedIds[] = $id;
                return ['status'=>'success', 'id'=>$id, 'name'=>'RNAseq_Landscape_Manuscript'];
            }
            protected function resolveGeneSymbol($candidate) {
                return strtoupper($candidate) === 'PAX3' ? 'PAX3' : null;
            }
            public function page($query) {
                return $this->chatbotRedirectPage('global', ['id'=>'all','name'=>'Clinomics'], $query);
            }
        };
        $query = 'Show me the pax3 fusion in rnaseq landscape project';
        $page = $controller->page($query);
        $this->assertSame(url('/viewFusionGenes/24421/PAX3'), $page['url']);
        $this->assertSame(24421, $page['cohort']['id']);
        $this->assertSame([24421], $controller->resolvedIds);
        $this->assertSame($page, $controller->page('Show me the pax3 fusion in RNAseq Landscape Manuscript project'));
        $this->assertNull($controller->page('Show me the pax3 fusion in rnaseq land project'));
        $controller->ambiguous = true;
        $this->assertNull($controller->page($query));
        $controller->exactMatch = true;
        $this->assertSame(url('/viewFusionGenes/100/PAX3'), $controller->page($query)['url']);
    }

    public function test_resolved_web_expression_opens_all_requested_genes(): void
    {
        $controller = $this->expressionController();
        $query = 'show me the expression of ALK,fgfr4 and mycn in landscape project';
        $page = $controller->resolved($query, [$this->expressionExecution()]);
        $this->assertSame(url('/viewProjectExpressionByGene/24421/ALK%20FGFR4%20MYCN'), $page['url']);
        $this->assertSame(24421, $page['cohort']['id']);
        $history = [['role' => 'user', 'content' => $query],
            ['role' => 'assistant', 'content' => 'Which Landscape project?']];
        $this->assertSame($page, $controller->resolved('the first one', [$this->expressionExecution()], $history));
        $this->assertSame($page, $controller->resolved('RNAseq_Landscape_Manuscript', [$this->expressionExecution()], $history));
    }

    public function test_web_expression_respects_explicit_data_and_analysis_requests(): void
    {
        $controller = $this->expressionController();
        foreach (['Summarize expression of ALK', 'Show expression of ALK in a table',
            'Show mean expression of ALK', 'Show median expression of ALK',
            'Compare expression of ALK and MYCN', 'Show expression of ALK and its mutations',
            'Show a violin plot of ALK', 'Export expression of ALK'] as $query) {
            $this->assertNull($controller->resolved($query, [$this->expressionExecution()]), $query);
            $this->assertNull($controller->fixed($query), $query);
        }
        $this->assertNull($controller->resolved('the first one', [$this->expressionExecution()],
            [['role' => 'user', 'content' => 'Summarize expression of ALK in landscape']]));
        $this->assertNull($controller->resolved('Show expression of UNKNOWN', [$this->expressionExecution()]));
    }

    public function test_resolved_viewer_requires_successful_unique_authorized_project_evidence(): void
    {
        $controller = $this->expressionController();
        $query = 'show expression of ALK';
        $execution = $this->expressionExecution();
        $this->assertNull($controller->resolved($query, []));
        $this->assertNull($controller->resolved($query, [['tool' => 'getProjects', 'status' => 'success']]));
        $failed = $execution;
        $failed['status'] = 'error';
        $this->assertNull($controller->resolved($query, [$failed]));
        $different = $execution;
        $different['arguments']['cohort_id'] = 99;
        $this->assertNull($controller->resolved($query, [$execution, $different]));
        $different['arguments'] = ['cohort_type' => 'cancer_type', 'cohort_id' => 'Neuroblastoma'];
        $this->assertNull($controller->resolved($query, [$different]));
        $controller->authorized = false;
        $this->assertNull($controller->resolved($query, [$execution]));
    }

    private function expressionExecution(): array
    {
        return ['tool' => 'getCohortExpression', 'status' => 'success',
            'arguments' => ['cohort_type' => 'project', 'cohort_id' => 24421, 'gene' => 'ALK'],
            'preview' => ['cohort_name' => 'RNAseq_Landscape_Manuscript']];
    }

    private function expressionController(): ChatbotController
    {
        return new class extends ChatbotController {
            public bool $authorized = true;
            protected function resolveChatbotContext($scope, $id) {
                return ['status' => $this->authorized ? 'success' : 'error', 'id' => (int) $id,
                    'name' => 'RNAseq_Landscape_Manuscript'];
            }
            protected function resolveGeneSymbol($gene) {
                return in_array(strtoupper($gene), ['ALK', 'FGFR4', 'MYCN'], true) ? strtoupper($gene) : null;
            }
            public function resolved($query, $executions, $history = []) {
                return $this->resolvedExpressionPage($query, $executions, $history);
            }
            public function fixed($query) {
                return $this->deterministicChatbotRedirect('project', 24421, $query);
            }
        };
    }

    private function controller(): ChatbotController
    {
        return new class extends ChatbotController {
            public bool $authorized = true;
            public bool $duplicate = false;
            protected function redirectProjects(): array {
                $projects = [(object)['id'=>42,'name'=>'COMPASS']];
                if ($this->duplicate) $projects[] = (object)['id'=>43,'name'=>'COMPASS'];
                return $projects;
            }
            protected function resolveChatbotContext($scope, $id) {
                return ['status'=>$this->authorized?'success':'error','id'=>42,'name'=>'COMPASS'];
            }
            protected function resolveGeneSymbol($candidate) { return strtoupper($candidate) === 'TP53' ? 'TP53' : null; }
            public function page($query, $conversation = []) {
                return $this->chatbotRedirectPage('global', ['id'=>'all','name'=>'Clinomics'], $query, $conversation);
            }
        };
    }
}
