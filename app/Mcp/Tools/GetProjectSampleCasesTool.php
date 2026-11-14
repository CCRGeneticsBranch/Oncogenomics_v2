<?php

namespace App\Mcp\Tools;

class GetProjectSampleCasesTool extends BaseProjectTableTool
{
    protected string $name = 'get_project_sample_cases';

    protected string $description = <<<'MARKDOWN'
        Return project samples joined to clinical cases, including case IDs and paths.
    MARKDOWN;

    protected string $controllerMethod = 'getProjectSampleCases';

    protected string $tableTitle = 'Project Sample Cases';

    protected string $tableSummary = 'Project sample records joined to their clinical cases.';

    protected string $serializationError = 'Project sample-case data could not be serialized.';
}
