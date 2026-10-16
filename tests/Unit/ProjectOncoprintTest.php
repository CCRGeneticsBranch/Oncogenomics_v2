<?php

namespace Tests\Unit;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Symfony\Component\Process\Process;

class ProjectOncoprintTest extends TestCase
{
    private array $refreshStatements = [];

    private function refreshStatements(string $dbType): array
    {
        if (!isset($this->refreshStatements[$dbType])) {
            $process = new Process(['perl', base_path('tests/fixtures/capture-refresh-views.pl'), $dbType,
                getenv('ONCOPRINT_REFRESH_SCRIPT') ?: base_path('app/scripts/backend/refreshViews.pl'),
                base_path('app/scripts/backend/refreshViews.pl')]);
            $process->mustRun();
            $this->refreshStatements[$dbType] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }
        return $this->refreshStatements[$dbType];
    }

    private function refreshAlterationTable(string $dbType = 'oracle'): void
    {
        $statement = array_values(array_filter($this->refreshStatements($dbType),
            static fn ($sql) => str_starts_with($sql, 'create table var_alteration ')))[0];
        // Exercise the emitted SELECT in SQLite; only MySQL's storage engine differs.
        $statement = str_replace(' engine=MyISAM', '', $statement);
        DB::statement('drop table if exists var_alteration');
        DB::statement($statement);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.db_connection' => 'sqlite']);
        DB::statement('create table project_patients (project_id integer, patient_id text)');
        DB::statement('create table project_samples (project_id integer, patient_id text, sample_id text)');
        DB::statement('create table var_fusion (patient_id text, sample_id text, left_gene text, right_gene text, var_level text)');
        DB::statement('create table var_tier_avia (patient_id text, sample_id text, gene text, type text, somatic_level text)');
        foreach (['P1', 'P2', 'P3', 'P4'] as $patient) {
            DB::table('project_patients')->insert(['project_id' => 42, 'patient_id' => $patient]);
            DB::table('project_samples')->insert(['project_id' => 42, 'patient_id' => $patient, 'sample_id' => 'S'.$patient]);
        }
        DB::table('project_samples')->insert(['project_id' => 99, 'patient_id' => 'P1', 'sample_id' => 'outside']);
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', static fn (...$values) => in_array(null, $values, true) ? null : implode('', $values));
        $this->refreshAlterationTable();
    }

    private function mutation($patient, $gene, $tier = 'Tier 1', $type = 'somatic', $sample = null): void
    {
        DB::table('var_tier_avia')->insert(['patient_id' => $patient, 'sample_id' => $sample ?? 'S'.$patient, 'gene' => $gene, 'type' => $type, 'somatic_level' => $tier]);
        $this->refreshAlterationTable();
    }

    private function fusion($patient): void
    {
        DB::table('var_fusion')->insert(['patient_id' => $patient, 'sample_id' => 'S'.$patient, 'left_gene' => 'BCR', 'right_gene' => 'ABL1', 'var_level' => '1']);
        $this->refreshAlterationTable();
    }

    public function test_matrix_counts_unique_patients_and_sorts_within_alteration_types(): void
    {
        $this->mutation('P1', 'TP53');
        $this->mutation('P1', 'TP53');
        $this->mutation('P2', 'TP53', 'Tier 1A', 'rnaseq');
        $this->mutation('P1', 'KRAS');
        $this->mutation('P2', 'ABL1');
        foreach (['P1', 'P2', 'P3'] as $patient) {
            $this->fusion($patient);
        }
        $this->fusion('P1');
        $matrix = Project::getOncoprintMatrix(42);
        $this->assertSame(['TP53', 'ABL1', 'KRAS', 'BCR::ABL1'], array_column($matrix['columns'], 'alteration'));
        $this->assertSame([2, 1, 1, 3], array_column($matrix['columns'], 'patient_count'));
        $this->assertSame([], $matrix['patients']['P4']);
        $this->assertTrue($matrix['patients']['P1']['mutation:TP53']);
        $this->assertArrayNotHasKey('mutation:KRAS', $matrix['patients']['P2']);
    }

    public function test_alterations_respect_project_samples_tier_and_mutation_type(): void
    {
        $this->mutation('P1', 'TP53');
        $this->mutation('P1', 'KRAS', 'Tier 2');
        $this->mutation('P1', 'OUTSIDE', 'Tier 1', 'somatic', 'outside');
        $this->mutation('P1', 'GERMLINE', 'Tier 1', 'germline');
        $this->fusion('P1');
        DB::table('var_fusion')->insert(['patient_id' => 'P2', 'sample_id' => 'SP2', 'left_gene' => 'ETV6', 'right_gene' => 'RUNX1', 'var_level' => '2']);
        $this->refreshAlterationTable();
        $this->assertCount(2, Project::getAlteration(42));
        $tier2 = Project::getAlteration(42, '2');
        $names = array_column($tier2, 'alteration');
        sort($names);
        $this->assertSame(['ETV6::RUNX1', 'KRAS'], $names);
    }

    public function test_empty_matrix_keeps_project_patients_and_renders_empty_state(): void
    {
        $matrix = Project::getOncoprintMatrix(42);
        $this->assertSame([], $matrix['columns']);
        $this->assertCount(4, $matrix['patients']);
        $html = view('pages.viewProjectOncoprint', $matrix)->render();
        $this->assertStringContainsString('No Tier 1 alterations found', $html);
        $this->assertStringContainsString('P4', $html);
    }

    public function test_matrix_renders_checkmarks_and_escapes_alteration_labels(): void
    {
        $this->mutation('P1', '<script>alert(1)</script>');
        $html = view('pages.viewProjectOncoprint', Project::getOncoprintMatrix(42))->render();
        $this->assertSame(1, substr_count($html, 'aria-label="Alteration present"'));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('1 patients', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_oncoprint_route_resolves_controller_and_requires_project_access(): void
    {
        $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/viewProjectOncoprint/42'));
        $this->assertInstanceOf(\App\Http\Controllers\ProjectController::class, $route->getController());
        $this->assertContains('logged', $route->middleware());
        $this->assertContains('authorized_project', $route->middleware());
    }

    public function test_cached_matrix_reuses_queries_and_can_be_refreshed_after_data_changes(): void
    {
        $this->mutation('P1', 'TP53');
        DB::enableQueryLog();
        $first = Project::getCachedOncoprintMatrix(42);
        $queries = count(DB::getQueryLog());
        $this->assertSame($first, Project::getCachedOncoprintMatrix(42));
        $this->assertSame($queries, count(DB::getQueryLog()));
        $this->mutation('P2', 'KRAS');
        $this->assertCount(1, Project::getCachedOncoprintMatrix(42)['columns']);
        $this->assertCount(2, Project::getCachedOncoprintMatrix(42, true)['columns']);
        $this->assertSame([], Project::getCachedOncoprintMatrix(99)['columns']);
    }

    public function test_cached_matrix_expires_after_one_hour(): void
    {
        Project::getCachedOncoprintMatrix(42);
        $this->mutation('P1', 'TP53');
        $this->travel(3601)->seconds();
        $this->assertCount(1, Project::getCachedOncoprintMatrix(42)['columns']);
    }

    public function test_command_precalculates_the_cache(): void
    {
        DB::statement('create table projects (id integer, name text)');
        DB::table('projects')->insert(['id' => 42, 'name' => 'ACES']);
        $this->mutation('P1', 'TP53');
        $this->artisan('oncoprint:cache', ['project_id' => 42])
            ->expectsOutputToContain('4 patients, 1 alterations cached for one hour.')
            ->assertSuccessful();
        $this->mutation('P2', 'KRAS');
        $this->assertCount(1, Project::getCachedOncoprintMatrix(42)['columns']);
    }

    public function test_mysql_and_oracle_refresh_queries_produce_the_same_summary(): void
    {
        $this->mutation('P1', 'TP53', 'Tier 1A');
        $this->mutation('P1', 'TP53', 'Tier 1B');
        $this->mutation('P2', 'KRAS', 'Tier 2');
        $this->fusion('P1');
        $oracle = Project::getAlteration(42);
        $this->refreshAlterationTable('mysql');
        $this->assertEqualsCanonicalizing($oracle, Project::getAlteration(42));
        $this->assertCount(2, Project::getAlteration(42));
        $this->assertCount(1, Project::getAlteration(42, '1A'));
        foreach (['mysql', 'oracle'] as $dbType) {
            $this->assertContains('create index var_alteration_prj_tier_idx on var_alteration (project_id, tier)', $this->refreshStatements($dbType));
        }
        // Reads must still work without access to any of the expensive source tables.
        DB::statement('drop table var_fusion');
        DB::statement('drop table var_tier_avia');
        DB::statement('drop table project_samples');
        $this->assertCount(2, Project::getAlteration(42));
    }
}
