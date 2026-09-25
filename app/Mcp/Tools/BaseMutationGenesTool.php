<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tool;

abstract class BaseMutationGenesTool extends LegacySchemaTool
{
    protected const REQUEST_TYPES = ['germline', 'somatic', 'ranseq', 'variants'];

    protected const TIERS = ['Tier 1', 'Tier 2', 'Tier 3', 'Tier 4', 'No Tier'];

    protected function internalType(string $type): string
    {
        if ($type === 'ranseq') {
            return 'rnaseq';
        }

        return $type;
    }

    /**
     * Group distinct-patient counts by tier importance and mutation context.
     *
     * @param  array<int, object>  $rows
     * @return array{data: array<string, array<string, array<int, array{0: string, 1: int}>>>, gene_count: int}
     */
    protected function summarizeRows(array $rows, string $requestedType): array
    {
        $contexts = match ($requestedType) {
            'germline' => ['germline'],
            'somatic' => ['somatic'],
            default => ['germline', 'somatic'],
        };

        $counts = [];
        foreach ($contexts as $context) {
            foreach (self::TIERS as $tier) {
                $counts[$context][$tier] = [];
            }
        }

        $genes = [];
        foreach ($rows as $row) {
            $context = strtolower(trim((string) ($row->tier_type ?? '')));
            $gene = strtoupper(trim((string) ($row->gene ?? '')));

            if (!in_array($context, $contexts, true) || $gene === '') {
                continue;
            }

            $tier = $this->normalizeTier($row->tier ?? null);
            $count = (int) ($row->cnt ?? 0);
            $counts[$context][$tier][$gene] = ($counts[$context][$tier][$gene] ?? 0) + $count;
            $genes[$gene] = true;
        }

        $data = [];
        foreach ($contexts as $context) {
            foreach (self::TIERS as $tier) {
                $entries = [];
                foreach ($counts[$context][$tier] as $gene => $count) {
                    $entries[] = [$gene, $count];
                }

                usort($entries, static function (array $left, array $right): int {
                    $countComparison = $right[1] <=> $left[1];

                    return $countComparison !== 0
                        ? $countComparison
                        : strcasecmp($left[0], $right[0]);
                });

                $data[$context][$tier] = $entries;
            }
        }

        return [
            'data' => $data,
            'gene_count' => count($genes),
        ];
    }

    protected function normalizeTier($tier): string
    {
        $value = strtolower(trim((string) $tier));

        foreach (['Tier 1', 'Tier 2', 'Tier 3', 'Tier 4'] as $knownTier) {
            if (strpos($value, strtolower($knownTier)) === 0) {
                return $knownTier;
            }
        }

        return 'No Tier';
    }

    protected function typeSchema(): array
    {
        return [
            'type' => 'string',
            'enum' => self::REQUEST_TYPES,
            'description' => 'Mutation source: germline; somatic for paired tumor/normal calls; ranseq; or variants for tumor-only calls.',
        ];
    }
}
