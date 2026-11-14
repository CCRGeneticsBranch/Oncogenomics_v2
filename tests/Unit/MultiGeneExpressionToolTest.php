<?php
namespace Tests\Unit;

use App\Mcp\Tools\GetCohortExpressionTool;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Tests\TestCase;

class MultiGeneExpressionToolTest extends TestCase
{
    private function tool(): GetCohortExpressionTool
    {
        return new class extends GetCohortExpressionTool {
            public int $reads = 0;
            protected function isAccessibleProject(int $id): bool { return $id === 42; }
            protected function projectLabel(int $id): ?string { return 'Study'; }
            protected function isAvailableCancerType(string $id): bool { return $id === 'RMS'; }
            protected function resolveGeneSymbol(string $gene, string $build): ?string {
                return in_array($gene, ['ALK', 'MYCN', 'FGFR4'], true) ? $gene : null;
            }
            protected function cohortExpressionDataForGenes(string $type, int|string $id, array $genes, string $tissue, string $build): array {
                $this->reads++;
                return ['sample_ids' => ['S2', 'S1'], 'samples' => ['S2', 'S1'], 'patients' => ['S2' => 'P2', 'S1' => 'P1'],
                    'exp_data' => ['ALK' => [$build => [0, 2]], 'MYCN' => [$build => [4, null]]]];
            }
        };
    }

    public function test_one_fetch_aligns_genes_with_missing_values_and_full_statistics(): void
    {
        $tool = $this->tool();
        $r = $tool->handle(new Request(['cohort_type' => 'project', 'cohort_id' => 42,
            'genes' => ['alk', 'MYCN', 'ALK', 'FGFR4'], 'output' => ['limit' => 1]]))->getStructuredContent();
        $this->assertSame('success', $r['status']);
        $this->assertSame(1, $tool->reads);
        $this->assertSame(['ALK', 'MYCN', 'FGFR4'], $r['genes']);
        $this->assertSame([['P1', 'S1', 2, null, null]], $r['rows']);
        $this->assertSame(2, $r['total_rows']);
        $this->assertSame(1, $r['next_offset']);
        $this->assertSame(1.0, $r['statistics']['ALK']['mean']);
        $this->assertSame(1, $r['statistics']['MYCN']['sample_count']);
        $this->assertSame(0, $r['statistics']['FGFR4']['sample_count']);
        $this->assertNull($r['statistics']['FGFR4']['mean']);
    }

    public function test_diagnosis_summary_has_no_rows_and_keeps_each_gene_statistics(): void
    {
        $r = $this->tool()->handle(new Request(['cohort_type' => 'cancer_type', 'cohort_id' => 'RMS',
            'genes' => ['ALK', 'MYCN'], 'output' => ['mode' => 'summary']]))->getStructuredContent();
        $this->assertSame('success', $r['status']);
        $this->assertArrayNotHasKey('rows', $r);
        $this->assertSame(0.0, $r['statistics']['ALK']['min']);
    }

    public function test_invalid_genes_and_unauthorized_cohorts_never_fetch_data(): void
    {
        foreach ([['cohort_id' => 999], ['genes' => ['BAD']], ['plot_type' => 'violin']] as $override) {
            $tool = $this->tool();
            $r = $tool->handle(new Request(array_merge(['cohort_type' => 'project', 'cohort_id' => 42,
                'genes' => ['ALK']], $override)))->getStructuredContent();
            $this->assertSame('error', $r['status']);
            $this->assertSame(0, $tool->reads);
        }
    }

    public function test_gene_and_genes_are_mutually_exclusive(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool()->handle(new Request(['cohort_type' => 'project', 'cohort_id' => 42,
            'gene' => 'ALK', 'genes' => ['MYCN']]));
    }
}
