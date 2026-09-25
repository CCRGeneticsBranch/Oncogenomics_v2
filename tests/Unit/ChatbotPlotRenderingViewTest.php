<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChatbotPlotRenderingViewTest extends TestCase
{
    public function test_chatbot_lazily_renders_server_produced_plotly_charts(): void
    {
        $source = File::get(resource_path('views/pages/viewChatbot.blade.php'));

        $this->assertFileExists(public_path('packages/plotly/plotly.min.js'));
        $this->assertStringContainsString(
            'plotly.js v2.35.2',
            (string) file_get_contents(
                public_path('packages/plotly/plotly.min.js'),
                false,
                null,
                0,
                256,
            ),
        );

        $this->assertStringContainsString(
            "var plotlyUrl = @json(url('/packages/plotly/plotly.min.js'));",
            $source,
        );
        $this->assertStringContainsString('function loadPlotly()', $source);
        $this->assertStringContainsString('function normalizeViolinPayload(data, layout, chart)', $source);
        $this->assertStringContainsString('trace.points = false', $source);
        $this->assertStringContainsString("trace.spanmode = 'hard'", $source);
        $this->assertStringContainsString('trace.width = 0.8', $source);
        $this->assertStringContainsString('trace.x0 = group', $source);
        $this->assertStringContainsString('groups[group].push(value)', $source);
        $this->assertStringContainsString("layout.xaxis.categoryorder = 'array'", $source);
        $this->assertStringContainsString('Array.isArray(execution.charts)', $source);
        $this->assertStringContainsString("chart.type === 'plotly'", $source);
        $this->assertStringContainsString('plotly.newPlot(canvas, data, layout, config)', $source);
        $this->assertStringContainsString('evidence.open = executions.some', $source);
        $this->assertStringContainsString('var hasTable = execution.table', $source);
        $this->assertStringContainsString('return hasChart || hasTable;', $source);
    }

    public function test_chatbot_chart_status_and_labels_are_written_as_text(): void
    {
        $source = File::get(resource_path('views/pages/viewChatbot.blade.php'));

        $this->assertStringContainsString("chartStatus.setAttribute('role', 'status')", $source);
        $this->assertStringContainsString("chartStatus.setAttribute('aria-live', 'polite')", $source);
        $this->assertStringContainsString("canvas.setAttribute('role', 'img')", $source);
        $this->assertStringContainsString('chartTitle.textContent =', $source);
        $this->assertStringContainsString('chartStatus.textContent =', $source);
        $this->assertStringNotContainsString('chartTitle.innerHTML =', $source);
        $this->assertStringNotContainsString('chartStatus.innerHTML =', $source);
    }

    public function test_tool_result_links_are_promoted_outside_collapsed_evidence(): void
    {
        $source = File::get(resource_path('views/pages/viewChatbot.blade.php'));

        $this->assertStringContainsString("primaryLinks.setAttribute('aria-label', 'Primary result links')", $source);
        $this->assertStringContainsString('bubble.body.insertBefore(primaryLinks, bubble.meta)', $source);
        $this->assertStringContainsString("anchor.className = 'btn btn-primary btn-sm'", $source);
        $this->assertStringNotContainsString('block.appendChild(links)', $source);
    }

    public function test_structured_table_links_are_created_without_rendering_raw_html(): void
    {
        $source = File::get(resource_path('views/pages/viewChatbot.blade.php'));

        $this->assertStringContainsString('function structuredLinkCell(value)', $source);
        $this->assertStringContainsString('candidate = JSON.parse(trimmed)', $source);
        $this->assertStringContainsString('var linkCell = structuredLinkCell(cell)', $source);
        $this->assertStringContainsString('cellLink.textContent = linkCell.label', $source);
        $this->assertStringContainsString('td.appendChild(cellLink)', $source);
        $this->assertStringNotContainsString('td.innerHTML', $source);
    }
}
