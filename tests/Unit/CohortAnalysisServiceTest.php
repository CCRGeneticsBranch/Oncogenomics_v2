<?php

namespace Tests\Unit;

use App\Services\CohortAnalysisService;
use PHPUnit\Framework\TestCase;

class CohortAnalysisServiceTest extends TestCase
{
    /** @dataProvider callerValueProvider */
    public function test_it_extracts_caller_keys($value, array $expected): void
    {
        $service = new CohortAnalysisService();

        $this->assertSame($expected, $service->callerKeysFromValue($value));
    }

    public function callerValueProvider(): array
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
}
