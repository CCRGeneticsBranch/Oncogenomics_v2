<?php

namespace App\Mcp\Tools;

class FusionByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'fusion_by_gene';

    protected string $description = <<<'MARKDOWN'
        Return a gene fusion viewer URL for simple fusion-only or explicit page/link requests. For filtering, counting, joins, or other data types use get_fusion_genes; do not combine this redirect tool with other tools.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewFusionGenes/'.$projectId.'/'.$gene);
    }
}
