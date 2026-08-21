<?php

namespace Tests\Unit;

use App\Ai\Support\ChatbotRunContext;
use App\Ai\Support\ChatbotToolPolicy;
use App\Ai\Support\ScopedMcpToolCatalog;
use Tests\TestCase;

class ChatbotToolPolicyTest extends TestCase
{
    public function test_expression_question_cannot_select_the_chipseq_tool(): void
    {
        $query = 'Show the FGFR4 expression in NB';

        $this->assertTrue(ChatbotToolPolicy::asksForExpression($query));
        $this->assertFalse(ChatbotToolPolicy::allows('getCohortChIPseq', $query));
        $this->assertTrue(ChatbotToolPolicy::allows('getCohortExpression', $query));
    }

    public function test_explicit_chipseq_question_keeps_the_chipseq_tool(): void
    {
        $query = 'Show ChIP-seq samples targeting MYCN in NB';

        $this->assertTrue(ChatbotToolPolicy::asksForChipSeq($query));
        $this->assertTrue(ChatbotToolPolicy::allows('getCohortChIPseq', $query));
        $this->assertFalse(ChatbotToolPolicy::allows('getCohortExpression', $query));
    }

    public function test_global_expression_catalog_contains_expression_but_not_chipseq(): void
    {
        $context = new ChatbotRunContext(
            'global',
            'all',
            'All accessible cohorts',
            'Show the FGFR4 expression in NB',
        );
        $names = array_map(
            static fn ($tool): string => $tool->name(),
            app(ScopedMcpToolCatalog::class)->forContext($context),
        );

        $this->assertContains('getCohortExpression', $names);
        $this->assertNotContains('getCohortChIPseq', $names);
        $this->assertContains('getCancerTypes', $names);
    }

    public function test_alteration_question_cannot_inherit_an_expression_tool(): void
    {
        $query = 'show alteration of alk';

        $this->assertTrue(ChatbotToolPolicy::asksForGenomicAlterations($query));
        $this->assertFalse(ChatbotToolPolicy::allows('expression_by_gene', $query));
        $this->assertFalse(ChatbotToolPolicy::allows('getCohortExpression', $query));
        $this->assertTrue(ChatbotToolPolicy::allows('get_project_cnv', $query));
        $this->assertTrue(ChatbotToolPolicy::allows('get_fusion_genes', $query));
        $this->assertTrue(ChatbotToolPolicy::allows('get_pathogeic_mutations', $query));
    }

    public function test_expression_remains_available_when_the_current_query_requests_both_modalities(): void
    {
        $query = 'show alterations and expression of ALK';

        $this->assertTrue(ChatbotToolPolicy::asksForGenomicAlterations($query));
        $this->assertTrue(ChatbotToolPolicy::asksForExpression($query));
        $this->assertTrue(ChatbotToolPolicy::allows('expression_by_gene', $query));
    }

    public function test_only_dependent_follow_ups_receive_conversation_context(): void
    {
        $this->assertTrue(ChatbotToolPolicy::needsConversationContext('group by diagnosis'));
        $this->assertTrue(ChatbotToolPolicy::needsConversationContext('show me the heatmap'));
        $this->assertTrue(ChatbotToolPolicy::needsConversationContext('use TPM instead'));
        $this->assertFalse(ChatbotToolPolicy::needsConversationContext('show alteration of alk'));
        $this->assertFalse(ChatbotToolPolicy::needsConversationContext('show me the FGFR4 expression'));
    }

    public function test_project_alteration_catalog_excludes_expression_and_keeps_genomic_tools(): void
    {
        $context = new ChatbotRunContext(
            'project',
            22112,
            'Clinomics',
            'show alteration of alk',
        );
        $names = array_map(
            static fn ($tool): string => $tool->name(),
            app(ScopedMcpToolCatalog::class)->forContext($context),
        );

        $this->assertNotContains('expression_by_gene', $names);
        $this->assertContains('get_project_cnv', $names);
        $this->assertContains('get_fusion_genes', $names);
        $this->assertContains('get_pathogeic_mutations', $names);
    }
}
