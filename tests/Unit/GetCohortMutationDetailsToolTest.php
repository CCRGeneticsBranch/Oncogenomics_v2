<?php
namespace Tests\Unit;

use App\Mcp\Tools\GetCohortMutationDetailsTool;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Tests\TestCase;

class GetCohortMutationDetailsToolTest extends TestCase
{
    private function tool(): GetCohortMutationDetailsTool
    {
        return new class extends GetCohortMutationDetailsTool {
            public int $reads = 0;
            protected function isAccessibleProject(int $id): bool { return $id === 42; }
            protected function isAvailableCancerType(string $id): bool { return $id === 'RMS'; }
            protected function mutationDetails(string $type, int|string $id, array $filters, int $limit, int $offset, bool $summary): array {
                $this->reads++;
                return ['rows' => $summary ? [] : [['patient_id' => 'P'.($offset + 1), 'gene' => $filters['gene']]],
                    'total_rows' => 3, 'patient_count' => 3, 'sample_count' => 3];
            }
        };
    }

    private function input(array $extra = []): array
    {
        return array_merge(['cohort_type' => 'project', 'cohort_id' => 42, 'gene' => 'alk', 'type' => 'somatic'], $extra);
    }

    public function test_compact_page_is_not_paginated_twice(): void
    {
        $r = $this->tool()->handle(new Request($this->input(['output' => ['offset' => 1, 'limit' => 1, 'fields' => ['Patient ID', 'Gene']]])))->getStructuredContent();
        $this->assertSame('success', $r['status']);
        $this->assertSame([['P2', 'ALK']], $r['rows']);
        $this->assertSame(3, $r['total_rows']);
        $this->assertSame(3, $r['patient_count']);
        $this->assertSame(1, $r['returned_rows']);
        $this->assertSame(2, $r['next_offset']);
    }

    public function test_summary_and_website_page_keep_full_counts(): void
    {
        $summary = $this->tool()->handle(new Request($this->input(['output' => ['mode' => 'summary']])))->getStructuredContent();
        $this->assertArrayNotHasKey('rows', $summary);
        $this->assertSame(3, $summary['total_rows']);
        $this->assertSame(0, $summary['returned_rows']);
        $this->assertNull($summary['next_offset']);
        $page = $this->tool()->handle(new Request($this->input(['limit' => 1, 'offset' => 2])))->getStructuredContent();
        $this->assertSame('P3', json_decode($page['table_json'], true)['data'][0][0]);
        $this->assertSame(3, $page['total_rows']);
        $this->assertNull($page['next_offset']);
    }

    public function test_unauthorized_cohorts_do_not_read_variants(): void
    {
        foreach ([['cohort_id' => 99], ['cohort_type' => 'cancer_type', 'cohort_id' => 'Unknown']] as $extra) {
            $tool = $this->tool();
            $r = $tool->handle(new Request($this->input($extra)))->getStructuredContent();
            $this->assertSame('error', $r['status']);
            $this->assertSame(0, $tool->reads);
        }
    }

    public function test_page_limit_is_bounded(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool()->handle(new Request($this->input(['output' => ['limit' => 1001]])));
    }
}
