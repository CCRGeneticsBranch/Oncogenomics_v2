<?php

namespace App\Mcp\Tools;

use App\Services\DiagnosisFusionService;
use App\Models\User;
use App\Models\CancerType;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetFusionCancerTypeDetailTool extends LegacySchemaTool
{
    protected bool $supportsCompactOutput = true;

    protected string $name = 'getFusionCancerTypeDetail';

    protected string $description = <<<'MARKDOWN'
        Return fusion pairs, tiers, and distinct-patient counts for one authorized diagnosis. Filter gene on either side, partner in either orientation, caller, and tier. Counts are per pair/tier and cannot be added as unique patients. Resolve cancer_type_id with getCancerTypes.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'cancer_type_id' => 'required|string|max:200',
            'gene' => 'nullable|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'partner' => 'nullable|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'caller' => 'nullable|string|max:100',
            'tier' => 'nullable|string|in:1,2,3,4',
        ]);

        try {
            $cancerTypeId = trim((string) $validated['cancer_type_id']);

            if (!$this->isAvailableCancerType($cancerTypeId)) {
                return $this->resultResponse($request, [
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Cancer type {$cancerTypeId} is not an exact Cancer Type value available from getCancerTypes.",
                ]);
            }

            if (!empty($validated['partner']) && empty($validated['gene'])) {
                throw new \InvalidArgumentException('partner requires gene.');
            }
            $fusionPairs = array_map(static fn (array $row): array => [
                'left_gene' => trim((string) $row['left_gene']),
                'right_gene' => trim((string) $row['right_gene']),
                'tier' => empty($row['tier']) ? null : 'Tier '.$row['tier'],
                'patient_count' => (int) $row['patient_count'],
            ], $this->fusionPairs($cancerTypeId, $validated));

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

            return $this->resultResponse($request, [
                'status' => 'success',
                'action' => $this->name,
                'cancer_type_id' => $cancerTypeId,
                'fusion_pairs' => $fusionPairs,
                'gene' => $validated['gene'] ?? null,
                'partner' => $validated['partner'] ?? null,
                'caller' => $validated['caller'] ?? null,
                'tier' => $validated['tier'] ?? null,
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
            return $this->resultResponse($request, [
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function fusionPairs(string $diagnosis, array $filters): array
    {
        $user = User::getCurrentUser();
        if ($user === null) throw new \RuntimeException('An authenticated MCP user is required.');
        return app(DiagnosisFusionService::class)->pairs((int) $user->id, $diagnosis, $filters);
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

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'gene' => ['type' => 'string', 'description' => 'Exact gene symbol on either side.'],
                'partner' => ['type' => 'string', 'description' => 'Requires gene; match pair in either orientation.'],
                'caller' => ['type' => 'string', 'description' => 'Exact caller name, case-insensitive.'],
                'tier' => ['type' => 'string', 'enum' => ['1', '2', '3', '4']],
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
