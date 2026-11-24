<?php

namespace App\Mcp\Tools;

class GetVarGeneSummaryTool extends BaseGeneQueryTool
{
    protected string $name = 'getVarGeneSummary';
    protected string $description = 'Return somatic and germline tier summaries for a gene across all authorized projects, grouped by diagnosis or project. No project or cancer type is required. This is not the var_pathogenic dataset; for explicitly pathogenic mutation requests use get_pathogeic_mutations for a project or authorized SQL over var_pathogenic across projects. Counts default to tiers 1 and 2; use frequency for percentages.';
    protected array $argumentNames = ['gene_id', 'value_type', 'category', 'min_pat', 'tiers'];
}
