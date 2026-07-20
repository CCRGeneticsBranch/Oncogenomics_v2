<?php

namespace App\Mcp\Tools;

class SurvivalByExpressionTool extends BaseGeneRedirectTool
{
    protected string $name = 'survival_by_expression';

    protected string $description = <<<'MARKDOWN'
        Build a project survival-by-expression view URL for a given gene symbol.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewSurvivalByExpression/'.$projectId.'/'.$gene.'/Y');
    }
}