<?php

namespace App\Ai\Gateways;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\Gemini\GeminiGateway as LaravelGeminiGateway;

class GeminiGateway extends LaravelGeminiGateway
{
    /**
     * Gemini's function-declaration schema does not accept the JSON Schema
     * additionalProperties keyword. Laravel AI removes it from the root tool
     * object, but currently leaves it on nested object parameters.
     *
     * @return array<string, mixed>
     */
    protected function mapTool(Tool $tool): array
    {
        return $this->withoutAdditionalProperties(parent::mapTool($tool));
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function withoutAdditionalProperties(array $value): array
    {
        unset($value['additionalProperties']);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->withoutAdditionalProperties($item);
            }
        }

        return $value;
    }
}
