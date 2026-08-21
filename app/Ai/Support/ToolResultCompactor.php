<?php

namespace App\Ai\Support;

class ToolResultCompactor
{
    public function __construct(
        private readonly int $maxRows = 75,
        private readonly int $maxCharacters = 30000,
    ) {}

    /** @param array<string, mixed> $result @return array<string, mixed> */
    public function compact(array $result): array
    {
        $resolverResult = $this->compactResolverResult($result);
        if ($resolverResult !== null) {
            return $resolverResult;
        }

        $expressionResult = $this->compactExpressionResult($result);
        if ($expressionResult !== null) {
            return $expressionResult;
        }

        $compacted = $this->compactValue($result, 0);

        if (isset($result['table_json']) && is_string($result['table_json'])) {
            $table = json_decode($result['table_json'], true);
            if (is_array($table)) {
                $rows = is_array($table['data'] ?? null) ? $table['data'] : [];
                $columns = $table['cols'] ?? $table['columns'] ?? [];
                unset($compacted['table_json']);
                $compacted['table_preview'] = [
                    'columns' => $columns,
                    'row_count' => count($rows),
                    'rows' => array_slice($rows, 0, $this->maxRows),
                    'truncated' => count($rows) > $this->maxRows,
                ];
            }
        }

        $json = $this->encode($compacted);
        if (strlen($json) <= $this->maxCharacters) {
            return $compacted;
        }

        return array_filter([
            'status' => $result['status'] ?? 'success',
            'action' => $result['action'] ?? null,
            'title' => $result['title'] ?? null,
            'summary' => $result['summary'] ?? null,
            'count' => $result['count'] ?? $result['row_count'] ?? null,
            'available_fields' => array_keys($result),
            'notice' => 'The result exceeded the agent context limit. Use its summary/count or call a narrower tool query.',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @param array<string, mixed> $result @return array<string, mixed>|null */
    private function compactResolverResult(array $result): ?array
    {
        $action = strtolower(trim((string) ($result['action'] ?? '')));

        if ($action === 'getcancertypes' && is_array($result['cancer_types'] ?? null)) {
            return [
                'status' => $result['status'] ?? 'success',
                'action' => $result['action'],
                'cancer_type_count' => $result['cancer_type_count'] ?? count($result['cancer_types']),
                'cancer_types' => array_values(array_filter(array_map(
                    static fn (mixed $item): string => trim((string) (is_array($item) ? ($item['diagnosis'] ?? '') : $item)),
                    $result['cancer_types']
                ))),
                'summary' => $result['summary'] ?? null,
            ];
        }

        if ($action === 'getprojects' && is_array($result['projects'] ?? null)) {
            return [
                'status' => $result['status'] ?? 'success',
                'action' => $result['action'],
                'project_count' => $result['project_count'] ?? count($result['projects']),
                'projects' => array_values(array_map(static fn (mixed $item): array => [
                    'project_id' => is_array($item) ? ($item['project_id'] ?? null) : null,
                    'project_name' => is_array($item) ? ($item['project_name'] ?? null) : null,
                ], $result['projects'])),
                'summary' => $result['summary'] ?? null,
            ];
        }

        return null;
    }

    /** @param array<string, mixed> $result @return array<string, mixed>|null */
    private function compactExpressionResult(array $result): ?array
    {
        if (strtolower(trim((string) ($result['action'] ?? ''))) !== 'expression_by_gene'
            || ! is_array($result['plot_rows'] ?? null)) {
            return null;
        }

        $transform = strtolower(trim((string) ($result['transform'] ?? 'none')));
        if (! in_array($transform, ['none', 'log2p1', 'zscore'], true)) {
            $transform = 'none';
        }

        $rawGroups = [];
        $allValues = [];
        foreach ($result['plot_rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $value = $row['raw_expression'] ?? $row['expression'] ?? null;
            if (! is_numeric($value)) {
                continue;
            }
            $value = (float) $value;
            if (! is_finite($value) || ($transform === 'log2p1' && $value < 0)) {
                continue;
            }

            $group = $this->expressionGroupName($row);
            $rawGroups[$group][] = $value;
            $allValues[] = $value;
        }
        if ($allValues === []) {
            return null;
        }

        $mean = array_sum($allValues) / count($allValues);
        $standardDeviation = 0.0;
        if ($transform === 'zscore') {
            $sum = 0.0;
            foreach ($allValues as $value) {
                $sum += ($value - $mean) ** 2;
            }
            $standardDeviation = sqrt($sum / count($allValues));
        }

        $groups = [];
        foreach ($rawGroups as $group => $values) {
            foreach ($values as $value) {
                $transformed = match ($transform) {
                    'log2p1' => log($value + 1, 2),
                    'zscore' => $standardDeviation > 0 ? ($value - $mean) / $standardDeviation : 0.0,
                    default => $value,
                };
                if (is_finite($transformed)) {
                    $groups[$group][] = $transformed;
                }
            }
        }
        $groups = array_filter($groups, static fn (array $values): bool => $values !== []);
        if ($groups === []) {
            return null;
        }

        $medians = [];
        foreach ($groups as $group => $values) {
            $medians[$group] = $this->median($values);
        }
        $groupOrder = strtolower(trim((string) ($result['group_order'] ?? 'none')));
        $groupNames = array_keys($groups);
        if (in_array($groupOrder, ['median_asc', 'median_desc'], true)) {
            usort($groupNames, static function (string $left, string $right) use ($medians, $groupOrder): int {
                $comparison = $groupOrder === 'median_desc'
                    ? $medians[$right] <=> $medians[$left]
                    : $medians[$left] <=> $medians[$right];

                return $comparison !== 0 ? $comparison : strcasecmp($left, $right);
            });
        } else {
            $groupOrder = 'none';
        }

        $rowCount = count($groupNames);
        $displayedNames = array_slice($groupNames, 0, $this->maxRows);
        $rows = [];
        foreach ($displayedNames as $group) {
            $values = $groups[$group];
            $rows[] = [
                $group,
                count($values),
                round(array_sum($values) / count($values), 4),
                round($medians[$group], 4),
                round(min($values), 4),
                round(max($values), 4),
            ];
        }

        $gene = $this->expressionText($result['gene'] ?? 'Gene', 100) ?: 'Gene';
        $valueType = strtolower($this->expressionText($result['value_type'] ?? '', 40));
        $baseValueLabel = match ($valueType) {
            'tmm-rpkm' => 'TMM-RPKM',
            'tpm' => 'TPM',
            'rpkm' => 'RPKM',
            'fpkm' => 'FPKM',
            default => 'expression',
        };
        $valueLabel = match ($transform) {
            'log2p1' => 'log2('.$baseValueLabel.' + 1)',
            'zscore' => 'z-score '.$baseValueLabel,
            default => $baseValueLabel,
        };
        $summary = sprintf(
            '%s expression summary across %d group%s using %d valid %s value%s.',
            $gene,
            $rowCount,
            $rowCount === 1 ? '' : 's',
            count($allValues),
            $valueLabel,
            count($allValues) === 1 ? '' : 's',
        );
        if ($rowCount > count($displayedNames)) {
            $summary .= sprintf(' The table shows the first %d groups.', count($displayedNames));
        }

        return array_filter([
            'status' => $result['status'] ?? 'success',
            'action' => $result['action'],
            'project_id' => $result['project_id'] ?? null,
            'project_name' => $result['project_name'] ?? null,
            'gene' => $gene,
            'value_type' => $result['value_type'] ?? null,
            'dataset_scope' => $result['dataset_scope'] ?? null,
            'plot_type' => $result['plot_type'] ?? null,
            'group_by' => $result['group_by'] ?? null,
            'transform' => $transform,
            'group_order' => $groupOrder,
            'summary' => $summary,
            'expression_summary' => [
                'columns' => ['Group', 'Samples', 'Mean', 'Median', 'Minimum', 'Maximum'],
                'rows' => $rows,
                'row_count' => $rowCount,
                'truncated' => $rowCount > count($rows),
                'value_label' => $valueLabel,
            ],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @param array<string, mixed> $row */
    private function expressionGroupName(array $row): string
    {
        $dataset = $this->expressionText($row['dataset'] ?? '', 100);
        $metadataField = $this->expressionText($row['metadata_field'] ?? '', 100);
        $metadataValue = $this->expressionText($row['metadata_value'] ?? '', 100);
        $group = $metadataField === ''
            && strcasecmp($metadataValue, 'All') === 0
            && $dataset !== ''
                ? ucfirst($dataset)
                : $this->expressionText($row['group'] ?? 'N/A', 120);

        return $group !== '' ? $group : 'N/A';
    }

    /** @param array<int, float> $values */
    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }

    private function expressionText(mixed $value, int $limit): string
    {
        $text = strip_tags((string) $value);
        $text = (string) preg_replace('/[\x00-\x1F\x7F<>]/u', ' ', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_substr($text, 0, $limit);
    }

    private function compactValue(mixed $value, int $depth): mixed
    {
        if ($depth > 8) {
            return '[nested data omitted]';
        }

        if (is_string($value)) {
            return strlen($value) > 4000 ? substr($value, 0, 4000).'… [truncated]' : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $items = array_is_list($value) && count($value) > $this->maxRows
            ? array_slice($value, 0, $this->maxRows)
            : $value;
        $compacted = [];
        foreach ($items as $key => $item) {
            $compacted[$key] = $this->compactValue($item, $depth + 1);
        }
        if (array_is_list($value) && count($value) > $this->maxRows) {
            $compacted[] = '[truncated; total items: '.count($value).']';
        }

        return $compacted;
    }

    /** @param array<string, mixed> $value */
    public function encode(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
