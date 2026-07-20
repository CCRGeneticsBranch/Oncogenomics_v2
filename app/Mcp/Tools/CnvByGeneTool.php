<?php

namespace App\Mcp\Tools;

class CnvByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'cnv_by_gene';

    protected string $description = <<<'MARKDOWN'
        Build a project CNV view URL for a given gene symbol.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewCNVByGene/'.$projectId.'/'.$gene.'/Project');
    }
}
