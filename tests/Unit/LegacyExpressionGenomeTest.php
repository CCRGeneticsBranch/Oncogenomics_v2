<?php
namespace Tests\Unit;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyExpressionGenomeTest extends TestCase
{
    private function project(string $column = 'genome_version'): Project
    {
        DB::statement('create table project_samples (project_id integer, patient_id text, sample_id text)');
        DB::statement('create table samples (patient_id text, sample_id text, sample_name text, sample_alias text, exp_type text, library_type text)');
        DB::statement("create table project_values (project_id integer, symbol text, target text, target_level text, value_type text, value_list text, {$column} text)");
        DB::table('project_samples')->insert(['project_id'=>24421, 'patient_id'=>'P1', 'sample_id'=>'S1']);
        DB::table('samples')->insert(['patient_id'=>'P1','sample_id'=>'S1','sample_name'=>'sample','sample_alias'=>'alias','exp_type'=>'RNAseq','library_type'=>'polyA']);
        foreach (['_list'=>'outside,S1','ENTPD1'=>'99,12.5'] as $symbol=>$values) {
            DB::table('project_values')->insert(['project_id'=>24421,'symbol'=>$symbol,'target'=>$symbol,'target_level'=>'gene','value_type'=>'tpm','value_list'=>$values,$column=>'ensembl']);
        }
        $project = new class extends Project {
            public string $builds = 'hg19';
            public function getGenomeVersion() { return $this->builds; }
        };
        $project->id = 24421;
        return $project;
    }

    public function test_legacy_genome_column_returns_requested_build_keys(): void
    {
        $this->assertMapped($this->project());
    }

    public function test_legacy_target_column_returns_requested_build_keys(): void
    {
        $this->assertMapped($this->project('target_type'));
    }

    private function assertMapped(Project $project): void
    {
        $data = $project->getGeneExpression(['ENTPD1'], 'hg19', 'all', 'gene', false, 'all', 'tpm');
        $this->assertSame(['hg19'=>[12.5]], $data['exp_data']['ENTPD1']);
        $this->assertSame(['hg19'], $data['genome_version']);
        $this->assertSame(['hg19'], array_keys($data['target_list']));
        $this->assertSame(['S1'], $data['sample_ids']);
    }

    public function test_native_genome_rows_take_precedence_over_legacy_rows(): void
    {
        $project = $this->project();
        foreach (['_list'=>'S1', 'ENTPD1'=>'7.25'] as $symbol=>$values) {
            DB::table('project_values')->insert(['project_id'=>24421,'symbol'=>$symbol,'target'=>$symbol,'target_level'=>'gene','value_type'=>'tpm','value_list'=>$values,'genome_version'=>'hg19']);
        }
        $data = $project->getGeneExpression(['ENTPD1'], 'hg19', 'all', 'gene', false);
        $this->assertSame(['hg19'=>[7.25]], $data['exp_data']['ENTPD1']);
    }

    public function test_different_or_ambiguous_build_does_not_relabel_legacy_data(): void
    {
        $project = $this->project();
        $this->assertSame([], $project->getGeneExpression(['ENTPD1'], 'hg38', 'all', 'gene', false));
        $project->builds = 'hg19,hg38';
        $this->assertSame([], $project->getGeneExpression(['ENTPD1'], 'hg19', 'all', 'gene', false));
    }
}
