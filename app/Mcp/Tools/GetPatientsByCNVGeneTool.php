<?php

namespace App\Mcp\Tools;

class GetPatientsByCNVGeneTool extends BaseGeneQueryTool
{
    protected string $name = 'getPatientsByCNVGene';
    protected string $description = 'List authorized patients with amplification/deletion of a gene. Omit category for all authorized groups, or drill into an exact group from getCNVGeneSummary. Default copy-number thresholds >=3 and <=1.';
    protected bool $patientDetails = true;
    protected array $argumentNames = ['gene_id', 'cat_type', 'category', 'min_amplified', 'max_deleted'];
}
