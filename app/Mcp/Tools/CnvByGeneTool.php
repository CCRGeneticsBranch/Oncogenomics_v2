<?php

namespace App\Mcp\Tools;

class CnvByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'cnv_by_gene';

    protected string $description = <<<'MARKDOWN'
        Open the interactive project CNV page for a gene. Use this tool ONLY
        when the user explicitly asks to open, view, browse, or get a link to
        the CNV page or CNV viewer. It returns a redirect URL, not data, so it
        cannot answer questions about copy number itself. To report copy number
        segments, use get_project_cnv instead.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewCNVByGene/'.$projectId.'/'.$gene.'/Project');
    }
}
