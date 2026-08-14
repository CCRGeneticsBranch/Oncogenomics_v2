<?php

namespace App\Mcp\Tools;

class GetProjectCasesTool extends BaseProjectTableTool
{
    protected string $name = 'get_project_cases';

    protected string $description = <<<'MARKDOWN'
        Get the clinical case records associated with the current project. Use
        this tool for requests asking for project cases or a project's case
        list. Returns a generic table payload (columns and rows) that the
        chatbot renders with jQuery DataTables.
    MARKDOWN;

    protected string $controllerMethod = 'getProjectCases';

    protected string $tableTitle = 'Project Cases';

    protected string $tableSummary = 'Clinical cases associated with the current project.';

    protected string $serializationError = 'Project case data could not be serialized.';
}
