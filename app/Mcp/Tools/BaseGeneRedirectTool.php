<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

abstract class BaseGeneRedirectTool extends LegacySchemaTool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate(array_merge([
            'project_id' => 'required|integer',
            'gene' => 'required|string|max:100',
        ], $this->validationRules()));

        $projectId = (int) $validated['project_id'];
        $gene = strtoupper(trim((string) $validated['gene']));

        $validated = $this->normalizeValidated($validated);

        $payload = array_merge([
            'action' => $this->actionName(),
            'redirect_url' => $this->buildRedirectUrl($projectId, $gene, $validated),
            'project_id' => $projectId,
            'gene' => $gene,
        ], $this->extraResponsePayload($validated));

        return Response::structured($payload);
    }

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge([
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Authorized numeric project ID returned by getProjects, or supplied by a fixed project chatbot context.',
                ],
                'gene' => [
                    'type' => 'string',
                    'description' => 'Gene symbol the view should be opened for, e.g. "TP53".',
                ],
            ], $this->extraSchemaProperties()),
            'required' => ['project_id', 'gene'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Extra JSON schema properties contributed by a subclass, keyed by property name.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function extraSchemaProperties(): array
    {
        return [];
    }

    protected function actionName(): string
    {
        return $this->name();
    }

    /**
     * @return array<string, string>
     */
    protected function validationRules(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeValidated(array $validated): array
    {
        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    abstract protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string;

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function extraResponsePayload(array $validated): array
    {
        return [];
    }
}
