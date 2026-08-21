<?php

namespace Tests\Unit;

use App\Mcp\Tools\GetFusionGenesTool;
use PHPUnit\Framework\TestCase;

class GetFusionGenesToolTest extends TestCase
{
    public function test_schema_exposes_a_first_class_caller_filter(): void
    {
        $tool = new GetFusionGenesTool();
        $schema = $tool->schemaDefinition();

        $this->assertSame(['string', 'null'], $schema['properties']['caller']['type']);
        $this->assertStringContainsString('JSON Tool column', $schema['properties']['caller']['description']);
        $this->assertNotContains('tool', $schema['properties']['filter_column']['enum']);
        $this->assertStringContainsString('caller="Arriba"', $tool->description());
    }
}
