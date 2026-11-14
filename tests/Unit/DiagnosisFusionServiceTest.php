<?php
namespace Tests\Unit;

use App\Services\DiagnosisFusionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class DiagnosisFusionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('user_projects', function (Blueprint $t) { $t->integer('user_id'); $t->integer('project_id'); });
        Schema::create('project_samples', function (Blueprint $t) {
            $t->integer('project_id'); foreach (['sample_id', 'patient_id', 'diagnosis'] as $f) $t->string($f);
        });
        Schema::create('var_fusion', function (Blueprint $t) {
            foreach (['sample_id', 'patient_id', 'left_chr', 'left_gene', 'right_chr', 'right_gene', 'var_level', 'tool'] as $f) $t->string($f);
        });
        DB::table('user_projects')->insert([['user_id' => 1, 'project_id' => 42], ['user_id' => 1, 'project_id' => 43], ['user_id' => 2, 'project_id' => 99]]);
        foreach ([[42, 'S1', 'P1', 'RMS'], [43, 'S1', 'P1', 'RMS'], [42, 'S2', 'P1', 'RMS'],
            [42, 'S3', 'P2', 'RMS'], [99, 'S4', 'P3', 'RMS'], [42, 'S5', 'P4', 'ARMS']] as $r) {
            DB::table('project_samples')->insert(array_combine(['project_id', 'sample_id', 'patient_id', 'diagnosis'], $r));
        }
        foreach ([['S1', 'P1', 'PAX3', 'FOXO1', '1.1', '[{"Arriba":"2"}]'],
            ['S2', 'P1', 'PAX3', 'FOXO1', '1.2', '[{"Arriba":"5"}]'],
            ['S3', 'P2', 'PAX3', 'FOXO1', '1.1', '[{"Other":"Arriba"}]'],
            ['S3', 'P2', 'FOXO1', 'PAX3', '2.1', '[{"Arriba":"5"}]'],
            ['S4', 'P3', 'PAX3', 'FOXO1', '1.1', '[{"Arriba":"5"}]'],
            ['S5', 'P4', 'PAX3', 'FOXO1', '1.1', '[{"Arriba":"5"}]']] as $r) {
            DB::table('var_fusion')->insert(array_combine(['sample_id', 'patient_id', 'left_gene', 'right_gene', 'var_level', 'tool'], $r) + ['left_chr' => '2', 'right_chr' => '13']);
        }
    }

    public function test_gene_and_tier_filter_before_deduplicated_patient_counts(): void
    {
        $rows = app(DiagnosisFusionService::class)->pairs(1, 'RMS', ['gene' => 'pax3', 'partner' => 'foxo1', 'tier' => '1']);
        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['patient_count']);
        $this->assertSame([], app(DiagnosisFusionService::class)->pairs(1, "RMS' OR 1=1 --", ['gene' => 'PAX3']));
        $this->assertSame([], app(DiagnosisFusionService::class)->pairs(1, 'RMS', ['gene' => 'UNKNOWN']));
    }

    public function test_caller_matches_keys_and_counts_each_patient_once_per_pair_tier(): void
    {
        $rows = app(DiagnosisFusionService::class)->pairs(1, 'RMS', ['gene' => 'PAX3', 'partner' => 'FOXO1', 'caller' => 'arriba']);
        $this->assertCount(2, $rows);
        $this->assertSame([1, 1], array_column($rows, 'patient_count'));
    }

    public function test_project_cutoff_counts_patients_across_samples_and_enforces_access(): void
    {
        $service = app(DiagnosisFusionService::class);
        $rows = $service->projectPairs(1, 42, ['tier' => '1', 'cutoff' => 2]);
        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['patient_count']);
        $this->assertSame([], $service->projectPairs(1, 43, ['cutoff' => 2]));
        $this->assertSame([], $service->projectPairs(1, 99, ['cutoff' => 1]));
        $this->assertSame([], $service->projectPairs(2, 42, ['cutoff' => 1]));
        $this->assertSame([], $service->projectPairs(1, 42, ['cutoff' => 4]));
        $filtered = $service->projectPairs(1, 42, ['diagnosis' => 'RMS', 'cutoff' => 2]);
        $this->assertCount(1, $filtered);
        $this->assertSame(2, $filtered[0]['patient_count']);
    }

    public function test_cutoff_applies_after_caller_filter_and_tier_grouping(): void
    {
        $service = app(DiagnosisFusionService::class);
        $this->assertSame([], $service->pairs(1, 'RMS', ['caller' => 'Arriba', 'cutoff' => 2]));
        $rows = $service->projectPairs(1, 42, ['caller' => 'Arriba', 'cutoff' => 2]);
        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['tier']);
        $this->assertSame(2, $rows[0]['patient_count']);
        $this->assertCount(2, $service->projectPairs(1, 42, ['cutoff' => 1]));
        $this->assertSame([], $service->pairs(1, 'RMS', ['cutoff' => 3]));
    }
}
