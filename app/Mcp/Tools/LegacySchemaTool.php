<?php

namespace App\Mcp\Tools;

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
        return $this->legacySchema();
    }

    /** @return array<string, mixed> */
    abstract protected function legacySchema(): array;
}
