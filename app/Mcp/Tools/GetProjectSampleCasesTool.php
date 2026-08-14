<?php

namespace App\Mcp\Tools;

class GetProjectSampleCasesTool extends BaseProjectTableTool
{
    protected string $name = 'get_project_sample_cases';

    protected string $description = <<<'MARKDOWN'
        Get project sample records joined to their clinical cases. Use this
        tool when a request needs sample-level project data together with case
        IDs and case paths. Returns a generic table payload (columns and rows)
        that the chatbot renders with jQuery DataTables.
    MARKDOWN;

    protected string $controllerMethod = 'getProjectSampleCases';

    protected string $tableTitle = 'Project Sample Cases';

    protected string $tableSummary = 'Project sample records joined to their clinical cases.';

    protected string $serializationError = 'Project sample-case data could not be serialized.';
}
