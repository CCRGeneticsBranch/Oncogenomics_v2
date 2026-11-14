<?php

namespace App\Mcp\Support;

use InvalidArgumentException;

/** Opt-in presentation only: authorization and domain filtering run in the tools. */
class CompactTableResult
{
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'description' => 'Compact: one page (100 rows default). Summary: counts only. Omit for UI output.',
            'properties' => [
                'mode' => ['type' => 'string', 'enum' => ['compact', 'summary']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                'offset' => ['type' => 'integer', 'minimum' => 0],
                'fields' => ['type' => 'array', 'minItems' => 1, 'uniqueItems' => true,
                    'items' => ['type' => 'string'], 'description' => 'Column titles; omit for all.'],
            ],
            'additionalProperties' => false,
        ];
    }

    public static function apply(array $content, mixed $options): array
    {
        if ($options === null || ($content['status'] ?? null) !== 'success') {
            return $content;
        }
        if (!is_array($options) || array_diff(array_keys($options), ['mode', 'limit', 'offset', 'fields'])) {
            throw new InvalidArgumentException('output must contain only mode, limit, offset, and fields.');
        }
        $mode = $options['mode'] ?? 'compact';
        $limit = $options['limit'] ?? 100;
        $offset = $options['offset'] ?? 0;
        if (!in_array($mode, ['compact', 'summary'], true)
            || !is_int($limit) || $limit < 1 || $limit > 1000
            || !is_int($offset) || $offset < 0) {
            throw new InvalidArgumentException('output requires mode compact/summary, limit 1–1000, and offset >= 0.');
        }

        $table = isset($content['table_json']) ? json_decode($content['table_json'], true) : null;
        if (isset($table['qc_data'])) {
            $table = $table['qc_data'];
        }
        if (($content['display_type'] ?? null) === 'correlation_table') {
            $table = ['cols' => ['Symbol', 'Gene', 'Coefficient', 'Positive/negative'],
                'data' => $content['correlation_data']];
        }
        if (($content['display_type'] ?? null) === 'grouped_expression_data') {
            $table = ['cols' => ['Group', 'Patient ID', 'Sample ID', 'TPM'], 'data' => []];
            foreach ($content['data'] as $group => $entries) {
                foreach ($entries as $entry) {
                    $table['data'][] = array_merge([(string) $group], array_values($entry));
                }
            }
        }
        if (($content['display_type'] ?? null) === 'tiered_mutation_gene_summary') {
            $table = ['cols' => ['Context', 'Tier', 'Gene', 'Patient Count'], 'data' => []];
            foreach ($content['data'] as $context => $tiers) {
                foreach ($tiers as $tier => $entries) {
                    foreach ($entries as [$gene, $count]) {
                        $table['data'][] = [$context, $tier, $gene, $count];
                    }
                }
            }
        }
        if (!is_array($table) || !isset($table['cols'], $table['data'])
            || !is_array($table['cols']) || !is_array($table['data'])) {
            throw new InvalidArgumentException('Compact output requires a tabular result; omit output for plots.');
        }
        $columns = array_map(static fn ($col): string => is_array($col)
            ? (string) ($col['title'] ?? $col['name'] ?? '') : (string) $col, $table['cols']);
        $indices = array_keys($columns);
        if (isset($options['fields'])) {
            if (!is_array($options['fields']) || !array_is_list($options['fields']) || $options['fields'] === []) {
                throw new InvalidArgumentException('output.fields must be a nonempty array of column titles.');
            }
            $indices = [];
            foreach ($options['fields'] as $field) {
                $index = array_search($field, $columns, true);
                if (!is_string($field) || $index === false || in_array($index, $indices, true)) {
                    throw new InvalidArgumentException('Unknown or duplicate output field. Available columns: '.implode(', ', $columns));
                }
                $indices[] = $index;
            }
        }
        $total = count($table['data']);
        $rows = $mode === 'summary' ? [] : array_slice(array_values($table['data']), $offset, $limit);
        $rows = array_map(static function (array $row) use ($indices): array {
            $values = array_values($row);
            return array_map(static fn ($i) => $values[$i] ?? null, $indices);
        }, $rows);

        // Preserve scalar context and full-result statistics, never duplicate datasets.
        $result = array_filter($content, static fn ($value) => is_scalar($value) || $value === null);
        foreach (['table_json', 'data_type', 'display_type'] as $key) {
            unset($result[$key]);
        }
        if (isset($content['genes'])) $result['genes'] = $content['genes'];
        if (isset($content['statistics'])) {
            $result['statistics'] = $content['statistics'];
        }
        $result['output_mode'] = $mode;
        $result['total_rows'] = $total;
        $result['count_scope'] = 'full_filtered_result';
        $result['row_count_unit'] = 'table_rows';
        $result['returned_rows'] = count($rows);
        if ($mode === 'compact') {
            $result['columns'] = array_map(static fn ($i) => $columns[$i], $indices);
            $result['rows'] = $rows;
            $result['offset'] = $offset;
            $result['limit'] = $limit;
            $result['next_offset'] = $offset + count($rows) < $total ? $offset + count($rows) : null;
        }
        return $result;
    }
}
