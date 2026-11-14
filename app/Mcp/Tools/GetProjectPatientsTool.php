<?php

namespace App\Mcp\Tools;

class GetProjectPatientsTool extends BaseProjectTableTool
{
    protected string $name = 'get_project_patients';

    protected string $description = <<<'MARKDOWN'
        Return authorized project patient records.
    MARKDOWN;

    protected string $controllerMethod = 'getProjectPatients';

    protected string $tableTitle = 'Project Patients';

    protected string $tableSummary = 'Patients associated with the current project.';

    protected string $serializationError = 'Project patient data could not be serialized.';
}
