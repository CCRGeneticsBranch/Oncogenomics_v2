<?php

namespace Tests\Unit;

use App\Services\ChatbotMarkdownRenderer;
use Tests\TestCase;

class ChatbotMarkdownRendererTest extends TestCase
{
    public function test_it_renders_github_flavored_markdown_tables(): void
    {
        $html = app(ChatbotMarkdownRenderer::class)->render(<<<'MARKDOWN'
            | Gene | Mean TPM |
            | --- | ---: |
            | FGFR4 | 4.09 |
            MARKDOWN);

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Gene</th>', $html);
        $this->assertStringContainsString('<td>FGFR4</td>', $html);
        $this->assertStringContainsString('>4.09</td>', $html);
    }

    public function test_it_unwraps_a_whole_answer_markdown_fence(): void
    {
        $html = app(ChatbotMarkdownRenderer::class)->render(<<<'MARKDOWN'
            ```markdown
            | Gene | TPM |
            | --- | ---: |
            | FGFR4 | 4.09 |
            ```
            MARKDOWN);

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringNotContainsString('<pre>', $html);
    }

    public function test_it_strips_raw_html_unsafe_links_and_remote_images(): void
    {
        $html = app(ChatbotMarkdownRenderer::class)->render(<<<'MARKDOWN'
            <script>alert('x')</script>

            <img src=x onerror=alert(1)>

            [unsafe](javascript:alert(1))

            ![tracker](https://tracker.example/pixel.png)
            MARKDOWN);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('tracker.example', $html);
        $this->assertStringContainsString('unsafe', $html);
    }

    public function test_it_turns_a_json_encoded_tool_link_cell_into_a_clickable_link(): void
    {
        $html = app(ChatbotMarkdownRenderer::class)->render(<<<'MARKDOWN'
            | Library | Target |
            | --- | --- |
            | {"type":"link","label":"TR14_H3K27ac_C","url":"https://example.test/viewChIPseqSample/TR14/S1"} | MYCN |
            | {"type":"link","label":"Unsafe","url":"javascript:alert(1)"} | MYCN |
            MARKDOWN);

        $this->assertStringContainsString('<a href="https://example.test/viewChIPseqSample/TR14/S1">TR14_H3K27ac_C</a>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('Unsafe', $html);
    }

    public function test_it_shows_only_known_tiering_figures_as_local_clickable_images(): void
    {
        $renderer = app(ChatbotMarkdownRenderer::class);
        foreach (['Germline-Classification--03022017-1.svg', 'Somatic-Classification--01032017-1.svg', 'fusion_tier.png'] as $file) {
            $source = 'https://clinomics.ccr.cancer.gov/clinomics/public/images/tutorial/'.$file;
            $local = asset('images/tutorial/'.$file);
            foreach ([$source, $local] as $url) {
                $html = $renderer->render('![Tier **definitions**](<'.$url.'>)');
                $this->assertStringContainsString('src="'.e($local).'"', $html);
                $this->assertStringContainsString('href="'.e($local).'"', $html);
                $this->assertStringContainsString('alt="Tier definitions"', $html);
                $this->assertStringContainsString('class="chat-wiki-figure"', $html);
                $this->assertStringContainsString('target="_blank"', $html);
            }
        }
        foreach ([asset('images/tutorial/fusion_tier.png').'?tracker=1',
            'https://tracker.example/fusion_tier.png', '/images/unknown.png',
            'https://clinomics.ccr.cancer.gov.evil.test/clinomics/public/images/tutorial/fusion_tier.png'] as $url) {
            $this->assertStringNotContainsString('<img', $renderer->render('![bad](<'.$url.'>)'));
        }
    }
}
