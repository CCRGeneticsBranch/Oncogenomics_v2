<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\CancerTypeController;
use App\Models\CancerType;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetFusionCancerTypeDetailTool extends Tool
{
    protected string $name = 'getFusionCancerTypeDetail';

    protected string $description = <<<'MARKDOWN'
        Return every fusion pair for one authorized cancer type, including the
        left gene, right gene, tier, and distinct-patient count. The
        cancer_type_id must exactly match a Cancer Type value returned by
        getCancerTypes. Use getCancerTypes first when the diagnosis is unknown
        or ambiguous.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'cancer_type_id' => 'required|string|max:200',
        ]);

        try {
            $cancerTypeId = trim((string) $validated['cancer_type_id']);

            if (!$this->isAvailableCancerType($cancerTypeId)) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Cancer type {$cancerTypeId} is not an exact Cancer Type value available from getCancerTypes.",
                ]);
            }

            $result = app(CancerTypeController::class)->getFusionCancerTypeDetail(
                $cancerTypeId,
                null,
                1,
                'json',
                'Y'
            );
            $table = json_decode($this->normalizeToJson($result), true);

            if (!is_array($table) || !isset($table['data']) || !is_array($table['data'])) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => 'Fusion cancer-type detail could not be serialized as a table.',
                ]);
            }

            $fusionPairs = [];
            foreach ($table['data'] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $tierValue = trim((string) ($row[4] ?? ''));
                $fusionPairs[] = [
                    'left_gene' => trim((string) ($row[1] ?? '')),
                    'right_gene' => trim((string) ($row[3] ?? '')),
                    'tier' => $tierValue === '' ? null : 'Tier '.$tierValue,
                    'patient_count' => $this->numericValue($row[5] ?? 0),
                ];
            }

            usort($fusionPairs, static function (array $left, array $right): int {
                return ($right['patient_count'] <=> $left['patient_count'])
                    ?: strcasecmp($left['left_gene'], $right['left_gene'])
                    ?: strcasecmp($left['right_gene'], $right['right_gene'])
                    ?: strcasecmp((string) $left['tier'], (string) $right['tier']);
            });

            $table = [
                'cols' => [
                    ['title' => 'Left Gene'],
                    ['title' => 'Right Gene'],
                    ['title' => 'Tier'],
                    ['title' => 'Patient Count'],
                ],
                'data' => array_map(static fn (array $pair): array => [
                    $pair['left_gene'],
                    $pair['right_gene'],
                    $pair['tier'],
                    $pair['patient_count'],
                ], $fusionPairs),
            ];
            $count = count($fusionPairs);

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'cancer_type_id' => $cancerTypeId,
                'fusion_pairs' => $fusionPairs,
                'fusion_pair_count' => $count,
                'count_unit' => 'distinct_patients',
                'data_type' => 'table',
                'display_type' => 'table',
                'table_json' => (string) json_encode($table, JSON_UNESCAPED_SLASHES),
                'title' => 'Fusion Cancer Type Detail',
                'summary' => $count === 1
                    ? "1 fusion pair found for {$cancerTypeId}."
                    : "{$count} fusion pairs found for {$cancerTypeId}.",
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function isAvailableCancerType(string $cancerTypeId): bool
    {
        [, $rows] = CancerType::getAll();

        foreach ($rows as $row) {
            if ((string) ($row[0] ?? '') === $cancerTypeId) {
                return true;
            }
        }

        return false;
    }

    protected function numericValue($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5);
        $text = str_replace(',', '', trim($text));

        return is_numeric($text) ? (int) $text : 0;
    }

    protected function normalizeToJson($result): string
    {
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return (string) json_encode($result->getData(true), JSON_UNESCAPED_SLASHES);
        }

        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return (string) $result->getContent();
        }

        return is_string($result)
            ? $result
            : (string) json_encode($result, JSON_UNESCAPED_SLASHES);
    }

    public function schema($schema = null): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cancer_type_id' => [
                    'type' => 'string',
                    'description' => 'Exact Cancer Type value returned by getCancerTypes.',
                ],
            ],
            'required' => ['cancer_type_id'],
            'additionalProperties' => false,
        ];
    }
}
