<?php

namespace App\Mcp\Tools;

class SurvivalByExpressionTool extends BaseGeneRedirectTool
{
    protected string $name = 'survival_by_expression';

    protected string $description = <<<'MARKDOWN'
        Return a project survival-by-expression viewer URL for a gene.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewSurvivalByExpression/'.$projectId.'/'.$gene.'/Y');
    }
}