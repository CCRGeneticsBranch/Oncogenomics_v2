<?php

namespace App\Mcp\Tools;

class GetPatientsByVarGeneTool extends BaseGeneQueryTool
{
    protected string $name = 'getPatientsByVarGene';
    protected string $description = 'List authorized patients with tiered mutations in a gene. Omit category to query all authorized projects; optionally drill into an exact diagnosis/project group from getVarGeneSummary. Default somatic, tiers 1 and 2.';
    protected bool $patientDetails = true;
    protected array $argumentNames = ['gene_id', 'type', 'cat_type', 'category', 'tiers'];
}
