<?php

namespace App\Mcp\Tools;

class GetCNVGeneSummaryTool extends BaseGeneQueryTool
{
    protected string $name = 'getCNVGeneSummary';
    protected string $description = 'Return amplification/deletion summaries for a gene across all authorized projects, grouped by diagnosis or project. No cohort is required. Default copy-number thresholds are >=3 and <=1.';
    protected array $argumentNames = ['gene_id', 'value_type', 'category', 'min_pat', 'min_amplified', 'max_deleted'];
}
