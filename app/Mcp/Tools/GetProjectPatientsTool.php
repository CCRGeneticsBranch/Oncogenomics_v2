<?php

namespace App\Mcp\Tools;

class GetProjectPatientsTool extends BaseProjectTableTool
{
    protected string $name = 'get_project_patients';

    protected string $description = <<<'MARKDOWN'
        Get the patient records associated with the current project. Use this
        tool for requests asking for project patients or a project's patient
        list. Returns a generic table payload (columns and rows) that the
        chatbot renders with jQuery DataTables.
    MARKDOWN;

    protected string $controllerMethod = 'getProjectPatients';

    protected string $tableTitle = 'Project Patients';

    protected string $tableSummary = 'Patients associated with the current project.';

    protected string $serializationError = 'Project patient data could not be serialized.';
}
