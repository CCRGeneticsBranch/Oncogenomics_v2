<?php

namespace App\Mcp\Tools;

use App\Models\Gene;
use App\Models\Patient;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class ExpressionByGeneTool extends BaseGeneRedirectTool
{
    protected string $name = 'expression_by_gene';

    protected string $description = <<<'MARKDOWN'
        Get project expression data JSON for a given gene symbol.
        The JSON contains tumor_project_data and normal_project_data. Metadata
        labels are in each dataset's meta_data.attr_list and sample values use
        the matching index in meta_data.data. Returns both datasets unless
        dataset_scope requests only tumor or normal data. Supports downstream
        LLM-driven heatmap, boxplot, violin, barplot, and column plotting grouped by metadata.
        Plot rows preserve raw expression values. The transform field records
        the user's requested downstream LLM transformation.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'gene' => 'required|string|max:100',
            'patient_id' => 'nullable|string|max:100',
            'case_id' => 'nullable|string|max:100',
            'genome_version' => 'nullable|string|max:20',
            'library_type' => 'nullable|string|max:20',
            'value_type' => 'nullable|string|max:50',
            'plot_type' => 'nullable|string|in:heatmap,boxplot,violin,barplot,column',
            'group_by' => 'nullable|string|max:100',
            'dataset_scope' => 'nullable|string|in:all,tumor,normal',
            'transform' => 'nullable|string|in:none,log2p1,zscore',
            'group_order' => 'nullable|string|in:none,median_asc,median_desc',
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $gene = strtoupper(trim((string) $validated['gene']));
            $patientId = $this->normalizeOptionalId($validated['patient_id'] ?? null);
            $caseId = $this->normalizeOptionalId($validated['case_id'] ?? null);
            $genomeVersion = trim((string) ($validated['genome_version'] ?? 'hg19'));
            $libraryType = trim((string) ($validated['library_type'] ?? 'all'));
            $valueType = trim((string) ($validated['value_type'] ?? 'tmm-rpkm'));
            $plotType = $validated['plot_type'] ?? null;
            $groupBy = $validated['group_by'] ?? null;
            $datasetScope = $validated['dataset_scope'] ?? 'all';
            $transform = $validated['transform'] ?? 'none';
            $groupOrder = $validated['group_order'] ?? 'none';

            if ($genomeVersion === '' || strtolower($genomeVersion) === 'null') {
                $genomeVersion = 'hg19';
            }
            if ($libraryType === '') {
                $libraryType = 'all';
            }
            if ($valueType === '') {
                $valueType = 'tmm-rpkm';
            }

            $project = Project::getProject($projectId);
            if ($project === null) {
                return Response::structured([
                    'status' => 'error',
                    'message' => "Project {$projectId} not found",
                    'action' => 'expression_by_gene',
                ]);
            }

            $expressionJson = $this->buildExpressionByGeneListJson(
                $projectId,
                $patientId,
                $caseId,
                $gene,
                $genomeVersion,
                $libraryType,
                $valueType
            );
            $plotResult = $this->buildPlotRows($expressionJson, $gene, $genomeVersion, $groupBy, $datasetScope);

            return Response::structured([
                'status' => 'success',
                'action' => 'expression_by_gene',
                'project_id' => $projectId,
                'project_name' => $project->name,
                'gene' => $gene,
                'patient_id' => $patientId,
                'case_id' => $caseId,
                'genome_version' => $genomeVersion,
                'library_type' => $libraryType,
                'value_type' => $valueType,
                'plot_type' => $plotType,
                'group_by' => $groupBy,
                'dataset_scope' => $datasetScope,
                'transform' => $transform,
                'group_order' => $groupOrder,
                'metadata_fields' => $plotResult['metadata_fields'],
                'metadata_options' => $plotResult['metadata_options'],
                'plot_rows' => $plotResult['rows'],
                'display_type' => 'expression_data_json',
                'expression_data_json' => $expressionJson,
                'plot_generation_hint' => 'Choose one exact metadata label and zero-based index from each included dataset meta_data.attr_list. Read metadata values from the same index in meta_data.data. Never default to index 0 when no field matches. transform=log2p1 means plot expression is log2(raw_expression+1). transform=zscore means z=(x-mean)/standard_deviation using all plotted raw_expression values. group_order=median_asc or median_desc orders completed groups by transformed-value median. barplot and column use each group median as the bar value.',
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'message' => $e->getMessage(),
                'action' => 'expression_by_gene',
            ]);
        }
    }

    protected function buildRedirectUrl(int $projectId, string $gene, array $validated): string
    {
        return url('/viewProjectExpressionByGene/'.$projectId.'/'.$gene);
    }

    private function normalizeOptionalId($value): string
    {
        if ($value === null) {
            return 'null';
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || strtolower($normalized) === 'null') {
            return 'null';
        }

        return $normalized;
    }

    private function buildExpressionByGeneListJson(
        int $projectId,
        string $patientId,
        string $caseId,
        string $gene,
        string $genomeVersion,
        string $libraryType,
        string $valueType
    ): string {
        $genes = [$gene];
        $highlightSamples = [];

        if ($patientId !== 'null') {
            $patient = Patient::where('patient_id', '=', $patientId)->first();
            if ($patient !== null && $patient->samples != null) {
                foreach ($patient->samples as $sample) {
                    if (($sample->exp_type ?? null) !== 'RNAseq') {
                        continue;
                    }
                    if ($caseId !== 'null' && (string) ($sample->case_id ?? '') !== $caseId) {
                        continue;
                    }
                    $sampleName = (string) ($sample->sample_name ?? '');
                    if ($sampleName !== '') {
                        $highlightSamples[] = $sampleName;
                    }
                }
            }
        }

        $project = Project::getProject($projectId);
        $geneMeta = Gene::getSurfaceInfo($genes);
        $tumorProjectData = $project->getGeneExpression($genes, $genomeVersion, $libraryType, 'gene', true, 'all', $valueType);

        $normalProject = Project::getNormalProject();
        $normalProjectData = null;
        if ($normalProject !== null) {
            $normalProjectData = $normalProject->getGeneExpression($genes, $genomeVersion, $libraryType, 'gene', true, 'normal', $valueType);
        }

        return json_encode([
            'hight_light_samples' => $highlightSamples,
            'tumor_project_data' => $tumorProjectData,
            'normal_project_data' => $normalProjectData,
            'gene_meta' => $geneMeta,
        ]);
    }

    private function buildPlotRows(
        string $expressionJson,
        string $gene,
        string $genomeVersion,
        ?string $groupBy,
        string $datasetScope
    ): array
    {
        $payload = json_decode($expressionJson, true);
        $datasets = [];
        if ($datasetScope !== 'normal') {
            $datasets['tumor'] = $payload['tumor_project_data'] ?? [];
        }
        if ($datasetScope !== 'tumor') {
            $datasets['normal'] = $payload['normal_project_data'] ?? [];
        }

        $rows = [];
        $metadataFields = [];
        $metadataOptions = [];
        foreach ($datasets as $datasetName => $projectData) {
            if (!is_array($projectData) || empty($projectData)) {
                continue;
            }
            $datasetResult = $this->buildDatasetPlotRows(
                $projectData,
                $datasetName,
                $gene,
                $genomeVersion,
                $groupBy,
                $datasetScope === 'all'
            );
            $rows = array_merge($rows, $datasetResult['rows']);
            $metadataFields[$datasetName] = $datasetResult['metadata_field'];
            $metadataOptions[$datasetName] = array_values($projectData['meta_data']['attr_list'] ?? []);
        }

        return [
            'rows' => $rows,
            'metadata_fields' => $metadataFields,
            'metadata_options' => $metadataOptions,
        ];
    }

    private function buildDatasetPlotRows(
        array $projectData,
        string $datasetName,
        string $gene,
        string $genomeVersion,
        ?string $groupBy,
        bool $includeDatasetInGroup
    ): array {
        $samples = $projectData['samples'] ?? [];
        $patients = $projectData['patients'] ?? [];
        $expressionByGenome = $projectData['exp_data'][$gene] ?? [];
        $expressionValues = $expressionByGenome[$genomeVersion] ?? reset($expressionByGenome);
        if (!is_array($expressionValues)) {
            return ['rows' => [], 'metadata_field' => null];
        }

        $metadata = $projectData['meta_data'] ?? [];
        $metadataNames = $metadata['attr_list'] ?? [];
        $metadataIndex = $this->findMetadataIndex($metadataNames, $groupBy);
        $metadataField = $metadataIndex !== null ? (string) $metadataNames[$metadataIndex] : null;

        $rows = [];
        foreach ($samples as $index => $sample) {
            if (!array_key_exists($index, $expressionValues) || !is_numeric($expressionValues[$index])) {
                continue;
            }
            $sampleMetadata = $metadata['data'][$sample] ?? [];
            $metadataValue = $metadataIndex !== null ? ($sampleMetadata[$metadataIndex] ?? 'N/A') : 'All';
            $metadataValue = trim((string) $metadataValue);
            if ($metadataValue === '') {
                $metadataValue = 'N/A';
            }
            $datasetLabel = ucfirst($datasetName);
            $group = $includeDatasetInGroup ? $datasetLabel.' | '.$metadataValue : $metadataValue;
            $expression = (float) $expressionValues[$index];
            $rows[] = [
                'sample' => (string) $sample,
                'patient_id' => (string) ($patients[$sample] ?? ''),
                'expression' => $expression,
                'raw_expression' => (float) $expressionValues[$index],
                'dataset' => $datasetName,
                'metadata_field' => $metadataField,
                'metadata_value' => $metadataValue,
                'group' => $group,
            ];
        }

        return ['rows' => $rows, 'metadata_field' => $metadataField];
    }

    private function findMetadataIndex(array $metadataNames, ?string $groupBy): ?int
    {
        if ($groupBy === null || trim($groupBy) === '') {
            return null;
        }

        $requested = $this->normalizeMetadataName($groupBy);
        foreach ($metadataNames as $index => $metadataName) {
            $segments = preg_split('/[|\/]+/', (string) $metadataName);
            foreach ($segments as $segment) {
                if ($this->normalizeMetadataName($segment) === $requested) {
                    return $index;
                }
            }
            if ($this->normalizeMetadataName($metadataName) === $requested) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeMetadataName(string $name): string
    {
        return strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '', $name)));
    }
    
    public function schema($schema = null): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => [
                    'type' => 'integer',
                    'description' => 'Current project ID supplied by the chatbot context.',
                ],
                'gene' => [
                    'type' => 'string',
                    'description' => 'Gene symbol to query (for example FGFR4).',
                ],
                'patient_id' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional patient ID to highlight project samples.',
                ],
                'case_id' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional case ID used with patient_id to narrow highlighted samples.',
                ],
                'genome_version' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional genome version (default: hg19).',
                ],
                'library_type' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional library type filter (default: all).',
                ],
                'value_type' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional expression value type such as tmm-rpkm or tpm (default: tmm-rpkm).',
                ],
                'plot_type' => [
                    'type' => ['string', 'null'],
                    'enum' => ['heatmap', 'boxplot', 'violin', 'barplot', 'column', null],
                    'description' => 'Optional downstream plot type hint.',
                ],
                'group_by' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional metadata field label to group expression values by.',
                ],
                'dataset_scope' => [
                    'type' => ['string', 'null'],
                    'enum' => ['all', 'tumor', 'normal', null],
                    'description' => 'Optional dataset scope (default: all).',
                ],
                'transform' => [
                    'type' => ['string', 'null'],
                    'enum' => ['none', 'log2p1', 'zscore', null],
                    'description' => 'Optional downstream transform hint.',
                ],
                'group_order' => [
                    'type' => ['string', 'null'],
                    'enum' => ['none', 'median_asc', 'median_desc', null],
                    'description' => 'Optional grouped-plot ordering hint.',
                ],
            ],
            'required' => ['project_id', 'gene'],
            'additionalProperties' => false,
        ];
    }
}
