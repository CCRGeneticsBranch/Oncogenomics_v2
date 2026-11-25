<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Models\VarAnnotation;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

/** Data behind viewGeneDetail, across the signed-in user's authorized cohorts. */
abstract class BaseGeneQueryTool extends LegacySchemaTool
{
    protected bool $supportsCompactOutput = true;
    protected bool $patientDetails = false;
    protected bool $fusion = false;
    protected array $argumentNames = [];

    public function handle(Request $request): ResponseFactory
    {
        $specs = $this->parameters();
        $validated = $request->validate(array_map(static fn ($spec) => $spec['rule'], $specs));
        $arguments = [];
        foreach ($specs as $name => $spec) {
            $arguments[$name] = $validated[$name] ?? $spec['default'] ?? null;
            if (in_array($name, ['gene_id', 'left_gene', 'right_gene'], true)) {
                $arguments[$name] = strtoupper(trim($arguments[$name]));
            }
        }
        if (isset($arguments['tiers'])) {
            $arguments['tiers'] = implode(',', array_map(function ($tier) {
                $tier = str_replace('Tier ', 'Tier', trim($tier));
                return $this->fusion ? $tier : preg_replace('/^Tier([1-4])$/', 'Tier $1', $tier);
            }, explode(',', $arguments['tiers'])));
        }
        if (isset($arguments['min_amplified']) && $arguments['min_amplified'] <= $arguments['max_deleted']) {
            return $this->resultResponse($request, ['status' => 'error', 'action' => $this->name,
                'message' => 'min_amplified must be greater than max_deleted.']);
        }
        if (!$this->isAuthenticated()) {
            return $this->resultResponse($request, ['status' => 'error', 'action' => $this->name,
                'message' => 'Sign in to query authorized gene data.']);
        }
        try {
            $data = $this->fetchData($arguments) ?? [];
            $table = $this->patientDetails ? [
                'cols' => $this->name === 'getPatientsByCNVGene'
                    ? ['Patient ID', 'Diagnosis', 'Type', 'Projects']
                    : ['Patient ID', 'Diagnosis', 'Projects'],
                'data' => $data,
            ] : $this->summaryTable($data, ($arguments['value_type'] ?? 'count') === 'frequency');
            return $this->resultResponse($request, array_merge($arguments, [
                'status' => 'success', 'action' => $this->name, 'scope' => 'all_authorized',
                'data' => $data, 'table_json' => json_encode($table), 'display_type' => 'table',
                'row_count_unit' => $this->patientDetails ? 'patient_group_rows' : 'summary_group_rows',
                'value_unit' => ($arguments['value_type'] ?? 'count') === 'frequency' ? 'percent' : 'patient_count',
                'count_note' => 'Groups and tiers can overlap; counts must not be summed as unique patients across groups.',
            ]));
        } catch (\Throwable $exception) {
            report($exception);
            return $this->resultResponse($request, ['status' => 'error', 'action' => $this->name,
                'message' => 'Unable to load authorized gene data.']);
        }
    }

    protected function isAuthenticated(): bool
    {
        return User::getCurrentUser() !== null;
    }

    protected function fetchData(array $arguments): ?array
    {
        return VarAnnotation::{$this->name}(...array_values($arguments));
    }

    private function summaryTable(array $data, bool $frequency = false): array
    {
        $rows = [];
        $contexts = $this->name === 'getVarGeneSummary' ? $data : ['all' => $data];
        foreach ($contexts as $context => $plot) {
            foreach ($plot['series'] ?? [] as $series) {
                foreach ($plot['category'] ?? [] as $index => $group) {
                    $point = $series['data'][$index] ?? 0;
                    $rows[] = [$context, $group, $series['name'], is_array($point) ? $point['y'] : $point];
                }
            }
        }
        return ['cols' => ['Context', 'Group', 'Series', $frequency ? 'Patient Percentage (%)' : 'Patient Count'], 'data' => $rows];
    }

    protected function legacySchema(): array
    {
        $properties = [];
        $required = [];
        foreach ($this->parameters() as $name => $spec) {
            $properties[$name] = $spec['schema'];
            if (in_array('required', is_array($spec['rule']) ? $spec['rule'] : explode('|', $spec['rule']), true)) $required[] = $name;
        }
        return ['type' => 'object', 'properties' => $properties,
            'required' => $required, 'additionalProperties' => false];
    }

    private function parameters(): array
    {
        $gene = ['rule' => 'required|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'schema' => ['type' => 'string', 'description' => 'Gene symbol, for example TP53.']];
        $enum = static fn (array $values, string $default, string $description) => [
            'rule' => 'sometimes|string|in:'.implode(',', $values), 'default' => $default,
            'schema' => ['type' => 'string', 'enum' => $values, 'default' => $default, 'description' => $description],
        ];
        $number = static fn (string $kind, $default, string $description) => [
            'rule' => 'sometimes|'.($kind === 'integer' ? 'integer' : 'numeric').'|min:0', 'default' => $default,
            'schema' => ['type' => $kind, 'minimum' => 0, 'default' => $default, 'description' => $description],
        ];
        $specs = [
            'gene_id' => $gene, 'left_gene' => $gene, 'right_gene' => $gene,
            'value_type' => $enum(['count', 'frequency'], 'count', 'Patient count, or percent of the group (frequency).'),
            'category' => $this->patientDetails ? [
                'rule' => 'nullable|string|max:255', 'default' => null,
                'schema' => ['type' => ['string', 'null'], 'description' => 'Optional exact group label from a gene summary. Omit to include every authorized group.'],
            ] : $enum(['diagnosis', 'project'], 'diagnosis', 'Grouping only; no cohort selection is required.'),
            'cat_type' => $enum(['diagnosis', 'project'], 'diagnosis', 'Kind of the optional category group filter.'),
            'min_pat' => $number('integer', 0, 'Minimum patient count per summary group.'),
            'tiers' => [
                'rule' => ['sometimes', 'string', 'max:100', $this->fusion
                    ? 'regex:/^Tier ?[1-4](?:,Tier ?[1-4])*$/'
                    : 'regex:/^(?:Tier ?[1-4]|No Tier)(?:,(?:Tier ?[1-4]|No Tier))*$/'],
                'default' => $this->fusion ? 'Tier1,Tier2' : 'Tier 1,Tier 2',
                'schema' => ['type' => 'string', 'description' => 'Comma-separated tiers 1–4 (Tier1 or Tier 1); default tiers 1 and 2. Mutation queries also accept No Tier.'],
            ],
            'type' => $enum(['somatic', 'germline'], 'somatic', 'Mutation category.'),
            'fusion_type' => $enum(['All', 'in-frame', 'out-of-frame', 'right gene intact'], 'All', 'Fusion reading-frame filter.'),
            'min_amplified' => $number('number', 3, 'Inclusive minimum copy number for Amplified.'),
            'max_deleted' => $number('number', 1, 'Inclusive maximum copy number for Deleted.'),
        ];
        return array_combine($this->argumentNames, array_map(static fn ($name) => $specs[$name], $this->argumentNames));
    }
}
