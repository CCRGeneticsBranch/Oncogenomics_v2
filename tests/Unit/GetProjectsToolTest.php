<?php

namespace Tests\Unit;

use App\Mcp\Tools\GetProjectsTool;
use Laravel\Mcp\Request;
use PHPUnit\Framework\TestCase;

class GetProjectsToolTest extends TestCase
{
    public function test_it_returns_available_projects_sorted_by_name(): void
    {
        $tool = new class extends GetProjectsTool
        {
            protected function availableProjects(): array
            {
                return [
                    (object) [
                        'id' => '22112',
                        'name' => 'Clinomics',
                        'description' => 'Main cohort',
                    ],
                    (object) [
                        'id' => '7',
                        'name' => 'Alpha',
                        'description' => null,
                    ],
                ];
            }
        };

        $content = $tool->handle(new Request)->getStructuredContent();

        $this->assertSame('success', $content['status']);
        $this->assertSame('getProjects', $content['action']);
        $this->assertSame([
            [
                'project_id' => 7,
                'project_name' => 'Alpha',
                'description' => '',
            ],
            [
                'project_id' => 22112,
                'project_name' => 'Clinomics',
                'description' => 'Main cohort',
            ],
        ], $content['projects']);

        $table = json_decode($content['table_json'], true);
        $this->assertSame([7, 'Alpha', ''], $table['data'][0]);
        $this->assertSame('2 projects are available.', $content['summary']);
    }

    public function test_it_advertises_an_argument_free_schema(): void
    {
        $tool = new class extends GetProjectsTool
        {
            protected function availableProjects(): array
            {
                return [];
            }
        };

        $this->assertSame([
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ], $tool->schemaDefinition());
    }
}
