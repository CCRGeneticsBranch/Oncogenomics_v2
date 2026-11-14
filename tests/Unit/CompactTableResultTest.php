<?php

namespace Tests\Unit;

use App\Mcp\Support\CompactTableResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CompactTableResultTest extends TestCase
{
    private function payload(): array
    {
        return ['status' => 'success', 'action' => 'example', 'statistics' => ['mean' => 2],
            'samples' => [['patient_id' => 'P1'], ['patient_id' => 'P1']],
            'table_json' => json_encode(['cols' => [['title' => 'Patient ID'], ['title' => 'TPM']],
                'data' => [['P1', 1], ['P1', 3]]])];
    }

    public function test_legacy_and_error_payloads_remain_unchanged(): void
    {
        $this->assertSame($this->payload(), CompactTableResult::apply($this->payload(), null));
        $error = ['status' => 'error', 'message' => 'Access denied'];
        $this->assertSame($error, CompactTableResult::apply($error, ['mode' => 'summary']));
    }

    public function test_pagination_projection_and_statistics_have_explicit_scope(): void
    {
        $result = CompactTableResult::apply($this->payload(), ['limit' => 1, 'fields' => ['TPM']]);
        $this->assertSame([[1]], $result['rows']);
        $this->assertSame(['TPM'], $result['columns']);
        $this->assertSame(2, $result['total_rows']);
        $this->assertSame('table_rows', $result['row_count_unit']);
        $this->assertSame(['mean' => 2], $result['statistics']);
        $this->assertSame('full_filtered_result', $result['count_scope']);
        $this->assertSame(1, $result['next_offset']);
        $this->assertArrayNotHasKey('samples', $result);
        $this->assertArrayNotHasKey('table_json', $result);
        $pastEnd = CompactTableResult::apply($this->payload(), ['offset' => 10]);
        $this->assertSame([], $pastEnd['rows']);
        $this->assertNull($pastEnd['next_offset']);
    }

    public function test_summary_counts_all_rows_without_sending_them(): void
    {
        $result = CompactTableResult::apply($this->payload(), ['mode' => 'summary', 'offset' => 1, 'limit' => 1]);
        $this->assertSame(2, $result['total_rows']);
        $this->assertSame(0, $result['returned_rows']);
        $this->assertArrayNotHasKey('rows', $result);
        $this->assertArrayNotHasKey('samples', $result);
        $this->assertArrayNotHasKey('table_json', $result);
    }

    public function test_invalid_options_are_rejected(): void
    {
        foreach ([['limit' => 0], ['limit' => 1001], ['limit' => '1'], ['offset' => -1],
            ['mode' => 'bad'], ['fields' => []], ['fields' => ['missing']],
            ['fields' => ['TPM', 'TPM']], ['fields' => 'TPM'], ['unknown' => true], 'bad'] as $options) {
            try {
                CompactTableResult::apply($this->payload(), $options);
                $this->fail('Invalid options were accepted: '.json_encode($options));
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_grouped_results_and_nested_qc_are_paginated_as_one_table(): void
    {
        $mutation = CompactTableResult::apply(['status' => 'success', 'gene_count' => 1,
            'display_type' => 'tiered_mutation_gene_summary',
            'data' => ['somatic' => ['Tier 1' => [['TP53', 5]], 'Tier 2' => [['TP53', 2]]]]],
            ['mode' => 'summary']);
        $this->assertSame(2, $mutation['total_rows']);
        $this->assertSame(1, $mutation['gene_count']);
        $this->assertArrayNotHasKey('data', $mutation);
        $expression = CompactTableResult::apply(['status' => 'success',
            'display_type' => 'grouped_expression_data',
            'data' => ['A' => [['P1', 'S1', 10]], 'B' => [['P2', 'S2', 20]]]], ['offset' => 1]);
        $this->assertSame([['B', 'P2', 'S2', 20]], $expression['rows']);
        $qc = CompactTableResult::apply(['status' => 'success', 'table_json' => json_encode([
            'qc_data' => json_decode($this->payload()['table_json'], true), 'other_data' => ['large']])], []);
        $this->assertSame([['P1', 1], ['P1', 3]], $qc['rows']);
    }

    public function test_default_page_bounds_large_results_and_reduces_serialized_size(): void
    {
        $payload = $this->payload();
        $table = json_decode($payload['table_json'], true);
        $table['data'] = array_fill(0, 500, ['P1', 3]);
        $payload['table_json'] = json_encode($table);
        $payload['samples'] = $table['data'];
        $compact = CompactTableResult::apply($payload, []);
        $this->assertCount(100, $compact['rows']);
        $this->assertSame(500, $compact['total_rows']);
        $this->assertLessThan(strlen(json_encode($payload)) / 4, strlen(json_encode($compact)));
    }

    public function test_public_mcp_schema_serializes_nested_output_options(): void
    {
        $definition = (new \App\Mcp\Tools\GetProjectsTool)->toArray();
        $output = $definition['inputSchema']['properties']['output'];
        $this->assertSame(['compact', 'summary'], $output['properties']['mode']['enum']);
        $this->assertSame(1000, $output['properties']['limit']['maximum']);
        $this->assertSame('string', $output['properties']['fields']['items']['type']);
        $this->assertFalse($output['additionalProperties']);
    }
}
