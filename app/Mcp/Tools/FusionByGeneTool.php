<?php

namespace App\Mcp\Tools;

class FusionByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'fusion_by_gene';

    protected string $description = <<<'MARKDOWN'
        Open the interactive project fusion page for a gene. Use this tool for
        simple, fusion-only questions such as "show me the fusion of FGFR4",
        and when the user asks to open, view, browse, or get a link to the
        fusion page or fusion viewer. It returns a redirect URL, not data, so
        it cannot be combined with any other tool. Whenever the question also
        involves another data type, or asks for fusions to be filtered,
        counted, or joined with copy number, mutation, expression or sample
        information, use get_fusion_genes instead.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewFusionGenes/'.$projectId.'/'.$gene);
    }
}
