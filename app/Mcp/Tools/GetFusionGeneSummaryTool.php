<?php

namespace App\Mcp\Tools;

class GetFusionGeneSummaryTool extends BaseGeneQueryTool
{
    protected string $name = 'getFusionGeneSummary';
    protected string $description = 'Return tiered fusion summaries involving a gene on either side across all authorized projects. No cohort is required; group by diagnosis or project. Default tiers 1 and 2.';
    protected bool $fusion = true;
    protected array $argumentNames = ['gene_id', 'value_type', 'category', 'min_pat', 'fusion_type', 'tiers'];
}
