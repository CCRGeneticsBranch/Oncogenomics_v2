<?php

namespace Tests\Unit;

use App\Ai\Support\ChatbotRunContext;
use App\Ai\Support\ChatbotScopeArguments;
use App\Ai\Support\ChatbotToolPolicy;
use App\Ai\Support\ExplicitChatbotCohort;
use App\Ai\Support\ScopedMcpToolCatalog;
use App\Ai\Support\ToolResultCompactor;
use App\Ai\Tools\ScopedMcpTool;
use App\Http\Controllers\ChatbotController;
use App\Mcp\Tools\SearchOncogenomicsWikiTool;
use App\Services\ClinomicsChatAgentRunner;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request;
use ReflectionMethod;
use Tests\TestCase;

class WikiChatbotToolTest extends TestCase
{
    public function test_documentation_is_available_without_cohort_fields_in_all_scopes(): void
    {
        config()->set('chatbot.wiki.index_path', '/nonexistent/wiki-test-index.json');
        $query = ExplicitChatbotCohort::appendProjectContext('How do I download expression matrices?', ['id' => 42, 'name' => 'Example']);
        foreach (['project', 'cancer_type', 'global'] as $scope) {
            $context = new ChatbotRunContext($scope, $scope === 'project' ? 42 : 'RMS', 'Example', $query);
            $tools = app(ScopedMcpToolCatalog::class)->forContext($context);
            $this->assertContains('searchOncogenomicsWiki', array_map(fn ($tool) => $tool->name(), $tools));
            $adapter = new ScopedMcpTool(new SearchOncogenomicsWikiTool, $context, new ToolResultCompactor);
            $args = $adapter->applyScope(['query' => 'expression matrices', 'project_id' => 99, 'cohort_type' => 'project', 'cohort_id' => 99]);
            $this->assertSame(['query' => 'expression matrices'], $args);
            $this->assertSame([], ChatbotScopeArguments::injectedFields('searchOncogenomicsWiki', $scope));
            $result = json_decode($adapter->handle(new AiRequest($args)), true);
            $this->assertSame('success', $result['status']);
            $this->assertNotEmpty($result['matches']);
            $this->assertStringContainsString('github.com/CCRGeneticsBranch/Oncogenomics_v2/wiki/', $result['matches'][0]['url']);
        }
    }

    public function test_tier_definition_followup_only_exposes_wiki_and_finds_fusion_documentation(): void
    {
        $query = 'define the tier1 fusion';
        \App\Ai\Agents\ClinomicsIntentClassifier::fake(fn () => ['intent' => 'documentation']);
        app(\App\Services\ChatbotIntent::class)->classify($query);
        foreach (['global', 'project', 'cancer_type'] as $scope) {
            $tools = app(ScopedMcpToolCatalog::class)->forContext(new ChatbotRunContext($scope, '42', 'Study', $query));
            $this->assertSame(['searchOncogenomicsWiki'], array_map(fn ($tool) => $tool->name(), $tools));
        }
        \App\Ai\Agents\ClinomicsIntentClassifier::fake(fn () => ['intent' => 'data']);
        app(\App\Services\ChatbotIntent::class)->classify('Show tier1 fusions of PAX3');
        $this->assertTrue(ChatbotToolPolicy::allows('getFusionGenes', 'Show tier1 fusions of PAX3'));
        $this->assertTrue(ChatbotToolPolicy::allows('getFusionGenes', 'What are the fusions of PAX3?'));
        $this->assertTrue(ChatbotToolPolicy::allows('getFusionGenes', 'Define tier1 fusion and list PAX3 fusions'));
        config()->set('chatbot.wiki.index_path', '/nonexistent/wiki-test-index.json');
        $result = (new SearchOncogenomicsWikiTool)->handle(new Request(['query' => $query]))->getStructuredContent();
        $this->assertNotEmpty($result['matches']);
        $this->assertContains('Fusion tier definitions', array_column($result['figures'], 'title'));
    }

    public function test_tool_reports_no_match_without_claiming_an_answer(): void
    {
        config()->set('chatbot.wiki.index_path', '/nonexistent/wiki-test-index.json');
        $r = (new SearchOncogenomicsWikiTool)->handle(new Request(['query' => 'qzxv999unknownfeature']))->getStructuredContent();
        $this->assertSame('success', $r['status']);
        $this->assertSame([], $r['matches']);
        $this->assertStringContainsString('No matching tutorial', $r['summary']);
    }

    public function test_tutorial_questions_bypass_viewer_redirects_and_data_continuations(): void
    {
        $controller = new class extends ChatbotController {
            public function direct(string $q): ?string { return $this->deterministicChatbotRedirect('project', 42, $q); }
            public function page(string $q): ?array { return $this->chatbotRedirectPage('global', ['id' => 'all', 'name' => 'Global'], $q); }
            public function resolved(string $q): ?array { return $this->resolvedExpressionPage($q, []); }
        };
        foreach (['How do I view ALK expression in my project?', 'What do fusion tiers mean?',
            'Where can I download expression matrices?', 'Explain the tier filters',
            'define the tier1 fusion', 'What is a tier-1 fusion?', 'Explain fusion tier 1',
            'What does tier1 fusion mean?', 'definition of tier2 fusion'] as $q) {
            \App\Ai\Agents\ClinomicsIntentClassifier::fake(fn () => ['intent' => 'documentation']);
            app(\App\Services\ChatbotIntent::class)->classify($q);
            $this->assertTrue(ChatbotToolPolicy::asksForTutorial($q));
            $this->assertNull($controller->direct($q));
            $this->assertNull($controller->page($q));
            $this->assertNull($controller->resolved($q));
        }
        \App\Ai\Agents\ClinomicsIntentClassifier::fake(fn () => ['intent' => 'data']);
        app(\App\Services\ChatbotIntent::class)->classify('How many patients have ALK fusions in COMPASS?');
        $this->assertFalse(ChatbotToolPolicy::asksForTutorial('How many patients have ALK fusions in COMPASS?'));
        $this->assertFalse(ChatbotToolPolicy::asksForTutorial('Show expression of ALK'));
        \App\Ai\Agents\ClinomicsIntentClassifier::fake(fn () => ['intent' => 'documentation']);
        app(\App\Services\ChatbotIntent::class)->classify('How do I view expression?');
        $method = new ReflectionMethod(ClinomicsChatAgentRunner::class, 'needsDataContinuation');
        $this->assertFalse($method->invoke(app(ClinomicsChatAgentRunner::class), 'How do I view expression?', [
            ['tool' => 'getProjects', 'result' => ['status' => 'success']],
            ['tool' => 'searchOncogenomicsWiki', 'result' => ['status' => 'success']],
        ]));
    }
}
