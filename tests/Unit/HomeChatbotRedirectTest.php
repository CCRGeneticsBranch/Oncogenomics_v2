<?php
namespace Tests\Unit;

use App\Http\Controllers\ChatbotController;
use Tests\TestCase;

class HomeChatbotRedirectTest extends TestCase
{
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
