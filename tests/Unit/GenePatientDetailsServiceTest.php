<?php

namespace Tests\Unit;

use App\Services\GenePatientDetailsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenePatientDetailsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('user_projects', function (Blueprint $t) {
            $t->integer('user_id'); $t->integer('project_id'); $t->string('project_name');
        });
        Schema::create('project_patients', function (Blueprint $t) {
            $t->integer('project_id'); $t->string('patient_id'); $t->string('diagnosis');
        });
        Schema::create('var_gene_tier', function (Blueprint $t) {
            foreach (['patient_id', 'gene', 'type', 'somatic_level', 'germline_level'] as $field) $t->string($field)->nullable();
        });
        foreach (['var_cnv_genes', 'var_cnv_gene_level'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->string('patient_id'); $t->string('gene'); $t->float('cnt'); });
        }
        Schema::create('var_fusion', function (Blueprint $t) {
            foreach (['patient_id', 'left_gene', 'right_gene', 'var_level', 'type'] as $field) $t->string($field);
        });
        foreach ([[1, 10, 'Alpha'], [1, 11, "O'Brien"], [2, 99, 'Private']] as $row) {
            DB::table('user_projects')->insert(array_combine(['user_id', 'project_id', 'project_name'], $row));
        }
        foreach ([[10, 'P1', 'RMS'], [11, 'P1', 'RMS'], [99, 'P1', 'RMS'], [99, 'P2', 'RMS'], [10, 'P3', 'Other']] as $row) {
            DB::table('project_patients')->insert(array_combine(['project_id', 'patient_id', 'diagnosis'], $row));
        }
        foreach (['P1', 'P2', 'P3'] as $patient) {
            DB::table('var_gene_tier')->insert(['patient_id' => $patient, 'gene' => 'ALK', 'type' => 'somatic', 'somatic_level' => 'Tier 1.1']);
            DB::table('var_fusion')->insert(['patient_id' => $patient, 'left_gene' => 'EML4', 'right_gene' => 'ALK', 'var_level' => '1.1', 'type' => 'in-frame']);
            foreach (['var_cnv_genes', 'var_cnv_gene_level'] as $table) {
                DB::table($table)->insert(['patient_id' => $patient, 'gene' => 'ALK', 'cnt' => $patient === 'P3' ? 1 : 3]);
            }
        }
    }

    private function filters(array $extra = []): array
    {
        return array_merge(['gene_id' => 'ALK', 'type' => 'somatic', 'cat_type' => 'diagnosis', 'category' => null,
            'tiers' => 'Tier 1,Tier 2', 'fusion_type' => 'All', 'min_amplified' => 3, 'max_deleted' => 1], $extra);
    }

    public function test_shared_mutation_summary_uses_authorized_frequency_denominators(): void
    {
        $this->app->instance('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface', new class {
            public function getLoggedUser() { return (object) ['id' => 1]; }
        });
        $result = \App\Models\VarAnnotation::getVarGeneSummary('ALK', 'frequency', 'diagnosis', 0, 'Tier 1');
        $this->assertEquals([100, 100], array_column($result['somatic']['series'][0]['data'], 'y'));
        $this->assertSame(['Other', 'RMS'], $result['somatic']['category']);
    }

    public function test_percentage_denominators_count_only_distinct_authorized_patients(): void
    {
        $service = app(GenePatientDetailsService::class);
        $this->assertSame(['Other' => 1, 'RMS' => 1], $service->summaryDenominators(1, 'diagnosis'));
        $this->assertSame(['p10' => 2, 'p11' => 1], $service->summaryDenominators(1, 'project'));
        $this->assertSame([], $service->summaryDenominators(777, 'diagnosis'));
    }

    public function test_unfiltered_gene_details_are_authorized_deduplicated_and_hide_private_project_names(): void
    {
        $service = app(GenePatientDetailsService::class);
        foreach (['mutation', 'fusion'] as $kind) {
            $rows = $service->patients(1, $kind, $this->filters());
            $this->assertSame(['P1', 'P3'], array_column($rows, 0));
            $this->assertSame("Alpha, O'Brien", $rows[0][2]);
            $this->assertStringNotContainsString('Private', json_encode($rows));
            $this->assertSame([], $service->patients(777, $kind, $this->filters()));
        }
    }

    public function test_group_filters_are_bound_and_cannot_widen_access(): void
    {
        $service = app(GenePatientDetailsService::class);
        $rows = $service->patients(1, 'mutation', $this->filters(['cat_type' => 'project', 'category' => "O'Brien"]));
        $this->assertSame([['P1', 'RMS', "O'Brien"]], $rows);
        $this->assertSame([], $service->patients(1, 'mutation', $this->filters(['cat_type' => 'project', 'category' => 'Private'])));
        $this->assertSame([], $service->patients(1, 'fusion', $this->filters(['category' => "RMS' OR 1=1 --"])));
        $this->assertSame(['P1'], array_column($service->patients(1, 'mutation', $this->filters(['category' => 'RMS'])), 0));
    }

    public function test_mutation_tiers_context_and_fusion_orientation_are_preserved(): void
    {
        $service = app(GenePatientDetailsService::class);
        $this->assertSame([], $service->patients(1, 'mutation', $this->filters(['type' => 'germline'])));
        $this->assertSame([], $service->patients(1, 'mutation', $this->filters(['tiers' => 'Tier 2'])));
        $this->assertSame([], $service->patients(1, 'fusion', $this->filters(['tiers' => 'Tier2'])));
        $this->assertCount(2, $service->patients(1, 'fusion', $this->filters(['tiers' => 'Tier1', 'left_gene' => 'EML4', 'right_gene' => 'ALK'])));
        $this->assertSame([], $service->patients(1, 'fusion', $this->filters(['left_gene' => 'ALK', 'right_gene' => 'EML4'])));
        $this->assertSame([], $service->patients(1, 'fusion', $this->filters(['fusion_type' => 'out-of-frame'])));
    }

    public function test_cnv_boundaries_and_project_table_match_the_gene_summary(): void
    {
        $service = app(GenePatientDetailsService::class);
        $rows = $service->cnvPatients(1, $this->filters());
        $this->assertSame(['P1', 'P3'], array_column($rows, 0));
        $this->assertSame(['Amplified', 'Deleted'], array_column($rows, 2));
        DB::table('var_cnv_gene_level')->where('patient_id', 'P1')->update(['cnt' => 2]);
        $rows = $service->cnvPatients(1, $this->filters(['cat_type' => 'project', 'category' => 'Alpha']));
        $this->assertSame([['P3', 'Other', 'Deleted', 'Alpha']], $rows);
    }
}
