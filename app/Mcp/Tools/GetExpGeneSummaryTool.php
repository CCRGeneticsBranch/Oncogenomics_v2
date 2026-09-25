<?php

namespace App\Mcp\Tools;

use App\Models\Gene;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class GetExpGeneSummaryTool extends LegacySchemaTool
{
    protected string $name = 'getExpGeneSummary';

    protected string $description = <<<'MARKDOWN'
        Get TPM expression for a gene from every RNA-seq sample the authorized
        user can access. Results are grouped by diagnosis or project. Each
        group contains rows in the form [patient_id, sample_id, tpm]. Use this
        tool for cross-project or cross-diagnosis expression summaries rather
        than a single-project expression request.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'gene_id' => 'required|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
            'category' => 'required|string|in:diagnosis,project',
            'tissue' => 'required|string|in:all,tumor,normal',
            'genome_version' => 'nullable|string|in:hg19,hg38',
            'lib_type' => 'nullable|string|in:all,polyA,nonPolyA',
        ]);

        try {
            $genomeVersion = $validated['genome_version'] ?? 'hg19';
            $libType = $validated['lib_type'] ?? 'all';
            $requestedGene = strtoupper(trim((string) $validated['gene_id']));
            $gene = Gene::getGene($requestedGene, $genomeVersion);

            if ($gene === null) {
                return Response::structured([
                    'status' => 'error',
                    'action' => $this->name,
                    'message' => "Gene {$requestedGene} was not found for {$genomeVersion}.",
                ]);
            }

            $geneSymbol = $gene->getSymbol();
            $data = Gene::getExpGeneSummary(
                $geneSymbol,
                $validated['category'],
                $validated['tissue'],
                $genomeVersion,
                $libType
            );

            ksort($data, SORT_NATURAL | SORT_FLAG_CASE);
            $sampleCount = array_sum(array_map('count', $data));
            $groupCount = count($data);

            return Response::structured([
                'status' => 'success',
                'action' => $this->name,
                'gene_id' => $geneSymbol,
                'category' => $validated['category'],
                'tissue' => $validated['tissue'],
                'genome_version' => $genomeVersion,
                'lib_type' => $libType,
                'value_type' => 'tpm',
                'data' => $data,
                'group_count' => $groupCount,
                'sample_count' => $sampleCount,
                'display_type' => 'grouped_expression_data',
                'summary' => "{$sampleCount} sample(s) in {$groupCount} {$validated['category']} group(s).",
            ]);
        } catch (\Throwable $e) {
            return Response::structured([
                'status' => 'error',
                'action' => $this->name,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function legacySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'gene_id' => [
                    'type' => 'string',
                    'description' => 'Gene symbol, for example FGFR4.',
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => ['diagnosis', 'project'],
                    'description' => 'Field used to group the expression rows.',
                ],
                'tissue' => [
                    'type' => 'string',
                    'enum' => ['all', 'tumor', 'normal'],
                    'description' => 'Include all samples, tumor samples, or normal samples.',
                ],
                'genome_version' => [
                    'type' => ['string', 'null'],
                    'enum' => ['hg19', 'hg38', null],
                    'description' => 'Genome version. Defaults to hg19.',
                ],
                'lib_type' => [
                    'type' => ['string', 'null'],
                    'enum' => ['all', 'polyA', 'nonPolyA', null],
                    'description' => 'RNA library type. Defaults to all.',
                ],
            ],
            'required' => ['gene_id', 'category', 'tissue'],
            'additionalProperties' => false,
        ];
    }
}
