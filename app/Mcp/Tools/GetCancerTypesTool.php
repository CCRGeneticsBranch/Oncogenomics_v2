<?php

namespace App\Mcp\Tools;

use App\Models\CancerType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetCancerTypesTool extends LegacySchemaTool
{
    protected string $name = 'getCancerTypes';

    protected string $description = <<<'MARKDOWN'
        List every cancer type (diagnosis) visible to the authorized user.
        Each result includes its ordered OncoTree hierarchy, distinct patient
        and sample counts, sample/assay-type counts, and library-type counts.
        Use this tool to resolve an available diagnosis before calling a
        cancer-type-scoped tool.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $request->validate([]);

        try {
            $user = User::getCurrentUser();

            if ($user === null) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => 'No authorized user is associated with the MCP request.',
                ]);
            }

            [$columns, $rows] = CancerType::getAll();
            $libraryCounts = $this->libraryCounts((int) $user->id);
            $oncotreeHierarchies = $this->oncotreeHierarchies();
            $sampleTypeNames = array_map(
                static fn (array $column): string => trim((string) ($column['title'] ?? '')),
                array_slice($columns, 3)
            );

            $cancerTypes = [];
            foreach ($rows as $row) {
                $diagnosis = trim((string) ($row[0] ?? ''));
                if ($diagnosis === '') {
                    continue;
                }

                $sampleTypes = [];
                foreach ($sampleTypeNames as $index => $sampleType) {
                    if ($sampleType === '') {
                        continue;
                    }
                    $sampleTypes[$sampleType] = (int) ($row[$index + 3] ?? 0);
                }

                $sampleTypes = $this->sortCounts($sampleTypes);
                $diagnosisLibraryCounts = $this->sortCounts($libraryCounts[$diagnosis] ?? []);
                $sampleCount = array_sum($sampleTypes);
                $hierarchy = $oncotreeHierarchies[$diagnosis] ?? [];

                $cancerTypes[] = [
                    'diagnosis' => $diagnosis,
                    'oncotree_hierarchy' => $hierarchy,
                    'oncotree_annotation' => implode(' > ', array_column($hierarchy, 'name')),
                    'patient_count' => (int) ($row[2] ?? 0),
                    'sample_count' => $sampleCount,
                    'sample_types' => $sampleTypes,
                    'library_types' => $diagnosisLibraryCounts,
                ];
            }

            usort($cancerTypes, static function (array $left, array $right): int {
                return strcasecmp($left['diagnosis'], $right['diagnosis']);
            });

            $table = [
                'cols' => [
                    ['title' => 'Cancer Type'],
                    ['title' => 'OncoTree Hierarchy'],
                    ['title' => 'Patients'],
                    ['title' => 'Samples'],
                    ['title' => 'Sample Types'],
                    ['title' => 'Library Types'],
                ],
                'data' => array_map(static function (array $cancerType): array {
                    return [
                        $cancerType['diagnosis'],
                        $cancerType['oncotree_annotation'],
                        $cancerType['patient_count'],
                        $cancerType['sample_count'],
                        self::formatCounts($cancerType['sample_types']),
                        self::formatCounts($cancerType['library_types']),
                    ];
                }, $cancerTypes),
            ];

            $count = count($cancerTypes);

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'cancer_types' => $cancerTypes,
                'cancer_type_count' => $count,
                'count_unit' => 'distinct_patients_and_samples',
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => (string) json_encode($table, JSON_UNESCAPED_SLASHES),
                'title' => 'Available Cancer Types',
                'summary' => $count === 1
                    ? '1 cancer type is available.'
                    : "{$count} cancer types are available.",
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, array<string, int>>
     */
    protected function libraryCounts(int $userId): array
    {
        $rows = DB::select(
            "select diagnosis, library_type, count(distinct sample_id) as samples
             from user_projects u, project_samples s
             where u.user_id={$userId} and u.project_id=s.project_id
             group by diagnosis, library_type
             order by diagnosis, library_type"
        );

        $counts = [];
        foreach ($rows as $row) {
            $diagnosis = trim((string) ($row->diagnosis ?? ''));
            $libraryType = trim((string) ($row->library_type ?? ''));
            if ($diagnosis === '') {
                continue;
            }
            if ($libraryType === '') {
                $libraryType = 'Unknown';
            }
            $counts[$diagnosis][$libraryType] = (int) ($row->samples ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<string, array<int, array{level: int, name: string}>>
     */
    protected function oncotreeHierarchies(): array
    {
        $rows = DB::select('select * from oncotree_mapping m left join oncotree o on m.id=o.id');
        $hierarchies = [];

        foreach ($rows as $row) {
            $diagnosis = trim((string) ($row->diagnosis ?? ''));
            if ($diagnosis === '') {
                continue;
            }

            $hierarchy = [];
            for ($level = 1; $level <= 6; $level++) {
                $field = 'name_level'.$level;
                $name = trim((string) ($row->{$field} ?? ''));
                if ($name !== '') {
                    $hierarchy[] = ['level' => $level, 'name' => $name];
                }
            }
            $hierarchies[$diagnosis] = $hierarchy;
        }

        return $hierarchies;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    protected function sortCounts(array $counts): array
    {
        uksort($counts, 'strcasecmp');

        return $counts;
    }

    /** @param array<string, int> $counts */
    protected static function formatCounts(array $counts): string
    {
        $parts = [];
        foreach ($counts as $name => $count) {
            $parts[] = "{$name}: {$count}";
        }

        return implode(', ', $parts);
    }

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ];
    }
}
