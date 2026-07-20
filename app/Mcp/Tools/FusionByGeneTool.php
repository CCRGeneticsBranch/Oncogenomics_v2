<?php

namespace App\Mcp\Tools;

class FusionByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'fusion_by_gene';

    protected string $description = <<<'MARKDOWN'
        Build a project fusion view URL for a given gene symbol.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewFusionGenes/'.$projectId.'/'.$gene);
    }
}
