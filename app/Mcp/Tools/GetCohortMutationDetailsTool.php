<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\CompactTableResult;
use App\Mcp\Tools\Concerns\ResolvesCohortInput;
use App\Models\CancerType;
use App\Models\User;
use App\Services\CohortMutationDetailsService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class GetCohortMutationDetailsTool extends LegacySchemaTool
{
    use ResolvesCohortInput;

    protected bool $supportsCompactOutput = true;
    protected string $name = 'getCohortMutationDetails';
    protected string $description = 'Return annotated sample-level variants for one gene in an authorized project or exact diagnosis. Includes coordinates, consequence, protein change, VAF and both tiers; not limited to pathogenic variants. Database pages default to 100 rows; output.mode=summary returns full filtered counts. Missing annotations are null.';

    public function handle(Request $request): ResponseFactory
    {
        $input = $request->validate([
            'cohort_type' => 'required|in:project,cancer_type', 'cohort_id' => 'required',
            'gene' => 'required|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'type' => 'required|in:germline,somatic,rnaseq,variants',
            'genome_version' => 'nullable|in:hg19,hg38', 'sample_id' => 'nullable|string|max:200',
            'tier' => 'nullable|string|in:1,2,3,4', 'tier_context' => 'nullable|in:germline,somatic',
            'min_vaf' => 'nullable|numeric|min:0|max:1',
            'limit' => 'sometimes|integer|min:1|max:1000|prohibits:output.limit',
            'offset' => 'sometimes|integer|min:0|prohibits:output.offset',
            'output' => 'nullable|array:mode,limit,offset,fields',
            'output.mode' => 'sometimes|in:compact,summary',
            'output.limit' => 'sometimes|integer|min:1|max:1000',
            'output.offset' => 'sometimes|integer|min:0',
            'output.fields' => 'sometimes|array|min:1', 'output.fields.*' => 'string|distinct',
        ]);
        try {
            [$id, $error] = $this->resolveCohortId($input['cohort_type'], $input['cohort_id'], $this->name);
            if ($error !== null) return $error;
            if ($input['cohort_type'] === 'cancer_type' && !$this->isAvailableCancerType((string) $id)) {
                throw new \InvalidArgumentException('Diagnosis must exactly match an authorized Cancer Type from getCancerTypes.');
            }
            $options = $input['output'] ?? null;
            $limit = (int) ($options['limit'] ?? $input['limit'] ?? 100);
            $offset = (int) ($options['offset'] ?? $input['offset'] ?? 0);
            $summaryOnly = ($options['mode'] ?? '') === 'summary';
            $input['gene'] = strtoupper(trim($input['gene']));
            $data = $this->mutationDetails($input['cohort_type'], $id, $input, $limit, $offset, $summaryOnly);
            $fields = ['patient_id', 'case_id', 'sample_id', 'gene', 'chromosome', 'start_pos', 'end_pos',
                'ref', 'alt', 'genome_version', 'type', 'consequence', 'protein_change', 'vaf',
                'total_cov', 'var_cov', 'germline_tier', 'somatic_tier'];
            $titles = ['Patient ID', 'Case ID', 'Sample ID', 'Gene', 'Chromosome', 'Start', 'End', 'Ref', 'Alt',
                'Genome', 'Type', 'Consequence', 'Protein Change', 'VAF', 'Total Coverage', 'Variant Coverage',
                'Germline Tier', 'Somatic Tier'];
            $table = ['cols' => array_map(static fn ($t) => ['title' => $t], $titles),
                'data' => array_map(static fn ($row) => array_map(static fn ($f) => $row[$f] ?? null, $fields), $data['rows'])];
            $content = ['status' => 'success', 'action' => $this->name, 'cohort_type' => $input['cohort_type'],
                'cohort_id' => $id, 'gene' => $input['gene'], 'type' => $input['type'],
                'genome_version' => $input['genome_version'] ?? 'hg19',
                'tier_context' => $input['tier_context'] ?? ($input['type'] === 'germline' ? 'germline' : 'somatic'),
                'tier' => $input['tier'] ?? null, 'min_vaf' => $input['min_vaf'] ?? null,
                'sample_id' => $input['sample_id'] ?? null, 'vaf_unit' => 'fraction',
                'patient_count' => $data['patient_count'], 'sample_count' => $data['sample_count'],
                'count_scope' => 'full_filtered_result', 'count_unit' => 'annotated_variant_records',
                'table_json' => json_encode($table, JSON_UNESCAPED_SLASHES),
                'data_type' => 'table', 'display_type' => 'table', 'title' => 'Mutation Details',
                'summary' => $data['total_rows'].' annotated variant records; '.$data['patient_count'].' distinct patients.'];
            if ($options !== null) {
                // SQL has already paginated. Project fields once without applying
                // the requested offset a second time, then restore full counts.
                $content = CompactTableResult::apply($content, array_merge($options, ['offset' => 0, 'limit' => $limit]));
            }
            $content['total_rows'] = $data['total_rows'];
            $content['returned_rows'] = count($data['rows']);
            $content['offset'] = $offset;
            $content['limit'] = $limit;
            $content['next_offset'] = !$summaryOnly && $offset + count($data['rows']) < $data['total_rows']
                ? $offset + count($data['rows']) : null;
            return Response::structured($content);
        } catch (\Throwable $e) {
            return Response::structured(['status' => 'error', 'action' => $this->name, 'message' => $e->getMessage()]);
        }
    }

    protected function isAvailableCancerType(string $id): bool
    {
        [, $rows] = CancerType::getAll();
        foreach ($rows as $row) if ((string) ($row[0] ?? '') === $id) return true;
        return false;
    }

    protected function mutationDetails(string $type, int|string $id, array $filters, int $limit, int $offset, bool $summary): array
    {
        $user = User::getCurrentUser();
        if ($user === null) throw new \RuntimeException('An authenticated MCP user is required.');
        return app(CohortMutationDetailsService::class)->fetch((int) $user->id, $type, $id, $filters, $limit, $offset, $summary);
    }

    protected function legacySchema(): array
    {
        return ['type' => 'object', 'properties' => array_merge($this->cohortProperties(), [
            'gene' => ['type' => 'string', 'description' => 'Exact gene symbol.'],
            'type' => ['type' => 'string', 'enum' => ['germline', 'somatic', 'rnaseq', 'variants']],
            'genome_version' => ['type' => 'string', 'enum' => ['hg19', 'hg38'], 'description' => 'Defaults to hg19.'],
            'sample_id' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'description' => 'Page size; default 100. Do not also set output.limit.'],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Page offset; default 0. Do not also set output.offset.'],
            'tier' => ['type' => 'string', 'enum' => ['1', '2', '3', '4']],
            'tier_context' => ['type' => 'string', 'enum' => ['germline', 'somatic'],
                'description' => 'Tier filter context; defaults to germline for germline calls, otherwise somatic.'],
            'min_vaf' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
        ]), 'required' => ['cohort_type', 'cohort_id', 'gene', 'type'], 'additionalProperties' => false];
    }
}
