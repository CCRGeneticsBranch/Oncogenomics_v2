<?php

namespace App\Mcp\Tools;

class GetPatientsByFusionGeneTool extends BaseGeneQueryTool
{
    protected string $name = 'getPatientsByFusionGene';
    protected string $description = 'List authorized patients with fusions involving a gene on either side. Omit category for all authorized groups, or drill into a group from getFusionGeneSummary. Default tiers 1 and 2.';
    protected bool $patientDetails = true;
    protected bool $fusion = true;
    protected array $argumentNames = ['gene_id', 'cat_type', 'category', 'fusion_type', 'tiers'];
}
