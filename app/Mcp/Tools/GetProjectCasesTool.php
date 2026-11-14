<?php

namespace App\Mcp\Tools;

class GetProjectCasesTool extends BaseProjectTableTool
{
    protected string $name = 'get_project_cases';

    protected string $description = <<<'MARKDOWN'
        Return authorized project clinical cases.
    MARKDOWN;

    protected string $controllerMethod = 'getProjectCases';

    protected string $tableTitle = 'Project Cases';

    protected string $tableSummary = 'Clinical cases associated with the current project.';

    protected string $serializationError = 'Project case data could not be serialized.';
}
