<?php

namespace App\Mcp\Tools;

class CnvByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'cnv_by_gene';

    protected string $description = <<<'MARKDOWN'
        Return a gene CNV viewer URL only when a page/link is requested. For copy-number data use get_project_cnv.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewCNVByGene/'.$projectId.'/'.$gene.'/Project');
    }
}
