<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;

class InstanceFixtureContractTest extends InstanceTestCase
{
    public function test_configured_cancer_types_exist(): void
    {
        foreach ($this->fixtures['cancer_types'] as $cancerType) {
            $rows = DB::select('select id,name from cancer_types where id = ?', [$cancerType['id']]);
            $this->assertCount(1, $rows, "Missing cancer type {$cancerType['id']}");
            $this->assertSame($cancerType['name'], $rows[0]->name);
        }
    }

    public function test_configured_cancer_types_have_expected_patient_populations(): void
    {
        foreach ($this->fixtures['cancer_types'] as $cancerType) {
            $count = DB::selectOne(
                'select count(distinct patient_id) as cnt from project_patients where diagnosis = ?',
                [$cancerType['id']],
            )->cnt;

            $this->assertGreaterThanOrEqual(
                $cancerType['minimum_patient_count'],
                (int) $count,
                "Cancer type {$cancerType['id']} has fewer patients than expected",
            );
        }
    }

    public function test_configured_cancer_types_have_expected_sample_modalities(): void
    {
        foreach ($this->fixtures['cancer_types'] as $cancerType) {
            $rows = DB::select(
                'select distinct exp_type from project_samples where diagnosis = ?',
                [$cancerType['id']],
            );
            $actual = array_map(static fn ($row): string => $row->exp_type, $rows);

            foreach ($cancerType['sample_types'] as $sampleType) {
                $this->assertContains($sampleType, $actual, "Missing {$sampleType} samples for {$cancerType['id']}");
            }
        }
    }

    public function test_configured_cancer_types_have_expected_molecular_results(): void
    {
        foreach ($this->fixtures['cancer_types'] as $cancerType) {
            $variantRows = DB::select(
                'select distinct lower(v.type) as type from var_cases v where exists (select 1 from project_samples p where p.diagnosis = ? and p.patient_id = v.patient_id)',
                [$cancerType['id']],
            );
            $variantTypes = array_map(static fn ($row): string => $row->type, $variantRows);
            foreach ($cancerType['variant_types'] as $variantType) {
                $this->assertContains($variantType, $variantTypes, "Missing {$variantType} results for {$cancerType['id']}");
            }

            $fusionCount = DB::selectOne(
                'select count(*) as cnt from project_samples p, var_fusion v where p.diagnosis = ? and p.sample_id = v.sample_id',
                [$cancerType['id']],
            )->cnt;
            $cnvCount = DB::selectOne(
                'select count(*) as cnt from project_samples p, var_cnv v where p.diagnosis = ? and p.sample_id = v.sample_id',
                [$cancerType['id']],
            )->cnt;
            $expressionCount = DB::selectOne(
                "select count(*) as cnt from project_values v where exists (select 1 from project_samples p where p.diagnosis = ? and p.project_id = v.project_id and p.exp_type = 'RNAseq')",
                [$cancerType['id']],
            )->cnt;

            $this->assertGreaterThan(0, (int) $fusionCount, "Missing fusion rows for {$cancerType['id']}");
            $this->assertGreaterThan(0, (int) $cnvCount, "Missing CNV rows for {$cancerType['id']}");
            $this->assertGreaterThan(0, (int) $expressionCount, "Missing expression rows for {$cancerType['id']}");
        }
    }

    public function test_configured_chipseq_cancer_types_have_mounted_artifacts(): void
    {
        foreach ($this->fixtures['cancer_types'] as $cancerType) {
            if (! in_array('chipseq', $cancerType['capabilities'], true)) {
                continue;
            }

            $rows = DB::select(
                "select distinct sample_id from project_samples where diagnosis = ? and exp_type = 'ChIPseq'",
                [$cancerType['id']],
            );
            $mounted = array_filter($rows, static fn ($row): bool => is_dir(
                storage_path('ProcessedResults/chipseq/hg19/'.$row->sample_id),
            ));

            $this->assertNotEmpty($mounted, "No mounted ChIP-seq artifacts for {$cancerType['id']}");
        }
    }

    public function test_configured_projects_exist_with_expected_names(): void
    {
        foreach ($this->fixtures['projects'] as $project) {
            $rows = DB::select('select id,name from projects where id = ?', [$project['id']]);
            $this->assertCount(1, $rows, "Missing project {$project['id']}");
            $this->assertSame($project['name'], $rows[0]->name);
        }
    }

    public function test_configured_patients_and_cases_belong_to_each_project(): void
    {
        foreach ($this->fixtures['projects'] as $project) {
            foreach ($project['patients'] as $patient) {
                foreach ($patient['cases'] as $case) {
                    $rows = DB::select(
                        'select patient_id,case_id,path from project_processed_cases where project_id = ? and patient_id = ? and case_id = ?',
                        [$project['id'], $patient['patient_id'], $case['case_id']],
                    );
                    $this->assertCount(1, $rows, "Missing {$project['id']}/{$patient['patient_id']}/{$case['case_id']}");
                    $this->assertSame($case['path'], $rows[0]->path);
                }
            }
        }
    }

    public function test_primary_case_has_multimodal_samples_and_results(): void
    {
        $project = $this->fixtures['projects']['primary'];
        $patient = $project['patients'][0];
        $case = $patient['cases'][0];

        $samples = DB::select(
            'select distinct exp_type from sample_cases where patient_id = ? and case_id = ?',
            [$patient['patient_id'], $case['case_id']],
        );
        $types = array_map(static fn ($row): string => strtolower($row->exp_type), $samples);
        $this->assertContains('exome', $types);
        $this->assertContains('rnaseq', $types);

        $variantRows = DB::select(
            'select distinct type from var_cases where patient_id = ? and case_id = ?',
            [$patient['patient_id'], $case['case_id']],
        );
        $variantTypes = array_map(static fn ($row): string => strtolower($row->type), $variantRows);
        foreach (['germline', 'somatic', 'hotspot', 'rnaseq', 'fusion'] as $type) {
            $this->assertContains($type, $variantTypes);
        }

        $this->assertGreaterThan(0, DB::selectOne('select count(*) as cnt from var_fusion where patient_id = ? and case_id = ?', [$patient['patient_id'], $case['case_id']])->cnt);
        $this->assertGreaterThan(0, DB::selectOne('select count(*) as cnt from var_cnv where patient_id = ? and case_id = ?', [$patient['patient_id'], $case['case_id']])->cnt);
    }

    public function test_configured_result_directories_exist(): void
    {
        foreach ($this->fixtures['projects'] as $project) {
            foreach ($project['patients'] as $patient) {
                foreach ($patient['cases'] as $case) {
                    $directory = storage_path('ProcessedResults/'.$case['path'].'/'.$patient['patient_id'].'/'.$case['case_id']);
                    $this->assertDirectoryExists($directory);
                    $this->assertNotEmpty(glob($directory.'/*'), "No result artifacts in {$directory}");
                }
            }
        }
    }

    public function test_fixture_profile_does_not_depend_on_temporary_tso_validation_project(): void
    {
        $ids = array_column($this->fixtures['projects'], 'id');
        $this->assertNotContains(27303, $ids);
    }

    public function test_browser_fixture_samples_belong_to_the_primary_case(): void
    {
        $project = $this->fixtures['projects']['primary'];
        $patient = $project['patients'][0];
        $case = $patient['cases'][0];
        $browser = $this->fixtures['browser_values'];

        foreach (['exome_sample_id', 'rnaseq_sample_id'] as $key) {
            $row = DB::selectOne(
                'select count(*) as cnt from sample_cases where patient_id = ? and case_id = ? and sample_id = ?',
                [$patient['patient_id'], $case['case_id'], $browser[$key]],
            );
            $this->assertGreaterThan(0, (int) $row->cnt, "Browser fixture {$key} does not belong to the primary case");
        }
    }

    public function test_view_route_exclusions_reference_current_routes_and_explain_why(): void
    {
        $snapshot = json_decode(file_get_contents(base_path('tests/Fixtures/routes.json')), true, 512, JSON_THROW_ON_ERROR);
        $viewUris = array_column(array_filter(
            $snapshot,
            static fn (array $route): bool => str_contains($route['method'], 'GET') && str_starts_with($route['uri'], 'view'),
        ), 'uri');

        foreach ($this->fixtures['view_route_exclusions'] as $uri => $reason) {
            $this->assertContains($uri, $viewUris, "Excluded view route {$uri} no longer exists");
            $this->assertGreaterThanOrEqual(10, strlen(trim($reason)), "Excluded view route {$uri} needs a meaningful reason");
        }
    }
}
