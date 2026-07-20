<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use App\Models\Project;
use App\Models\Gene;

class CorrelationByGeneTool extends Tool
{
    protected string $name = 'correlation_by_gene';

    protected string $description = <<<'MARKDOWN'
        Get gene correlation data for a specific gene in a project.
        Returns a table of genes correlated with the query gene,
        including correlation coefficients and p-values.
    MARKDOWN;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'gene' => 'required|string|max:100',
            'method' => 'nullable|string|in:pearson,spearman',
            'value_type' => 'nullable|string',
            'cutoff' => 'nullable|numeric',
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $gene = strtoupper(trim((string) $validated['gene']));
            $method = $validated['method'] ?? 'pearson';
            $valueType = $validated['value_type'] ?? 'tmm-rpkm';
            $cutoff = (float) ($validated['cutoff'] ?? 0.2);

            // Resolve gene symbol
            $geneObj = Gene::getGene($gene);
            if ($geneObj === null) {
                return Response::structured([
                    'status' => 'error',
                    'message' => "Gene $gene not found",
                    'action' => 'correlation_by_gene',
                ]);
            }

            $geneSymbol = $geneObj->getSymbol();
            $project = Project::getProject($projectId);
            if ($project === null) {
                return Response::structured([
                    'status' => 'error',
                    'message' => "Project $projectId not found",
                    'action' => 'correlation_by_gene',
                ]);
            }

            // Get correlation data - returns array($corr_p, $corr_n)
            list($corrPositive, $corrNegative) = $project->getCorrelation(
                $geneSymbol,
                $cutoff,
                'refseq',
                $method,
                $valueType
            ) ?? [[], []];

            // Merge and format data for DataTables
            $allCorrelations = [];
            $gene_infos = Gene::getGenesInfo();
            
            // Add positive correlations
            foreach ($corrPositive as $geneId => $coeff) {
                $symbol = $geneId;
                if (array_key_exists($geneId, $gene_infos)) {
                    $gene_info = $gene_infos[$geneId];
                    $symbol = $gene_info->symbol;
                }
                $allCorrelations[] = [$symbol, $geneId, $coeff, 'Positive'];
            }
            
            // Add negative correlations
            foreach ($corrNegative as $geneId => $coeff) {
                $symbol = $geneId;
                if (array_key_exists($geneId, $gene_infos)) {
                    $gene_info = $gene_infos[$geneId];
                    $symbol = $gene_info->symbol;
                }
                $allCorrelations[] = [$symbol, $geneId, $coeff, 'Negative'];
            }

            if (empty($allCorrelations)) {
                return Response::structured([
                    'status' => 'no_data',
                    'message' => "No correlation data available for gene $geneSymbol in project {$project->name}",
                    'action' => 'correlation_by_gene',
                    'project_id' => $projectId,
                    'gene' => $geneSymbol,
                ]);
            }

            // Sort by absolute correlation coefficient (column index 2)
            usort($allCorrelations, function ($a, $b) {
                $aVal = abs(floatval($a[2]));
                $bVal = abs(floatval($b[2]));
                return $bVal <=> $aVal; // Descending order
            });

            // Structure response for display in result panel
            return Response::structured([
                'status' => 'success',
                'action' => 'correlation_by_gene',
                'project_id' => $projectId,
                'gene' => $geneSymbol,
                'project_name' => $project->name,
                'method' => $method,
                'value_type' => $valueType,
                'cutoff' => $cutoff,
                'correlation_data' => $allCorrelations,
                'display_type' => 'correlation_table',
                'summary' => "Found " . count($allCorrelations) . " genes correlated with $geneSymbol using $method correlation (|r| > $cutoff)",
            ]);
        } catch (\Exception $e) {
            return Response::structured([
                'status' => 'error',
                'message' => $e->getMessage(),
                'action' => 'correlation_by_gene',
            ]);
        }
    }

    public function schema($schema = null): array
    {
        return [];
    }
}
