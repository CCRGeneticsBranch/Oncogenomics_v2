<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\CompactTableResult;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Adapts the application's existing raw JSON schemas to Laravel MCP's typed
 * schema API. Keeping the raw definitions in one place avoids changing the
 * external MCP contract during the framework upgrade.
 */
abstract class LegacySchemaTool extends Tool
{
    protected bool $supportsCompactOutput = false;

    protected function resultResponse(Request $request, array $content): ResponseFactory
    {
        try {
            $content = CompactTableResult::apply($content, $request->get('output'));
        } catch (\InvalidArgumentException $e) {
            $content = ['status' => 'error', 'action' => $this->name, 'message' => $e->getMessage()];
        }
        return Response::structured($content);
    }

    /**
     * Clinomics MCP tools query or analyze data; none mutate source records.
     * Advertising that contract lets non-interactive MCP clients execute them
     * without waiting for an approval prompt that no browser user can answer.
     *
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        return [
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    final public function schema(JsonSchema $schema): array
    {
        $definition = $this->schemaDefinition();
        $required = array_fill_keys($definition['required'] ?? [], true);
        $properties = [];

        foreach ($definition['properties'] ?? [] as $name => $propertyDefinition) {
            $type = JsonSchemaFactory::fromArray($propertyDefinition);

            if (isset($required[$name])) {
                $type->required();
            }

            $properties[$name] = $type;
        }

        return $properties;
    }

    /**
     * Exposes the raw definition for focused contract tests and internal
     * consumers that still inspect the complete JSON Schema document.
     *
     * @return array<string, mixed>
     */
    final public function schemaDefinition(): array
    {
        $definition = $this->legacySchema();
        if ($this->supportsCompactOutput) {
            $definition['properties']['output'] = CompactTableResult::schema();
        }
        return $definition;
    }

    /** @return array<string, mixed> */
    abstract protected function legacySchema(): array;
}
