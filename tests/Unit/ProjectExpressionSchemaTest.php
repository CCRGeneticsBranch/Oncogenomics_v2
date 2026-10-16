<?php

namespace Tests\Unit;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectExpressionSchemaTest extends TestCase
{
    public function test_expression_reads_annotation_schema_and_preserves_sample_alignment(): void
    {
        $this->checkExpressionSchema('target_type', 'ensembl');
    }

    public function test_expression_still_filters_genome_schema(): void
    {
        $this->checkExpressionSchema('genome_version', 'hg19');
    }

    private function checkExpressionSchema(string $column, string $version): void
    {
        DB::statement('create table project_mview (id integer, version text)');
        DB::table('project_mview')->insert(['id' => 731, 'version' => 'hg19']);
        DB::statement('create table project_samples (project_id integer, patient_id text, sample_id text)');
        DB::statement('create table samples (patient_id text, sample_id text, sample_name text, sample_alias text, exp_type text, library_type text)');
        DB::statement("create table project_values (project_id integer, symbol text, target text, target_level text, value_type text, value_list text, {$column} text)");
        DB::table('project_samples')->insert(['project_id' => 731, 'patient_id' => 'P1', 'sample_id' => 'S1']);
        DB::table('samples')->insert(['patient_id' => 'P1', 'sample_id' => 'S1', 'sample_name' => 'sample', 'sample_alias' => 'alias', 'exp_type' => 'RNAseq', 'library_type' => 'polyA']);
        foreach (['_list' => 'outside,S1', 'MYCN' => '99,12.5'] as $symbol => $values) {
            DB::table('project_values')->insert(['project_id' => 731, 'symbol' => $symbol, 'target' => $symbol, 'target_level' => 'gene', 'value_type' => 'tpm', 'value_list' => $values, $column => $version]);
        }
        DB::table('project_values')->insert(['project_id' => 731, 'symbol' => 'MYCN', 'target' => 'MYCN', 'target_level' => 'gene', 'value_type' => 'tpm', 'value_list' => '88,77', $column => 'other']);

        $project = new Project;
        $project->id = 731;
        $result = $project->getGeneExpression(['MYCN'], 'hg19', 'all', 'gene', false, 'all', 'tpm');

        $this->assertSame(['S1'], $result['sample_ids']);
        $this->assertSame(['alias'], $result['samples']);
        $this->assertSame(['hg19' => [12.5]], $result['exp_data']['MYCN']);
    }
}
