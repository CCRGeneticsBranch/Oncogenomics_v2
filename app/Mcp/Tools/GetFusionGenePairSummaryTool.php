<?php

namespace App\Mcp\Tools;

class GetFusionGenePairSummaryTool extends BaseGeneQueryTool
{
    protected string $name = 'getFusionGenePairSummary';
    protected string $description = 'Return fusion partner-pair patient counts involving a gene across all authorized projects, split by diagnosis or project. No cohort is required. Pair summaries are counts, not percentages. Default tiers 1 and 2.';
    protected bool $fusion = true;
    protected array $argumentNames = ['gene_id', 'category', 'min_pat', 'fusion_type', 'tiers'];
}
