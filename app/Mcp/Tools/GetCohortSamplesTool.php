<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesCohortInput;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class GetCohortSamplesTool extends LegacySchemaTool
{
    protected bool $supportsCompactOutput = true;

    use ResolvesCohortInput;

    /** @var array<int, string> */
    private array $expTypes = [
        'Exome',
        'HiC',
        'Panel',
        'ChIPseq',
        'TCR',
        'Methylseq',
        'RNAseq',
        'Whole Genome',
    ];

    protected string $name = 'getCohortSamples';

    protected string $description = <<<'MARKDOWN'
        List authorized project or diagnosis samples. Pass all requested filters: exp_type, library_type, diagnosis. In project chat keep the project cohort and filter diagnosis; never infer disease from sample names. For RMS including subtypes use diagnosis=Rhabdomyosarcoma, diagnosis_match=contains. Counts and tables reflect the filters.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'cohort_type' => 'required|string|in:project,cancer_type',
            'cohort_id' => 'required',
            'exp_type' => 'nullable|string',
            'library_type' => 'nullable|string|max:200',
            'diagnosis' => 'nullable|string|max:200',
            'diagnosis_match' => 'nullable|in:exact,contains',
        ]);

        try {
            $cohortType = (string) $validated['cohort_type'];
            $expType = trim((string) ($validated['exp_type'] ?? ''));
            $libraryType = trim((string) ($validated['library_type'] ?? ''));
            if ($cohortType === 'project' && $expType === '') {
                return $this->resultResponse($request, [
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => 'exp_type is required for a project sample cohort.',
                ]);
            }
            $normalizedExpType = $expType === '' ? null : $this->normalizeExpType($expType);
            if ($expType !== '' && $normalizedExpType === null) {
                return $this->resultResponse($request, [
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => 'Invalid exp_type. Expected one of: '.implode(', ', $this->expTypes).'.',
                ]);
            }

            [$cohortId, $error] = $this->resolveCohortId(
                $cohortType,
                $validated['cohort_id'],
                $this->name
            );
            if ($error !== null) {
                return $error;
            }

            if ($cohortType === 'project') {
                $content = $this->projectSamples((int) $cohortId, (string) $normalizedExpType);
            } else {
                $content = $this->cancerTypeSamples((string) $cohortId);
            }
            $content = $this->filterSampleResult(
                $content,
                $cohortType === 'cancer_type' ? $normalizedExpType : null,
                $libraryType,
                trim((string) ($validated['diagnosis'] ?? '')),
                $validated['diagnosis_match'] ?? 'exact',
            );

            return $this->cohortResponse($content, $cohortType, $cohortId, $this->name, $request);
        } catch (\Throwable $e) {
            return $this->resultResponse($request, [
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    protected function projectSamples(int $projectId, string $expType): array
    {
        $expType = $this->normalizeExpType($expType);
        if ($expType === null) {
            return [
                'status' => 'error',
                'message' => 'Invalid exp_type. Expected one of: '.implode(', ', $this->expTypes).'.',
            ];
        }

        $project = Project::getProject($projectId);
        // Preserve diagnosis metadata for filtering and visible evidence. The
        // general project controller intentionally omits this column.
        $table = ['cols' => [], 'data' => []];
        if ($project !== null) {
            foreach ($project->getProjectSamples(true, $expType) as $sample) {
                $row = array_diff_key((array) $sample, array_flip([
                    'sample_alias', 'run_id', 'biomaterial_id', 'relation', 'platform', 'project_id', 'name',
                ]));
                if ($table['cols'] === []) {
                    $table['cols'] = array_map(static fn ($key) => ['title' => ucfirst(str_replace('_', ' ', $key))], array_keys($row));
                }
                $table['data'][] = array_values($row);
            }
        }
        $tableJson = json_encode($table, JSON_UNESCAPED_SLASHES);

        if ($project === null || trim($tableJson) === '') {
            return [
                'status' => 'error',
                'message' => $project === null
                    ? "Project {$projectId} not found."
                    : 'Sample data could not be serialized.',
            ];
        }

        return [
            'status' => 'success',
            'project_id' => $projectId,
            'project_name' => trim((string) $project->name),
            'exp_type' => $expType,
            'data_type' => 'table',
            'display_type' => 'table',
            'table_json' => $tableJson,
            'title' => $expType.' Samples',
            'summary' => $expType.' samples for project '.$project->name.'.',
        ];
    }

    /** @return array<string, mixed> */
    protected function cancerTypeSamples(string $cancerTypeId): array
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            return [
                'status' => 'error',
                'message' => 'No authorized user is associated with the MCP request.',
            ];
        }

        if (! in_array($cancerTypeId, $this->availableCancerTypes($userId), true)) {
            return [
                'status' => 'error',
                'message' => "Cancer type {$cancerTypeId} is not an exact Cancer Type value available from getCancerTypes.",
            ];
        }

        $samples = array_map(static function ($sample): array {
            return [
                'patient_id' => trim((string) ($sample->patient_id ?? '')),
                'sample_id' => trim((string) ($sample->sample_id ?? '')),
                'sample_name' => trim((string) ($sample->sample_name ?? '')),
                'sample_alias' => trim((string) ($sample->sample_alias ?? '')),
                'tissue_category' => trim((string) ($sample->tissue_cat ?? '')),
                'tissue_type' => trim((string) ($sample->tissue_type ?? '')),
                'assay_type' => trim((string) ($sample->exp_type ?? '')),
                'library_type' => trim((string) ($sample->library_type ?? '')),
                'material_type' => trim((string) ($sample->material_type ?? '')),
                'platform' => trim((string) ($sample->platform ?? '')),
            ];
        }, $this->samplesForCancerType($userId, $cancerTypeId));

        usort($samples, static fn (array $left, array $right): int => strcasecmp($left['patient_id'], $right['patient_id'])
                ?: strcasecmp($left['sample_id'], $right['sample_id'])
        );

        $patientIds = [];
        foreach ($samples as $sample) {
            if ($sample['patient_id'] !== '') {
                $patientIds[$sample['patient_id']] = true;
            }
        }

        $table = [
            'cols' => array_map(static fn (string $title): array => ['title' => $title], [
                'Patient ID', 'Sample ID', 'Sample Name', 'Sample Alias',
                'Tissue Category', 'Tissue Type', 'Assay Type', 'Library Type',
                'Material Type', 'Platform',
            ]),
            'data' => array_map(static fn (array $sample): array => array_values($sample), $samples),
        ];

        $sampleCount = count($samples);
        $patientCount = count($patientIds);

        return [
            'status' => 'success',
            'cancer_type_id' => $cancerTypeId,
            'samples' => $samples,
            'sample_count' => $sampleCount,
            'patient_count' => $patientCount,
            'count_unit' => 'distinct_samples_and_patients',
            'data_type' => 'table',
            'display_type' => 'table',
            'table_json' => (string) json_encode($table, JSON_UNESCAPED_SLASHES),
            'title' => "Samples for {$cancerTypeId}",
            'summary' => "{$sampleCount} sample(s) from {$patientCount} patient(s) found for {$cancerTypeId}.",
        ];
    }

    protected function currentUserId(): ?int
    {
        $user = User::getCurrentUser();

        return $user === null ? null : (int) $user->id;
    }

    /** @return array<int, string> */
    protected function availableCancerTypes(int $userId): array
    {
        $rows = DB::select(
            'select distinct s.diagnosis
             from user_projects u, project_samples s
             where u.user_id=? and u.project_id=s.project_id
             order by s.diagnosis',
            [$userId]
        );

        return array_values(array_filter(array_map(
            static fn ($row): string => trim((string) ($row->diagnosis ?? '')),
            $rows
        )));
    }

    /** @return array<int, object> */
    protected function samplesForCancerType(int $userId, string $cancerTypeId): array
    {
        return DB::select(
            'select distinct s.patient_id, s.sample_id, s.sample_name,
                    s.sample_alias, s.tissue_cat, s.tissue_type, s.exp_type,
                    s.library_type, s.material_type, s.platform
             from samples s
             where exists (
                 select 1
                 from user_projects u, project_samples p
                 where u.user_id=? and u.project_id=p.project_id
                   and p.sample_id=s.sample_id and p.diagnosis=?
             )
             order by s.patient_id, s.sample_id',
            [$userId, $cancerTypeId]
        );
    }

    private function normalizeExpType(string $value): ?string
    {
        $needle = strtolower(trim($value));
        foreach ($this->expTypes as $canonical) {
            if (strtolower($canonical) === $needle) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Apply assay/library filters to the authoritative server-side table so
     * the model does not have to filter a large, compacted sample payload.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function filterSampleResult(array $content, ?string $expType, string $libraryType, string $diagnosis = '', string $diagnosisMatch = 'exact'): array
    {
        if (($content['status'] ?? null) !== 'success'
            || ($expType === null && $libraryType === '' && $diagnosis === '')) {
            return $content;
        }

        $table = json_decode((string) ($content['table_json'] ?? ''), true);
        if (! is_array($table) || ! is_array($table['cols'] ?? null) || ! is_array($table['data'] ?? null)) {
            return [
                ...$content,
                'status' => 'error',
                'message' => 'Sample data could not be filtered because its table structure is unavailable.',
            ];
        }

        $columnIndexes = [];
        foreach ($table['cols'] as $index => $column) {
            $title = is_array($column) ? (string) ($column['title'] ?? '') : (string) $column;
            $columnIndexes[$this->normalizeFilterValue($title)] = (int) $index;
        }

        $expIndex = $columnIndexes['assaytype']
            ?? $columnIndexes['exptype']
            ?? $columnIndexes['experimenttype']
            ?? null;
        $libraryIndex = $columnIndexes['librarytype'] ?? null;
        $diagnosisIndex = $columnIndexes['diagnosis'] ?? null;
        if ($diagnosis !== '' && $diagnosisIndex === null) {
            // A diagnosis-scoped table already has one authoritative diagnosis.
            if (!isset($content['cancer_type_id'])) {
                return ['status' => 'error', 'message' => 'The sample table has no diagnosis column; cannot apply the requested filter.'];
            }
        }
        if ($expType !== null && $expIndex === null) {
            return [...$content, 'status' => 'error', 'message' => 'The sample table has no experiment-type column.'];
        }
        if ($libraryType !== '' && $libraryIndex === null) {
            return [...$content, 'status' => 'error', 'message' => 'The sample table has no library-type column.'];
        }

        $rows = array_values(array_filter($table['data'], function (mixed $row) use (
            $expType,
            $expIndex,
            $libraryType,
            $libraryIndex,
            $diagnosis, $diagnosisMatch, $diagnosisIndex, $content,
        ): bool {
            if (! is_array($row)) {
                return false;
            }
            if ($expType !== null
                && $this->normalizeFilterValue($row[$expIndex] ?? '') !== $this->normalizeFilterValue($expType)) {
                return false;
            }

            if ($diagnosis !== '' && !$this->matchesDiagnosis($diagnosisIndex === null ? ($content['cancer_type_id'] ?? '') : ($row[$diagnosisIndex] ?? ''), $diagnosis, $diagnosisMatch)) {
                return false;
            }

            return $libraryType === ''
                || $this->normalizeFilterValue($row[$libraryIndex] ?? '') === $this->normalizeFilterValue($libraryType);
        }));
        $table['data'] = $rows;
        $content['table_json'] = (string) json_encode($table, JSON_UNESCAPED_SLASHES);
        $content['sample_count'] = count($rows);

        $patientIndex = $columnIndexes['patientid'] ?? null;
        if ($patientIndex !== null) {
            $patients = [];
            foreach ($rows as $row) {
                $patientId = trim((string) ($row[$patientIndex] ?? ''));
                if ($patientId !== '') {
                    $patients[$patientId] = true;
                }
            }
            $content['patient_count'] = count($patients);
        }

        if (is_array($content['samples'] ?? null)) {
            $content['samples'] = array_values(array_filter(
                $content['samples'],
                function (mixed $sample) use ($expType, $libraryType, $diagnosis, $diagnosisMatch, $content): bool {
                    if (! is_array($sample)) {
                        return false;
                    }
                    if ($expType !== null
                        && $this->normalizeFilterValue($sample['assay_type'] ?? '') !== $this->normalizeFilterValue($expType)) {
                        return false;
                    }

                    if ($diagnosis !== '' && !$this->matchesDiagnosis($sample['diagnosis'] ?? $content['cancer_type_id'] ?? '', $diagnosis, $diagnosisMatch)) {
                        return false;
                    }
                    return $libraryType === ''
                        || $this->normalizeFilterValue($sample['library_type'] ?? '') === $this->normalizeFilterValue($libraryType);
                },
            ));
        }

        $content['filters'] = array_filter([
            'exp_type' => $expType ?? ($content['exp_type'] ?? null),
            'library_type' => $libraryType !== '' ? $libraryType : null,
            'diagnosis' => $diagnosis !== '' ? $diagnosis : null,
            'diagnosis_match' => $diagnosis !== '' ? $diagnosisMatch : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        if ($expType !== null) {
            $content['exp_type'] = $expType;
        }
        if ($libraryType !== '') {
            $content['library_type'] = $libraryType;
        }
        if ($diagnosis !== '') {
            $content['diagnosis'] = $diagnosis;
            $content['diagnosis_match'] = $diagnosisMatch;
        }

        $filterSummary = implode(', ', array_map(
            static fn (string $key, string $value): string => str_replace('_', ' ', $key).'='.$value,
            array_keys($content['filters']),
            array_values($content['filters']),
        ));
        $cohortLabel = trim((string) ($content['project_name'] ?? $content['cancer_type_id'] ?? 'the cohort'));
        $patientSummary = isset($content['patient_count'])
            ? ' from '.(int) $content['patient_count'].' patient(s)'
            : '';
        $content['title'] = 'Filtered Samples';
        $content['summary'] = count($rows).' sample(s)'.$patientSummary
            .' matched '.$filterSummary.' in '.$cohortLabel.'.';

        return $content;
    }

    private function matchesDiagnosis(mixed $actual, string $requested, string $match): bool
    {
        $normalize = static function ($value): string {
            $value = strtolower(trim((string) $value));
            return $value === 'rms' ? 'rhabdomyosarcoma' : $value;
        };
        $actual = $normalize($actual);
        $requested = $normalize($requested);
        return $match === 'contains' ? str_contains($actual, $requested) : $actual === $requested;
    }

    private function normalizeFilterValue(mixed $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim((string) $value)));
    }

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->cohortProperties(), [
                'exp_type' => [
                    'type' => ['string', 'null'],
                    'enum' => array_merge($this->expTypes, [null]),
                    'description' => 'Required when cohort_type=project; optional for cancer_type.',
                ],
                'diagnosis' => [
                    'type' => ['string', 'null'],
                    'description' => 'Filter actual diagnosis metadata within the cohort. RMS is an alias for Rhabdomyosarcoma; never match sample names.',
                ],
                'diagnosis_match' => [
                    'type' => 'string', 'enum' => ['exact', 'contains'],
                    'description' => 'Default exact, case-insensitive. Use contains for a disease family including subtypes, e.g. all RMS. State this matching choice in the answer.',
                ],
                'library_type' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional exact, case-insensitive library-type filter, for example PolyA or RiboZero.',
                ],
            ]),
            'required' => ['cohort_type', 'cohort_id'],
            'additionalProperties' => false,
        ];
    }
}
