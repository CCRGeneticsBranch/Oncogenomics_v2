<?php

namespace Tests\Unit;

use App\Services\CohortAnalysisService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CohortAnalysisServiceTest extends TestCase
{
    #[DataProvider('callerValueProvider')]
    public function test_it_extracts_caller_keys($value, array $expected): void
    {
        $service = new CohortAnalysisService();

        $this->assertSame($expected, $service->callerKeysFromValue($value));
    }

    public static function callerValueProvider(): array
    {
        return [
            'list of caller objects' => [
                '[{"Arriba":"12:4:19"},{"FusionCatcher":"24"},{"STAR-fusion":"47"}]',
                ['Arriba', 'FusionCatcher', 'STAR-fusion'],
            ],
            'single caller object' => ['{"STAR-SEQR":"102"}', ['STAR-SEQR']],
            'duplicate caller keys' => ['[{"Arriba":"1"},{"Arriba":"2"}]', ['Arriba']],
            'legacy scalar caller' => ['Arriba', ['Arriba']],
            'empty value' => ['', []],
            'null value' => [null, []],
        ];
    }

    public function test_it_filters_fusion_rows_by_exact_caller_key_case_insensitively(): void
    {
        $service = new CohortAnalysisService();
        $rows = [
            (object) [
                'sample_id' => 'S1',
                'tool' => '[{"Arriba":"12:4:19"},{"FusionCatcher":"24"}]',
            ],
            (object) [
                'sample_id' => 'S2',
                'tool' => '{"STAR-SEQR":"102"}',
            ],
            [
                'sample_id' => 'S3',
                'tool' => 'Arriba',
            ],
            (object) [
                'sample_id' => 'S4',
                'tool' => '[{"NotArriba":"1"}]',
            ],
        ];

        $filtered = $service->filterFusionRowsByCaller($rows, 'arriba');

        $this->assertCount(2, $filtered);
        $this->assertSame('S1', $filtered[0]->sample_id);
        $this->assertSame('S3', $filtered[1]['sample_id']);
    }

    public function test_an_empty_caller_keeps_all_fusion_rows(): void
    {
        $service = new CohortAnalysisService();
        $rows = [(object) ['tool' => null], (object) ['tool' => 'Arriba']];

        $this->assertSame($rows, $service->filterFusionRowsByCaller($rows, null));
    }
}
