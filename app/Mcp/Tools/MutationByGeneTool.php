<?php

namespace App\Mcp\Tools;

class MutationByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'mutation_by_gene';

    protected string $description = <<<'MARKDOWN'
        Build a project mutation view URL for a given gene symbol and mutation type.
    MARKDOWN;

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewVarAnnotationByGene/'.$projectId.'/'.$gene.'/'.$validated['type'].'/0');
    }

    protected function validationRules(): array
    {
        return [
            'type' => 'nullable|string|in:somatic,germline,rnaseq,variants',
        ];
    }

    protected function normalizeValidated(array $validated): array
    {
        $type = strtolower(trim((string) ($validated['type'] ?? 'somatic')));
        $validated['type'] = $type === '' ? 'somatic' : $type;

        return $validated;
    }

    protected function extraResponsePayload(array $validated): array
    {
        return [
            'type' => $validated['type'],
        ];
    }
}
