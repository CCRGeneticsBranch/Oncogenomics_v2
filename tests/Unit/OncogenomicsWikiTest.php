<?php

namespace Tests\Unit;

use App\Services\OncogenomicsWiki;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OncogenomicsWikiTest extends TestCase
{
    private string $directory;

    private function bundledPageCount(): int
    {
        return count(json_decode(file_get_contents(resource_path('knowledge/oncogenomics-wiki.json')), true)['pages']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/wiki-test-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory);
        config()->set('chatbot.wiki.index_path', $this->directory.'/index.json');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_bundled_wiki_finds_documented_workflows_with_source_links(): void
    {
        $wiki = app(OncogenomicsWiki::class);
        $result = $wiki->search('How do I download project expression matrices?', 3);
        $this->assertSame($this->bundledPageCount(), $result['page_count']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $result['revision']);
        $this->assertSame('12. Expression and differential expression', $result['matches'][0]['title']);
        $this->assertSame(OncogenomicsWiki::URL.'/12.-Expression-and-differential-expression#download-expression-matrices', $result['matches'][0]['url']);
        $this->assertStringContainsString('matrix', $result['matches'][0]['text']);
        $this->assertCount(3, $result['matches']);
        foreach ($result['matches'] as $match) $this->assertLessThanOrEqual(2200, mb_strlen($match['text']));
        $fusion = $wiki->search('fusion cutoff minimum patients');
        $this->assertSame('11. Cohorts filters and counts', $fusion['matches'][0]['title']);
        $this->assertStringContainsString('Fusion summary cutoff', $fusion['matches'][0]['section']);
    }

    public function test_unknown_and_stopword_queries_return_no_invented_matches(): void
    {
        $wiki = app(OncogenomicsWiki::class);
        foreach (['qzxv999unknownfeature', 'how do I use this'] as $query) {
            $this->assertSame([], $wiki->search($query)['matches']);
        }
    }

    public function test_tier_queries_return_the_appropriate_figures_with_source_citations(): void
    {
        $wiki = app(OncogenomicsWiki::class);
        foreach ([['What do fusion tiers mean?', ['Fusion tier definitions']],
            ['Define somatic tiers', ['Somatic mutation tier definitions']],
            ['Explain germline tiering', ['Germline mutation tier definitions']],
            ['What are mutation tiers?', ['Germline mutation tier definitions', 'Somatic mutation tier definitions']]] as [$query, $titles]) {
            $figures = $wiki->search($query)['figures'];
            $this->assertSame($titles, array_column($figures, 'title'));
            foreach ($figures as $figure) {
                $this->assertStringStartsWith(asset('images/tutorial/'), $figure['image_url']);
                $this->assertStringStartsWith(OncogenomicsWiki::URL.'/6.-Cases#', $figure['wiki_url']);
            }
        }
        $this->assertSame([], $wiki->search('How do I download expression?')['figures']);
    }

    public function test_refresh_overrides_bundle_and_preserves_comparisons_screenshots_and_links(): void
    {
        file_put_contents($this->directory.'/Test.md', "# Table of Contents\n- [QC](#qc)\n## QC\nCoverage <100bp remains valid.\n<img src=\"https://example.test/qc.png\" />\nSee [detail](6.-Cases#qc).\n## Final\nCoverage validation continues here.\n");
        file_put_contents($this->directory.'/_Footer.md', 'Footer must not enter the index.');
        $wiki = app(OncogenomicsWiki::class);
        $snapshot = $wiki->snapshot($this->directory, str_repeat('a', 40));
        $this->assertCount(1, $snapshot['pages']);
        file_put_contents($this->directory.'/index.json', json_encode($snapshot));
        $result = $wiki->search('coverage', 8);
        $this->assertSame(str_repeat('a', 40), $result['revision']);
        $this->assertCount(2, $result['matches']);
        $qc = array_values(array_filter($result['matches'], fn ($r) => $r['section'] === 'QC'))[0];
        $this->assertStringContainsString('Coverage <100bp remains valid.', $qc['text']);
        $this->assertStringContainsString('[Screenshot](https://example.test/qc.png)', $qc['text']);
        $this->assertStringContainsString(OncogenomicsWiki::URL.'/6.-Cases#qc', $qc['text']);
        $this->assertStringNotContainsString('Table of Contents', $qc['section']);
        file_put_contents($this->directory.'/index.json', '{broken');
        $this->assertSame($this->bundledPageCount(), $wiki->search('project')['page_count']);
    }

    public function test_failed_refresh_preserves_the_existing_index(): void
    {
        file_put_contents($this->directory.'/index.json', 'previous-index');
        $this->artisan('chatbot:refresh-wiki', ['--source' => $this->directory.'/missing'])
            ->assertExitCode(1);
        $this->assertSame('previous-index', file_get_contents($this->directory.'/index.json'));
    }

    public function test_missing_indexes_fail_explicitly(): void
    {
        config()->set('chatbot.wiki.snapshot_path', $this->directory.'/missing.json');
        $this->expectException(\RuntimeException::class);
        app(OncogenomicsWiki::class)->search('expression');
    }
}
