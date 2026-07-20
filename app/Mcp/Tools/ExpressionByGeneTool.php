<?php

namespace App\Mcp\Tools;

class ExpressionByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'expression_by_gene';

    protected string $description = <<<'MARKDOWN'
        Build a project expression view URL for a given gene symbol.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewProjectExpressionByGene/'.$projectId.'/'.$gene);
    }
    
    public function schema($schema = null): array
    {
        return [];
    }
}
