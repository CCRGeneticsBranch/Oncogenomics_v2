<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectController;
use App\Mcp\Tools\Concerns\ResolvesCohortInput;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetCohortSamplesTool extends Tool
{
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
        Return samples for either an authorized project or an authorized cancer
        type (diagnosis). For a project, exp_type is required and filters by
        experiment type. For a cancer type, all experiment types are returned.

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
            'exp_type' => 'nullable|string',
        ]);

        try {
            $cohortType = (string) $validated['cohort_type'];
            $expType = trim((string) ($validated['exp_type'] ?? ''));
            if ($cohortType === 'project' && $expType === '') {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => 'exp_type is required for a project sample cohort.',
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
                $content = $this->projectSamples((int) $cohortId, $expType);
            } else {
                $content = $this->cancerTypeSamples((string) $cohortId);
            }

            return $this->cohortResponse($content, $cohortType, $cohortId, $this->name);
        } catch (\Throwable $e) {
            return Response::structured([
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
        $tableJson = $this->normalizeToJson(
            app(ProjectController::class)->getProjectSamples($projectId, 'json', $expType)
        );

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

        if (!in_array($cancerTypeId, $this->availableCancerTypes($userId), true)) {
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

        usort($samples, static fn (array $left, array $right): int =>
            strcasecmp($left['patient_id'], $right['patient_id'])
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

    private function normalizeToJson($result): string
    {
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return (string) json_encode($result->getData(true), JSON_UNESCAPED_SLASHES);
        }
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return (string) $result->getContent();
        }

        return is_string($result) ? $result : (string) json_encode($result, JSON_UNESCAPED_SLASHES);
    }

    public function schema($schema = null): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->cohortProperties(), [
                'exp_type' => [
                    'type' => ['string', 'null'],
                    'enum' => array_merge($this->expTypes, [null]),
                    'description' => 'Required when cohort_type=project; omitted for cancer_type.',
                ],
            ]),
            'required' => ['cohort_type', 'cohort_id'],
            'additionalProperties' => false,
        ];
    }
}
