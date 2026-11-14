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

    public function test_new_arguments_are_optional_for_existing_callers(): void
    {
        $tool = new class extends GetProjectsTool
        {
            protected function availableProjects(): array
            {
                return [];
            }
        };

        $schema = $tool->schemaDefinition();
        $this->assertEmpty($schema['required'] ?? []);
        $this->assertSame(['result_role', 'query', 'match', 'output'], array_keys($schema['properties']));
        $this->assertFalse($schema['additionalProperties']);
    }

    public function test_filtered_compact_lookup_retains_ambiguous_authorized_matches(): void
    {
        $tool = new class extends GetProjectsTool {
            protected function availableProjects(): array
            {
                return [
                    (object) ['id' => 20, 'name' => 'COMPASS'],
                    (object) ['id' => 10, 'name' => 'Compass'],
                    (object) ['id' => 30, 'name' => 'Compass Trial'],
                ];
            }
        };
        $args = ['query' => 'compass', 'match' => 'exact', 'output' => ['limit' => 1,
            'fields' => ['Project ID', 'Project Name']]];
        $first = $tool->handle(new Request($args))->getStructuredContent();
        $this->assertSame(2, $first['total_rows']);
        $this->assertSame([[10, 'Compass']], $first['rows']);
        $this->assertSame(1, $first['next_offset']);
        $this->assertArrayNotHasKey('projects', $first);
        $this->assertArrayNotHasKey('table_json', $first);
        $args['output']['offset'] = 1;
        $second = $tool->handle(new Request($args))->getStructuredContent();
        $this->assertSame([[20, 'COMPASS']], $second['rows']);
        $this->assertNull($second['next_offset']);
        $empty = $tool->handle(new Request(['query' => 'inaccessible project',
            'output' => ['mode' => 'summary']]))->getStructuredContent();
        $this->assertSame(0, $empty['total_rows']);
        $this->assertArrayNotHasKey('rows', $empty);
        $partial = $tool->handle(new Request(['query' => 'compass',
            'output' => ['mode' => 'summary']]))->getStructuredContent();
        $this->assertSame(3, $partial['total_rows']);
    }

    public function test_invalid_output_returns_an_error_instead_of_an_unbounded_result(): void
    {
        $tool = new class extends GetProjectsTool {
            protected function availableProjects(): array { return []; }
        };
        $content = $tool->handle(new Request(['output' => ['limit' => 0]]))->getStructuredContent();
        $this->assertSame('error', $content['status']);
        $this->assertArrayNotHasKey('projects', $content);
    }
}
