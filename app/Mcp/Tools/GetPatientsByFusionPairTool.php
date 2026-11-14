<?php

namespace App\Mcp\Tools;

class GetPatientsByFusionPairTool extends BaseGeneQueryTool
{
    protected string $name = 'getPatientsByFusionPair';
    protected string $description = 'List authorized patients for an oriented fusion pair across all accessible projects. No cohort is required; use the exact left/right genes from getFusionGenePairSummary. Default tiers 1 and 2.';
    protected bool $patientDetails = true;
    protected bool $fusion = true;
    protected array $argumentNames = ['left_gene', 'right_gene', 'fusion_type', 'tiers'];
}
