<?php

namespace App\Mcp\Tools;

use App\Services\CohortAnalysisService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class RunDifferentialExpressionTool extends Tool
{
    protected string $name = 'runDifferentialExpression';

    protected string $description = <<<'MARKDOWN'
        Compare two project-scoped RNA-seq cohorts with DESeq2 using the
        project's preprocessed raw count matrix. Each cohort is defined by an
        exact fusion pair and fusion_status (positive or negative), and may also
        specify an exact diagnosis and fusion caller. A negative cohort is the
        RNA-seq complement lacking that exact fusion within the same project and
        optional diagnosis. Call getCohortSchema first. This tool performs the analysis on
        the server and returns ranked genes; it never downloads the full matrix
        to the MCP client. Positive log2FoldChange means higher expression in
        group_a than group_b.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'group_a' => 'required|array',
            'group_a.left_gene' => 'required|string|max:100',
            'group_a.right_gene' => 'required|string|max:100',
            'group_a.diagnosis' => 'nullable|string|max:200',
            'group_a.caller' => 'nullable|string|max:100',
            'group_a.fusion_status' => 'nullable|string|in:positive,negative',
            'group_b' => 'required|array',
            'group_b.left_gene' => 'required|string|max:100',
            'group_b.right_gene' => 'required|string|max:100',
            'group_b.diagnosis' => 'nullable|string|max:200',
            'group_b.caller' => 'nullable|string|max:100',
            'group_b.fusion_status' => 'nullable|string|in:positive,negative',
            'genome_version' => 'nullable|string|max:30',
            'alpha' => 'nullable|numeric|min:0.000001|max:0.5',
            'minimum_samples_per_group' => 'nullable|integer|min:2|max:1000',
            'limit' => 'nullable|integer|min:1|max:500',
            'return_all_genes' => 'nullable|boolean',
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $service = $this->service();
            $project = $service->accessibleProject($projectId);
            if ($project === null) {
                return Response::structured([
                    'status' => 'error', 'action' => $this->name,
                    'message' => "Project {$projectId} was not found or is not accessible to the authorized user.",
                ]);
            }

            $groupA = $service->fusionCohortSamples($projectId, $validated['group_a']);
            $groupB = $service->fusionCohortSamples($projectId, $validated['group_b']);
            $minimum = (int) ($validated['minimum_samples_per_group'] ?? 2);
            if (count($groupA) < $minimum || count($groupB) < $minimum) {
                return Response::structured([
                    'status' => 'insufficient_samples', 'action' => $this->name,
                    'group_a_sample_count' => count($groupA), 'group_b_sample_count' => count($groupB),
                    'minimum_samples_per_group' => $minimum,
                    'message' => "DESeq2 requires at least {$minimum} resolved RNA-seq samples in each group.",
                ]);
            }

            $overlap = array_values(array_intersect(
                array_column($groupA, 'sample_id'),
                array_column($groupB, 'sample_id')
            ));
            if ($overlap !== []) {
                return Response::structured([
                    'status' => 'invalid_cohorts', 'action' => $this->name,
                    'overlapping_sample_ids' => $overlap,
                    'message' => 'The comparison cohorts overlap; each sample must belong to only one group.',
                ]);
            }

            $alpha = (float) ($validated['alpha'] ?? 0.05);
            $matrix = $service->countMatrixPath($projectId, $validated['genome_version'] ?? null);
            $analysis = $service->runDifferentialExpression($matrix, $groupA, $groupB, $alpha);
            $includedCounts = (array) ($analysis['included_group_counts'] ?? [
                'group_a' => count($groupA),
                'group_b' => count($groupB),
            ]);
            if (($includedCounts['group_a'] ?? 0) < $minimum || ($includedCounts['group_b'] ?? 0) < $minimum) {
                return Response::structured([
                    'status' => 'insufficient_samples', 'action' => $this->name,
                    'group_a_sample_count' => (int) ($includedCounts['group_a'] ?? 0),
                    'group_b_sample_count' => (int) ($includedCounts['group_b'] ?? 0),
                    'ignored_missing_sample_ids' => (array) ($analysis['missing_sample_ids'] ?? []),
                    'minimum_samples_per_group' => $minimum,
                    'message' => "At least {$minimum} count-matrix samples are required in each group after missing samples are ignored.",
                ]);
            }
            $returnAllGenes = (bool) ($validated['return_all_genes'] ?? false);
            $limit = (int) ($validated['limit'] ?? 100);
            $genes = $returnAllGenes
                ? array_values($analysis['rows'])
                : array_slice($analysis['rows'], 0, $limit);
            $table = [
                'cols' => array_map(static fn (string $title): array => ['title' => $title], ['Gene', 'Base mean', 'Log2 fold change', 'SE', 'Statistic', 'P value', 'Adjusted P value']),
                'data' => array_map(static fn (array $row): array => [
                    $row['gene'] ?? null, $row['baseMean'] ?? null, $row['log2FoldChange'] ?? null,
                    $row['lfcSE'] ?? null, $row['stat'] ?? null, $row['pvalue'] ?? null, $row['padj'] ?? null,
                ], $genes),
            ];

            return Response::structured([
                'status' => 'success', 'action' => $this->name,
                'project_id' => $projectId, 'project_name' => trim((string) $project->name),
                'method' => 'DESeq2', 'contrast' => 'group_a / group_b', 'alpha' => $alpha,
                'count_preprocessing' => 'Nonnegative expected counts were rounded to integers before DESeq2.',
                'lfc_shrinkage' => $analysis['lfc_shrinkage'] ?? 'normal',
                'lfc_threshold' => $analysis['lfc_threshold'] ?? 1.0,
                'group_a' => $validated['group_a'], 'group_b' => $validated['group_b'],
                'group_a_sample_count' => (int) ($includedCounts['group_a'] ?? count($groupA)),
                'group_b_sample_count' => (int) ($includedCounts['group_b'] ?? count($groupB)),
                'ignored_missing_sample_ids' => (array) ($analysis['missing_sample_ids'] ?? []),
                'ignored_missing_sample_count' => count((array) ($analysis['missing_sample_ids'] ?? [])),
                'tested_gene_count' => $analysis['tested_gene_count'],
                'significant_gene_count' => $analysis['significant_gene_count'],
                'return_all_genes' => $returnAllGenes,
                'returned_gene_count' => count($genes), 'genes' => $genes,
                'data_type' => 'table', 'display_type' => 'table',
                'table_json' => (string) json_encode($table, JSON_UNESCAPED_SLASHES),
                'volcano_plot' => [
                    'mime_type' => $analysis['volcano_plot_mime_type'] ?? 'image/png',
                    'base64' => $analysis['volcano_plot_base64'] ?? null,
                    'x_axis' => 'Shrunken log2 fold change (group A / group B)',
                    'y_axis' => '-log10 adjusted p-value',
                ],
                'title' => 'Differential expression: group A versus group B',
                'summary' => $analysis['significant_gene_count'].' gene(s) have adjusted P value <= '.$alpha.'. Positive log2 fold change indicates higher expression in group A.',
            ]);
        } catch (\Throwable $e) {
            return Response::structured(['status' => 'error', 'action' => $this->name, 'message' => $e->getMessage()]);
        }
    }

    protected function service(): CohortAnalysisService
    {
        return app(CohortAnalysisService::class);
    }

    public function schema($schema = null): array
    {
        $cohort = [
            'type' => 'object',
            'properties' => [
                'left_gene' => ['type' => 'string'], 'right_gene' => ['type' => 'string'],
                'diagnosis' => ['type' => ['string', 'null']], 'caller' => ['type' => ['string', 'null']],
                'fusion_status' => [
                    'type' => ['string', 'null'],
                    'enum' => ['positive', 'negative', null],
                    'default' => 'positive',
                    'description' => 'positive selects samples with this exact fusion; negative selects the RNA-seq complement without it.',
                ],
            ],
            'required' => ['left_gene', 'right_gene'], 'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer'], 'group_a' => $cohort, 'group_b' => $cohort,
                'genome_version' => ['type' => ['string', 'null']],
                'alpha' => ['type' => ['number', 'null'], 'default' => 0.05],
                'minimum_samples_per_group' => ['type' => ['integer', 'null'], 'default' => 2],
                'limit' => ['type' => ['integer', 'null'], 'default' => 100, 'maximum' => 500],
                'return_all_genes' => [
                    'type' => ['boolean', 'null'],
                    'default' => false,
                    'description' => 'When true, return every tested gene and ignore limit. This can produce a very large MCP response.',
                ],
            ],
            'required' => ['project_id', 'group_a', 'group_b'], 'additionalProperties' => false,
        ];
    }
}
