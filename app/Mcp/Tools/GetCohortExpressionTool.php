<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesCohortInput;
use App\Models\CancerType;
use App\Models\Gene;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class GetCohortExpressionTool extends LegacySchemaTool
{
    use ResolvesCohortInput;

    protected string $name = 'getCohortExpression';

    protected string $description = <<<'MARKDOWN'
        Return per-sample RNA-seq TPM expression for one gene in either an
        authorized project or an authorized cancer type (diagnosis). Use this
        tool for gene-expression, RNA-expression, or TPM questions about one
        cohort. Do not use the ChIP-seq tool for expression questions; ChIP-seq
        measures protein-DNA binding, peaks, and assay QC rather than RNA TPM.

        First determine the cohort type from the user's wording. A named data
        project is cohort_type=project: call getProjects first and use its
        numeric project ID. A diagnosis/cancer type is
        cohort_type=cancer_type: call getCancerTypes first and use its exact
        Cancer Type value. Never guess the cohort type or ID.
        When a trusted host page supplies a fixed cohort context, that context
        has already completed this resolution step.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'cohort_type' => 'required|string|in:project,cancer_type',
            'cohort_id' => 'required',
            'gene' => 'required|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'tissue' => 'nullable|string|in:all,tumor,normal',
            'genome_version' => 'nullable|string|in:hg19,hg38',
            'plot_type' => 'nullable|string|in:heatmap,boxplot,violin,barplot,column',
            'group_by' => 'nullable|string|max:100',
            'transform' => 'nullable|string|in:none,log2p1,zscore',
            'group_order' => 'nullable|string|in:none,median_asc,median_desc',
        ]);

        try {
            $cohortType = (string) $validated['cohort_type'];
            [$cohortId, $error] = $this->resolveCohortId(
                $cohortType,
                $validated['cohort_id'],
                $this->name,
            );
            if ($error !== null) {
                return $error;
            }

            $cohortLabel = $cohortType === 'project'
                ? $this->projectLabel((int) $cohortId)
                : (string) $cohortId;
            if ($cohortLabel === null) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Project {$cohortId} was not found or is not accessible to the authorized user.",
                ]);
            }
            if ($cohortType === 'cancer_type' && ! $this->isAvailableCancerType($cohortLabel)) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Cancer type {$cohortLabel} is not an exact Cancer Type value available from getCancerTypes.",
                ]);
            }

            if ($cohortType === 'project'
                && (($validated['plot_type'] ?? null) !== null || ($validated['group_by'] ?? null) !== null)) {
                return $this->projectExpressionPlot(
                    (int) $cohortId,
                    (string) $cohortLabel,
                    $validated,
                );
            }

            $genomeVersion = (string) ($validated['genome_version'] ?? 'hg19');
            $tissue = (string) ($validated['tissue'] ?? 'all');
            $requestedGene = strtoupper(trim((string) $validated['gene']));
            $gene = $this->resolveGeneSymbol($requestedGene, $genomeVersion);
            if ($gene === null) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Gene {$requestedGene} was not found for {$genomeVersion}.",
                ]);
            }

            $expressionData = $this->cohortExpressionData(
                $cohortType,
                $cohortId,
                $gene,
                $tissue,
                $genomeVersion,
            );
            $rows = $this->normalizeExpressionData($expressionData, $gene, $genomeVersion);
            $values = array_column($rows, 'tpm');
            $statistics = $this->statistics($values);
            $rowCount = count($rows);

            $table = [
                'cols' => array_map(static fn (string $title): array => ['title' => $title], [
                    'Patient ID', 'Sample ID', 'TPM',
                ]),
                'data' => array_map(static fn (array $row): array => [
                    $row['patient_id'], $row['sample_id'], $row['tpm'],
                ], $rows),
            ];

            $summary = $rowCount === 0
                ? "No {$gene} RNA-seq TPM values were found in {$cohortLabel}."
                : sprintf(
                    '%d RNA-seq sample(s) with %s TPM in %s; median %s, mean %s, range %s–%s.',
                    $rowCount,
                    $gene,
                    $cohortLabel,
                    $this->formatStatistic($statistics['median']),
                    $this->formatStatistic($statistics['mean']),
                    $this->formatStatistic($statistics['min']),
                    $this->formatStatistic($statistics['max']),
                );

            return $this->cohortResponse([
                'status' => 'success',
                'gene' => $gene,
                'cohort_name' => $cohortLabel,
                'tissue' => $tissue,
                'genome_version' => $genomeVersion,
                'value_type' => 'tpm',
                'rows' => $rows,
                'sample_count' => $rowCount,
                'statistics' => $statistics,
                'count_unit' => 'rnaseq_samples',
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => (string) json_encode($table, JSON_UNESCAPED_SLASHES),
                'title' => "{$gene} expression in {$cohortLabel}",
                'summary' => $summary,
            ], $cohortType, $cohortId, $this->name);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function projectLabel(int $projectId): ?string
    {
        $project = Project::getProject($projectId);
        $name = trim((string) ($project->name ?? ''));

        return $name !== '' ? $name : null;
    }

    protected function isAvailableCancerType(string $cancerTypeId): bool
    {
        [, $rows] = CancerType::getAll();
        foreach ($rows as $row) {
            if ((string) ($row[0] ?? '') === $cancerTypeId) {
                return true;
            }
        }

        return false;
    }

    protected function resolveGeneSymbol(string $requestedGene, string $genomeVersion): ?string
    {
        $gene = Gene::getGene($requestedGene, $genomeVersion);

        return $gene === null ? null : strtoupper(trim((string) $gene->getSymbol()));
    }

    /** @param array<string, mixed> $validated */
    private function projectExpressionPlot(int $projectId, string $projectName, array $validated): ResponseFactory
    {
        $response = app(ExpressionByGeneTool::class)->handle(new Request([
            'project_id' => $projectId,
            'gene' => $validated['gene'],
            'genome_version' => $validated['genome_version'] ?? 'hg19',
            'value_type' => 'tpm',
            'plot_type' => $validated['plot_type'] ?? null,
            'group_by' => $validated['group_by'] ?? null,
            'dataset_scope' => $validated['tissue'] ?? 'all',
            'transform' => $validated['transform'] ?? 'none',
            'group_order' => $validated['group_order'] ?? 'none',
        ]));
        $content = $response->getStructuredContent();
        if (! is_array($content)) {
            return Response::structured([
                'status' => 'error',
                'action' => $this->name,
                'message' => 'The project expression result was unavailable.',
            ]);
        }

        $content['cohort_type'] = 'project';
        $content['cohort_id'] = $projectId;
        $content['cohort_name'] = $projectName;

        return Response::structured($content);
    }

    /** @return array<string, mixed> */
    protected function cohortExpressionData(
        string $cohortType,
        int|string $cohortId,
        string $gene,
        string $tissue,
        string $genomeVersion,
    ): array {
        if ($cohortType === 'project') {
            $project = Project::getProject((int) $cohortId);

            return $project === null ? [] : $project->getGeneExpression(
                [$gene],
                $genomeVersion,
                'all',
                'gene',
                false,
                $tissue,
                'tpm',
                true,
            );
        }

        $cancerType = CancerType::find((string) $cohortId);

        return $cancerType === null ? [] : $cancerType->getGeneExpression(
            [$gene],
            $genomeVersion,
            'all',
            'gene',
            false,
            $tissue,
            'tpm',
            true,
            'Y',
        );
    }

    /**
     * @param  array<string, mixed>  $expressionData
     * @return array<int, array<string, int|float|string>>
     */
    private function normalizeExpressionData(
        array $expressionData,
        string $gene,
        string $genomeVersion,
    ): array {
        $geneData = $expressionData['exp_data'][$gene] ?? null;
        if (! is_array($geneData)) {
            foreach ((array) ($expressionData['exp_data'] ?? []) as $symbol => $candidate) {
                if (strcasecmp((string) $symbol, $gene) === 0 && is_array($candidate)) {
                    $geneData = $candidate;
                    break;
                }
            }
        }
        if (! is_array($geneData)) {
            return [];
        }

        $values = $geneData[$genomeVersion] ?? reset($geneData);
        $sampleIds = array_values((array) ($expressionData['sample_ids'] ?? []));
        $sampleNames = array_values((array) ($expressionData['samples'] ?? []));
        $patients = (array) ($expressionData['patients'] ?? []);
        $rows = [];
        foreach ((array) $values as $index => $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $sampleId = trim((string) ($sampleIds[$index] ?? $sampleNames[$index] ?? ''));
            $sampleName = trim((string) ($sampleNames[$index] ?? $sampleId));
            $rows[] = [
                'patient_id' => trim((string) ($patients[$sampleName] ?? '')),
                'sample_id' => $sampleId,
                'tpm' => round((float) $value, 4),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strcasecmp((string) $left['patient_id'], (string) $right['patient_id'])
                ?: strcasecmp((string) $left['sample_id'], (string) $right['sample_id'])
        );

        return $rows;
    }

    /** @param array<int, int|float> $values @return array<string, float|null> */
    private function statistics(array $values): array
    {
        if ($values === []) {
            return ['mean' => null, 'median' => null, 'min' => null, 'max' => null];
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 0
            ? ((float) $values[$middle - 1] + (float) $values[$middle]) / 2
            : (float) $values[$middle];

        return [
            'mean' => round(array_sum($values) / $count, 4),
            'median' => round($median, 4),
            'min' => round((float) $values[0], 4),
            'max' => round((float) $values[$count - 1], 4),
        ];
    }

    private function formatStatistic(?float $value): string
    {
        return $value === null ? 'N/A' : rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->cohortProperties(), [
                'gene' => [
                    'type' => 'string',
                    'description' => 'Exact gene symbol, for example FGFR4.',
                ],
                'tissue' => [
                    'type' => ['string', 'null'],
                    'enum' => ['all', 'tumor', 'normal', null],
                    'description' => 'Include all, tumor, or normal RNA-seq samples. Defaults to all.',
                ],
                'genome_version' => [
                    'type' => ['string', 'null'],
                    'enum' => ['hg19', 'hg38', null],
                    'description' => 'Genome version. Defaults to hg19.',
                ],
                'plot_type' => [
                    'type' => ['string', 'null'],
                    'enum' => ['heatmap', 'boxplot', 'violin', 'barplot', 'column', null],
                    'description' => 'Optional expression plot requested by the user.',
                ],
                'group_by' => [
                    'type' => ['string', 'null'],
                    'description' => 'Exact project metadata field used to group the expression plot, such as Diagnosis.',
                ],
                'transform' => [
                    'type' => ['string', 'null'],
                    'enum' => ['none', 'log2p1', 'zscore', null],
                    'description' => 'Expression transformation used by the plot.',
                ],
                'group_order' => [
                    'type' => ['string', 'null'],
                    'enum' => ['none', 'median_asc', 'median_desc', null],
                    'description' => 'Optional ordering of plot groups.',
                ],
            ]),
            'required' => ['cohort_type', 'cohort_id', 'gene'],
            'additionalProperties' => false,
        ];
    }
}
