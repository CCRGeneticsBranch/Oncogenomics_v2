<?php
namespace Tests\Unit;

use App\Services\CohortMutationDetailsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CohortMutationDetailsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['site.avia_table' => 'test_annotations']);
        Schema::create('user_projects', function (Blueprint $t) { $t->integer('user_id'); $t->integer('project_id'); });
        Schema::create('project_samples', function (Blueprint $t) {
            $t->integer('project_id');
            foreach (['patient_id', 'sample_id', 'diagnosis'] as $f) $t->string($f);
        });
        Schema::create('test_annotations', function (Blueprint $t) {
            foreach (['patient_id', 'case_id', 'sample_id', 'gene', 'chromosome', 'ref', 'alt', 'genome_version', 'type', 'so', 'achange'] as $f) $t->string($f)->nullable();
            foreach (['start_pos', 'end_pos', 'total_cov', 'var_cov'] as $f) $t->integer($f);
            $t->float('vaf');
        });
        Schema::create('var_tier_avia', function (Blueprint $t) {
            foreach (['patient_id', 'sample_id', 'type', 'chromosome', 'ref', 'alt', 'gene', 'germline_level', 'somatic_level'] as $f) $t->string($f)->nullable();
            $t->integer('start_pos'); $t->integer('end_pos');
        });
        DB::table('user_projects')->insert(['user_id' => 1, 'project_id' => 42]);
        foreach ([['P1', 'S1', 42, 'RMS'], ['P1', 'S1', 42, 'RMS'], ['P2', 'S2', 42, 'RMS'], ['P3', 'S3', 99, 'RMS'], ['P4', 'S4', 42, 'Other']] as $r) {
            DB::table('project_samples')->insert(array_combine(['patient_id', 'sample_id', 'project_id', 'diagnosis'], $r));
        }
        foreach (range(1, 4) as $i) {
            $row = ['patient_id' => 'P'.$i, 'sample_id' => 'S'.$i, 'case_id' => 'C'.$i, 'gene' => 'ALK',
                'chromosome' => 'chr2', 'start_pos' => 100, 'end_pos' => 100, 'ref' => 'A', 'alt' => 'G',
                'genome_version' => 'hg19', 'type' => 'somatic', 'so' => 'missense_variant', 'achange' => null,
                'vaf' => $i / 10, 'total_cov' => 100, 'var_cov' => $i * 10];
            DB::table('test_annotations')->insert($row);
            if ($i === 1) {
                DB::table('test_annotations')->insert($row);
                DB::table('test_annotations')->insert(array_merge($row, ['genome_version' => 'hg38']));
                $tier = array_intersect_key($row, array_flip(['patient_id', 'sample_id', 'type', 'chromosome', 'start_pos', 'end_pos', 'ref', 'alt', 'gene']));
                DB::table('var_tier_avia')->insert($tier + ['somatic_level' => 'Tier 1', 'germline_level' => 'Tier 3']);
            }
        }
    }

    public function test_database_pages_preserve_full_counts_and_authorization(): void
    {
        $service = app(CohortMutationDetailsService::class);
        $filters = ['gene' => 'alk', 'type' => 'somatic'];
        $first = $service->fetch(1, 'cancer_type', 'RMS', $filters, 1, 0);
        $this->assertSame(2, $first['total_rows']);
        $this->assertSame(2, $first['patient_count']);
        $this->assertSame('P1', $first['rows'][0]['patient_id']);
        $this->assertNull($first['rows'][0]['protein_change']);
        $next = $service->fetch(1, 'cancer_type', 'RMS', $filters, 1, 1);
        $this->assertSame('P2', $next['rows'][0]['patient_id']);
        $this->assertSame(0, $service->fetch(1, 'project', 99, $filters, 10, 0)['total_rows']);
        $this->assertSame(0, $service->fetch(2, 'cancer_type', 'RMS', $filters, 10, 0)['total_rows']);
        $this->assertSame(0, $service->fetch(1, 'cancer_type', "RMS' OR 1=1 --", $filters, 10, 0)['total_rows']);
    }

    public function test_filters_and_summary_apply_to_full_result(): void
    {
        $service = app(CohortMutationDetailsService::class);
        $filters = ['gene' => 'ALK', 'type' => 'somatic'];
        $r = $service->fetch(1, 'cancer_type', 'RMS', $filters + ['tier' => '1'], 1, 0, true);
        $this->assertSame(1, $r['total_rows']);
        $this->assertSame([], $r['rows']);
        $this->assertSame(0, $service->fetch(1, 'cancer_type', 'RMS', $filters + ['tier' => '1', 'tier_context' => 'germline'], 1, 0)['total_rows']);
        $this->assertSame(1, $service->fetch(1, 'cancer_type', 'RMS', $filters + ['min_vaf' => .2], 10, 0)['total_rows']);
        $this->assertSame(1, $service->fetch(1, 'cancer_type', 'RMS', $filters + ['genome_version' => 'hg38'], 10, 0)['total_rows']);
    }
}
