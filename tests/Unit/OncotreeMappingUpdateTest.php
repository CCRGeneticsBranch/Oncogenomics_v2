<?php

namespace Tests\Unit;

use App\Services\Oncotree\CodexResearcher;
use App\Services\Oncotree\EvidenceVerifier;
use App\Services\Oncotree\MappingUpdater;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class OncotreeMappingUpdateTest extends TestCase
{
    private string $directory;
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/oncotree-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
        file_put_contents($this->directory.'/taxonomy.tsv', "ID\tTEXT\tMAIN_TYPE\tPARENT\nRMS\tRhabdomyosarcoma\tSoft Tissue\tSOFT_TISSUE\n");
        config(['oncotree_mapping.mode' => 'dev', 'oncotree_mapping.directory' => $this->directory.'/runs',
            'oncotree_mapping.taxonomy' => $this->directory.'/taxonomy.tsv', 'oncotree_mapping.limit' => 0]);
        $schema = DB::connection()->getSchemaBuilder();
        $schema->create('cancer_types', function (Blueprint $t) { $t->string('id'); $t->string('name')->nullable(); $t->string('description')->nullable(); });
        $schema->create('oncotree_mapping', function (Blueprint $t) { $t->string('diagnosis'); $t->string('id'); });
        $schema->create('patients', function (Blueprint $t) { $t->string('patient_id'); $t->string('diagnosis'); });
        $schema->create('users', function (Blueprint $t) { $t->string('email'); $t->text('permissions'); });
        DB::table('cancer_types')->insert(['id' => 'Lab RMS', 'name' => 'Rhabdomyosarcoma', 'description' => 'Rhabdomyosarcoma']);
        Http::preventStrayRequests();
        Http::fake(['https://pubmed.ncbi.nlm.nih.gov/123/' => Http::response('<p>Rhabdomyosarcoma is a soft tissue sarcoma.</p>', 200)]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function proposal(array $changes = []): array
    {
        return array_replace(['diagnosis' => 'Lab RMS', 'status' => 'matched', 'code' => 'RMS',
            'reason' => 'The supplied name supports the broad RMS category, without subtype.',
            'sources' => [['url' => 'https://pubmed.ncbi.nlm.nih.gov/123/', 'title' => 'Test evidence',
                'quote' => 'Rhabdomyosarcoma is a soft tissue sarcoma.']]], $changes);
    }

    private function researcher(array $proposal): void
    {
        $mock = \Mockery::mock(CodexResearcher::class);
        $mock->shouldReceive('research')->once()->andReturn($proposal);
        $this->app->instance(CodexResearcher::class, $mock);
    }

    private function expectMail(string $recipient, string $bodyContains): void
    {
        Mail::shouldReceive('raw')->once()->withArgs(function ($body, $callback) use ($recipient, $bodyContains) {
            $email = new Email();
            $callback(new Message($email));
            $this->assertSame([$recipient], array_map(fn ($a) => $a->getAddress(), $email->getTo()));
            $this->assertStringContainsString($bodyContains, $body);
            return true;
        });
    }

    public function test_llm_normal_classification_uses_local_code_and_preserves_label(): void
    {
        $label = 'Human embryonic kidney 293  cells ';
        DB::table('cancer_types')->delete();
        DB::table('cancer_types')->insert(['id' => $label]);
        $this->researcher($this->proposal([
            'diagnosis' => $label, 'code' => 'NORMAL', 'mapping_basis' => 'local_convention',
            'reason' => 'HEK293 is grouped as non-tumor under the local convention.',
            'sources' => [],
        ]));
        $this->expectMail('chouh@nih.gov', 'NORMAL');
        $this->artisan('oncotree:update-mappings')->assertExitCode(0);
        $this->assertSame('NORMAL', DB::table('oncotree_mapping')->where('diagnosis', $label)->value('id'));
        Http::assertNothingSent();
    }

    public function test_summary_lists_only_patients_with_matching_diagnosis(): void
    {
        DB::table('patients')->insert([
            ['patient_id' => 'P002', 'diagnosis' => 'Lab RMS'],
            ['patient_id' => 'P001', 'diagnosis' => 'Lab RMS'],
            ['patient_id' => 'P001', 'diagnosis' => 'Lab RMS'],
            ['patient_id' => 'OTHER', 'diagnosis' => 'Other diagnosis'],
        ]);
        $this->researcher($this->proposal());
        $this->expectMail('chouh@nih.gov', 'Patients: P001, P002');
        $this->artisan('oncotree:update-mappings', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(0, DB::table('oncotree_mapping')->count());
        $body = file_get_contents(glob($this->directory.'/runs/*/summary.txt')[0]);
        $this->assertStringNotContainsString('OTHER', $body);
    }

    public function test_dev_run_inserts_only_validated_mapping_and_emails_only_dev_recipient(): void
    {
        DB::table('users')->insert(['email' => 'manager@example.org', 'permissions' => '{"_projectmanager":1}']);
        $this->researcher($this->proposal());
        $this->expectMail('chouh@nih.gov', 'Lab RMS => RMS [inserted]');
        $this->artisan('oncotree:update-mappings')->assertExitCode(0);
        $this->assertSame('RMS', DB::table('oncotree_mapping')->where('diagnosis', 'Lab RMS')->value('id'));
        $reports = glob($this->directory.'/runs/*/report.json');
        $report = json_decode(file_get_contents($reports[0]), true);
        $this->assertSame('sent', $report['email_status']);
        $this->assertSame(hash_file('sha256', $this->directory.'/taxonomy.tsv'), $report['taxonomy_sha256']);
        $this->assertArrayHasKey('page_sha256', $report['results'][0]['sources'][0]);
    }

    public function test_default_unlimited_processes_more_than_previous_default(): void
    {
        DB::table('cancer_types')->delete();
        for ($i = 0; $i < 21; $i++) {
            DB::table('cancer_types')->insert(['id' => 'Diagnosis '.$i, 'name' => 'Unknown', 'description' => 'Unknown']);
        }
        $mock = \Mockery::mock(CodexResearcher::class);
        $mock->shouldReceive('research')->times(21)->andReturnUsing(static fn ($diagnosis) => [
            'diagnosis' => $diagnosis['id'], 'status' => 'unresolved', 'code' => null,
            'reason' => 'Insufficient evidence.', 'sources' => [],
        ]);
        $this->app->instance(CodexResearcher::class, $mock);
        $this->expectMail('chouh@nih.gov', 'DRY RUN');
        $this->artisan('oncotree:update-mappings', ['--dry-run' => true])->assertExitCode(0);
        $report = json_decode(file_get_contents(glob($this->directory.'/runs/*/report.json')[0]), true);
        $this->assertSame(21, $report['processed']);
        $this->assertSame(0, $report['remaining_unprocessed']);
    }

    public function test_explicit_limit_still_restricts_processing(): void
    {
        DB::table('cancer_types')->insert(['id' => 'Z diagnosis', 'name' => 'Unknown', 'description' => 'Unknown']);
        $this->researcher($this->proposal());
        $this->expectMail('chouh@nih.gov', 'DRY RUN');
        $this->artisan('oncotree:update-mappings', ['--dry-run' => true, '--limit' => 1])->assertExitCode(0);
        $report = json_decode(file_get_contents(glob($this->directory.'/runs/*/report.json')[0]), true);
        $this->assertSame(1, $report['processed']);
        $this->assertSame(1, $report['remaining_unprocessed']);
    }

    public function test_llm_interprets_typo_using_taxonomy_without_web_quote(): void
    {
        $label = 'Large cel lung carcinoma';
        DB::table('cancer_types')->delete();
        DB::table('cancer_types')->insert(['id' => $label]);
        file_put_contents($this->directory.'/taxonomy.tsv', "LCLC\tLarge Cell Lung Carcinoma\tNon-Small Cell Lung Cancer\tNSCLC\n", FILE_APPEND);
        $this->researcher($this->proposal([
            'diagnosis' => $label, 'code' => 'LCLC', 'mapping_basis' => 'taxonomy',
            'reason' => 'The typo cel means cell; the supplied taxonomy supports LCLC.',
            'sources' => [],
        ]));
        $this->expectMail('chouh@nih.gov', 'typo cel');
        $this->artisan('oncotree:update-mappings')->assertExitCode(0);
        $this->assertSame('LCLC', DB::table('oncotree_mapping')->where('diagnosis', $label)->value('id'));
        Http::assertNothingSent();
    }

    public function test_taxonomy_based_proposal_still_rejects_unknown_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(MappingUpdater::class)->validate($this->proposal([
            'code' => 'INVENTED', 'mapping_basis' => 'taxonomy', 'sources' => [],
        ]), ['id' => 'Lab RMS'], ['RMS' => []]);
    }

    public function test_llm_applies_local_convention_without_web_quote(): void
    {
        $label = 'Ewings like sarcoma';
        DB::table('cancer_types')->delete();
        DB::table('cancer_types')->insert(['id' => $label]);
        file_put_contents($this->directory.'/taxonomy.tsv', "ES\tEwing Sarcoma\tBone Cancer\tBONE\n", FILE_APPEND);
        $this->researcher($this->proposal([
            'diagnosis' => $label, 'code' => 'ES', 'mapping_basis' => 'local_convention',
            'reason' => 'User-approved Ewing cohort convention; site is not established.', 'sources' => [],
        ]));
        $this->expectMail('chouh@nih.gov', 'cohort convention');
        $this->artisan('oncotree:update-mappings')->assertExitCode(0);
        $this->assertSame('ES', DB::table('oncotree_mapping')->value('id'));
        Http::assertNothingSent();
    }

    public function test_dry_run_emails_only_developer_even_in_production_without_database_or_state_changes(): void
    {
        config(['oncotree_mapping.mode' => 'production']);
        DB::table('users')->insert(['email' => 'manager@example.org', 'permissions' => '{"_projectmanager":1}']);
        $this->researcher($this->proposal());
        $this->expectMail('chouh@nih.gov', 'DRY RUN');
        $this->artisan('oncotree:update-mappings', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(0, DB::table('oncotree_mapping')->count());
        $this->assertFileDoesNotExist($this->directory.'/runs/attempts.json');
        $report = json_decode(file_get_contents(glob($this->directory.'/runs/*/report.json')[0]), true);
        $this->assertSame('would_insert', $report['results'][0]['status']);
        $this->assertSame('sent', $report['email_status']);
        $this->assertSame(['chouh@nih.gov'], $report['email_recipients']);
    }

    public function test_invalid_code_fails_without_mapping_and_sends_error_summary(): void
    {
        $this->researcher($this->proposal(['code' => 'INVENTED']));
        $this->expectMail('chouh@nih.gov', '[error]');
        $this->artisan('oncotree:update-mappings')->assertExitCode(1);
        $this->assertSame(0, DB::table('oncotree_mapping')->count());
        Http::assertNothingSent();
    }

    public function test_uncertain_results_stay_unmapped_and_are_not_researched_each_week(): void
    {
        $this->researcher($this->proposal(['status' => 'ambiguous', 'code' => null, 'sources' => []]));
        Mail::shouldReceive('raw')->twice();
        $this->artisan('oncotree:update-mappings')->assertExitCode(0);
        $this->artisan('oncotree:update-mappings')->assertExitCode(0);
        $this->assertSame(0, DB::table('oncotree_mapping')->count());
    }

    public function test_existing_mapping_is_not_overwritten_even_if_insert_races(): void
    {
        $updater = app(MappingUpdater::class);
        $diagnosis = $updater->unmapped()[0];
        DB::table('oncotree_mapping')->insert(['diagnosis' => 'Lab RMS', 'id' => 'EXISTING']);
        $this->assertSame([], $updater->unmapped());
        $this->assertFalse($updater->insert($diagnosis, 'RMS'));
        $this->assertSame('EXISTING', DB::table('oncotree_mapping')->value('id'));
    }

    public function test_production_recipients_require_enabled_permissions_and_are_deduplicated(): void
    {
        foreach ([['A@example.org', '{"_superadmin":1}'], ['a@example.org', '{"_projectmanager":1}'],
            ['b@example.org', '{"_projectmanager":"1"}'], ['no@example.org', '{"_projectmanager":0}'],
            ['bad@example.org', '{"_superadmin":"false"}'], ['broken@example.org', 'bad json'],
            ['not-email', '{"_superadmin":1}']] as $r) {
            DB::table('users')->insert(['email' => $r[0], 'permissions' => $r[1]]);
        }
        $this->assertSame(['a@example.org', 'b@example.org'], app(MappingUpdater::class)->recipients('production'));
        $this->assertSame(['chouh@nih.gov'], app(MappingUpdater::class)->recipients('dev'));
    }

    public function test_email_failure_preserves_committed_mapping_and_reports_failure(): void
    {
        $this->researcher($this->proposal());
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $this->artisan('oncotree:update-mappings')->assertExitCode(1);
        $this->assertSame('RMS', DB::table('oncotree_mapping')->value('id'));
        $report = json_decode(file_get_contents(glob($this->directory.'/runs/*/report.json')[0]), true);
        $this->assertSame('failed', $report['email_status']);
    }

    public function test_changed_diagnosis_is_not_inserted_after_research(): void
    {
        $updater = app(MappingUpdater::class);
        $diagnosis = $updater->unmapped()[0];
        DB::table('cancer_types')->where('id', $diagnosis['id'])->update(['description' => 'Changed diagnosis']);
        try {
            $updater->insert($diagnosis, 'RMS');
            $this->fail('Expected changed diagnosis rejection.');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, DB::table('oncotree_mapping')->count());
        }
    }

    public function test_research_cannot_relabel_the_input_diagnosis(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $updater = app(MappingUpdater::class);
        $updater->validate($this->proposal(['diagnosis' => 'Other']), $updater->unmapped()[0], $updater->taxonomy($this->directory.'/taxonomy.tsv'));
    }

    public function test_quote_formatting_differences_are_accepted_but_changed_words_are_not(): void
    {
        Http::fake(['https://pubmed.ncbi.nlm.nih.gov/123/' => Http::response('<p>Rhabdomyosarcoma is a <b>soft</b>–tissue&nbsp;sarcoma.</p>')]);
        $sources = $this->proposal()['sources'];
        $this->assertCount(1, app(EvidenceVerifier::class)->verify($sources));
        $sources[0]['quote'] = 'Rhabdomyosarcoma is not a soft tissue sarcoma.';
        try {
            app(EvidenceVerifier::class)->verify($sources);
            $this->fail('Changed meaning must not be accepted.');
        } catch (\App\Services\Oncotree\EvidenceValidationException $e) {
            $this->assertSame($sources[0]['url'], $e->details['url']);
            $this->assertSame($sources[0]['quote'], $e->details['quote']);
        }
    }

    public function test_failed_citation_is_researched_again_with_feedback(): void
    {
        $bad = $this->proposal();
        $bad['sources'][0]['quote'] = 'A fabricated passage that the source does not contain.';
        $mock = \Mockery::mock(CodexResearcher::class);
        $mock->shouldReceive('research')->once()->ordered()
            ->withArgs(fn ($d, $t, $dir, $feedback) => $feedback === null)->andReturn($bad);
        $mock->shouldReceive('research')->once()->ordered()
            ->withArgs(fn ($d, $t, $dir, $feedback) => $feedback['failure']['url'] === $bad['sources'][0]['url'])
            ->andReturn($this->proposal());
        $this->app->instance(CodexResearcher::class, $mock);
        $this->expectMail('chouh@nih.gov', '[inserted]');
        $this->artisan('oncotree:update-mappings')->assertExitCode(0);
        $this->assertSame('RMS', DB::table('oncotree_mapping')->value('id'));
        $this->assertCount(1, glob($this->directory.'/runs/*/term-1/attempt-1/evidence-error.json'));
        $report = json_decode(file_get_contents(glob($this->directory.'/runs/*/report.json')[0]), true);
        $this->assertSame(2, $report['results'][0]['research_attempts']);
    }

    public function test_unverified_quote_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $sources = $this->proposal()['sources'];
        $sources[0]['quote'] = 'This is a fabricated statement not present in the source.';
        app(EvidenceVerifier::class)->verify($sources);
    }

    public function test_unapproved_evidence_host_is_never_fetched(): void
    {
        $sources = $this->proposal()['sources'];
        $sources[0]['url'] = 'https://localhost/private';
        try {
            app(EvidenceVerifier::class)->verify($sources);
            $this->fail('Expected rejection.');
        } catch (\InvalidArgumentException $e) {
            Http::assertNothingSent();
        }
    }
}
