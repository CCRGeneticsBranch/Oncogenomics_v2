<?php
namespace Tests\Unit;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SurvivalExpressionGenomeTest extends TestCase
{
    public function test_survival_uses_matching_legacy_expression_and_preserves_alignment(): void
    {
        DB::statement('create table project_values (project_id integer, symbol text, value_type text, genome_version text, target_level text, value_list text)');
        DB::statement('create table patient_details (patient_id text, attr_value text, class text)');
        DB::statement('create table project_samples (project_id integer, patient_id text, sample_id text, diagnosis text)');
        $samples=[];
        foreach (range(1,10) as $n) {
            $samples[]='S'.$n;
            DB::table('project_samples')->insert(['project_id'=>42,'patient_id'=>'P'.$n,'sample_id'=>'S'.$n]);
            DB::table('patient_details')->insert([
                ['patient_id'=>'P'.$n,'attr_value'=>'20','class'=>'overall_survival'],
                ['patient_id'=>'P'.$n,'attr_value'=>'dead','class'=>'survival_status'],
            ]);
        }
        foreach (['_list'=>implode(',',$samples),'MYCN'=>implode(',',range(1,10))] as $symbol=>$values) {
            DB::table('project_values')->insert(['project_id'=>42,'symbol'=>$symbol,'value_type'=>'tpm','genome_version'=>'ensembl','target_level'=>'gene','value_list'=>$values]);
        }
        $project=new class extends Project {
            public function getGenomeVersion() { return 'hg19'; }
        };
        $project->id=42;
        $old=storage_path();
        $dir=sys_get_temp_dir().'/survival-test-'.uniqid();
        mkdir($dir.'/project_data',0700,true);
        $this->app->useStoragePath($dir);
        try {
            $path=$project->getExpSurvivalFile('MYCN','hg19','gene','overall','tpm');
            $lines=file($path, FILE_IGNORE_NEW_LINES);
            $this->assertCount(11,$lines);
            $this->assertSame("S1\tP1\t20\t1\t1",$lines[1]);
            $this->assertSame("S10\tP10\t20\t1\t3.4594",$lines[10]);
            $this->assertNull($project->getExpSurvivalFile('MYCN','hg38','gene','overall','tpm'));
            $this->assertNull($project->getExpSurvivalFile('MISSING','hg19','gene','overall','tpm'));
        } finally {
            $this->app->useStoragePath($old);
            File::deleteDirectory($dir);
        }
    }
}
