<?php

namespace App\Mcp\Tools;

use App\Models\Gene;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetExpGeneSummaryTool extends LegacySchemaTool
{
    protected bool $supportsCompactOutput = true;

    protected string $name = 'getExpGeneSummary';

    protected string $description = <<<'MARKDOWN'
        Return gene TPM across all authorized RNA-seq samples, grouped by diagnosis or project as [patient_id, sample_id, tpm]. No project or cancer type is required. Defaults to category=diagnosis and tissue=all. For a global boxplot, set plot_type=boxplot and category=diagnosis (or project). Charts use all authorized values server-side; no project or raw-data retrieval by the LLM is needed. Supports transform and group_order.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'gene_id' => 'required|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'category' => 'sometimes|string|in:diagnosis,project',
            'group_by' => 'sometimes|string|in:diagnosis,project,diangosis',
            'plot_type' => 'nullable|string|in:boxplot,violin,barplot,column,heatmap',
            'transform' => 'sometimes|string|in:none,log2p1,zscore',
            'group_order' => 'sometimes|string|in:none,median_asc,median_desc',
            'tissue' => 'sometimes|string|in:all,tumor,normal',
            'genome_version' => 'nullable|string|in:hg19,hg38',
            'lib_type' => 'nullable|string|in:all,polyA,nonPolyA',
        ]);

        if (isset($validated['group_by'])) {
            $validated['category'] = $validated['group_by'] === 'diangosis' ? 'diagnosis' : $validated['group_by'];
        }
        $validated = array_merge(['category' => 'diagnosis', 'tissue' => 'all'], $validated);
        if (\App\Models\User::getCurrentUser() === null) {
            return $this->resultResponse($request, ['status' => 'error', 'action' => $this->name,
                'message' => 'Sign in to query authorized gene data.']);
        }
        try {
            $genomeVersion = $validated['genome_version'] ?? 'hg19';
            $libType = $validated['lib_type'] ?? 'all';
            $requestedGene = strtoupper(trim((string) $validated['gene_id']));
            $gene = Gene::getGene($requestedGene, $genomeVersion);

            if ($gene === null) {
                return $this->resultResponse($request, [
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Gene {$requestedGene} was not found for {$genomeVersion}.",
                ]);
            }

            $geneSymbol = $gene->getSymbol();
            $data = Gene::getExpGeneSummary(
                $geneSymbol,
                $validated['category'],
                $validated['tissue'],
                $genomeVersion,
                $libType
            );

            ksort($data, SORT_NATURAL | SORT_FLAG_CASE);
            $sampleCount = array_sum(array_map('count', $data));
            $groupCount = count($data);
            $rows = [];
            foreach ($data as $group => $samples) {
                foreach ($samples as $sample) {
                    $rows[] = ['group' => (string) $group, 'patient_id' => $sample[0] ?? null,
                        'sample_id' => $sample[1] ?? null, 'raw_expression' => $sample[2] ?? null];
                }
            }

            return $this->resultResponse($request, [
                'status' => 'success',
                'action' => $this->name,
                'scope' => 'all_authorized',
                'gene_id' => $geneSymbol,
                'gene' => $geneSymbol,
                'plot_type' => $validated['plot_type'] ?? null,
                'group_by' => $validated['category'],
                'transform' => $validated['transform'] ?? 'none',
                'group_order' => $validated['group_order'] ?? 'none',
                'plot_rows' => $rows,
                'category' => $validated['category'],
                'tissue' => $validated['tissue'],
                'genome_version' => $genomeVersion,
                'lib_type' => $libType,
                'value_type' => 'tpm',
                'data' => $data,
                'group_count' => $groupCount,
                'sample_count' => $sampleCount,
                'display_type' => 'grouped_expression_data',
                'summary' => "{$sampleCount} sample(s) in {$groupCount} {$validated['category']} group(s).",
            ]);
        } catch (\Throwable $e) {
            return $this->resultResponse($request, [
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'plot_type' => ['type' => 'string', 'enum' => ['boxplot', 'violin', 'barplot', 'column', 'heatmap'], 'description' => 'Request a chart across all authorized samples; boxplots include all groups using exact server-computed statistics.'],
                'group_by' => ['type' => 'string', 'enum' => ['diagnosis', 'project'], 'description' => 'Alias for category. No cohort restriction.'],
                'transform' => ['type' => 'string', 'enum' => ['none', 'log2p1', 'zscore']],
                'group_order' => ['type' => 'string', 'enum' => ['none', 'median_asc', 'median_desc']],
                'gene_id' => [
                    'type' => 'string',
                    'description' => 'Gene symbol, for example FGFR4.',
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => ['diagnosis', 'project'],
                    'description' => 'Grouping field; defaults to diagnosis.',
                ],
                'tissue' => [
                    'type' => 'string',
                    'enum' => ['all', 'tumor', 'normal'],
                    'description' => 'Include all samples (default), tumor samples, or normal samples.',
                ],
                'genome_version' => [
                    'type' => ['string', 'null'],
                    'enum' => ['hg19', 'hg38', null],
                    'description' => 'Genome version. Defaults to hg19.',
                ],
                'lib_type' => [
                    'type' => ['string', 'null'],
                    'enum' => ['all', 'polyA', 'nonPolyA', null],
                    'description' => 'RNA library type. Defaults to all.',
                ],
            ],
            'required' => ['gene_id'],
            'additionalProperties' => false,
        ];
    }
}
