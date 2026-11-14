<?php

namespace App\Mcp\Tools;

use App\Services\OncogenomicsWiki;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Throwable;

class SearchOncogenomicsWikiTool extends LegacySchemaTool
{
    protected string $name = 'searchOncogenomicsWiki';
    protected string $description = 'Search the Oncogenomics wiki tutorials for how-to instructions, interface navigation, filters, tier definitions, and documented workflows. No project/cohort is needed. Returns bounded excerpts with source URLs; cite those URLs. For actual patient/project counts and results, use the authorized data tools.';

    public function handle(Request $request): ResponseFactory
    {
        $input = $request->validate(['query' => 'required|string|max:500', 'limit' => 'nullable|integer|min:1|max:8']);
        try {
            $result = app(OncogenomicsWiki::class)->search(trim($input['query']), (int) ($input['limit'] ?? 5));
            return Response::structured(['status' => 'success', 'action' => $this->name,
                'title' => 'Oncogenomics Wiki Tutorials',
                'summary' => $result['matches'] === []
                    ? 'No matching tutorial passages found. Try more specific feature names or explain the documentation gap.'
                    : count($result['matches']).' relevant tutorial excerpt(s) found.',
                ...$result]);
        } catch (Throwable $e) {
            return Response::structured(['status' => 'error', 'action' => $this->name, 'message' => $e->getMessage()]);
        }
    }

    protected function legacySchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'query' => ['type' => 'string', 'description' => 'Tutorial question or feature keywords, such as project expression heatmap download.'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 8, 'description' => 'Maximum excerpts to return; default 5.'],
        ], 'required' => ['query'], 'additionalProperties' => false];
    }
}
