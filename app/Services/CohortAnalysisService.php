<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class CohortAnalysisService
{
    public function accessibleProject(int $projectId): ?Project
    {
        foreach (Project::getAll(false) as $project) {
            if ((int) $project->id === $projectId) {
                return Project::getProject($projectId);
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function diagnoses(int $projectId): array
    {
        return array_map(static fn ($row): array => [
            'diagnosis' => trim((string) $row->diagnosis),
            'rna_sample_count' => (int) $row->sample_count,
        ], DB::select(
            "select diagnosis, count(distinct sample_id) as sample_count
             from project_samples
             where project_id=? and exp_type='RNAseq' and diagnosis is not null
             group by diagnosis order by diagnosis",
            [$projectId]
        ));
    }

    /** @return array<int, string> */
    public function fusionCallers(int $projectId): array
    {
        $callers = [];
        $rows = DB::select(
            'select distinct f.tool from var_fusion f, project_samples p
             where p.project_id=? and p.sample_id=f.sample_id and f.tool is not null',
            [$projectId]
        );

        foreach ($rows as $row) {
            foreach ($this->callerKeysFromValue($row->tool ?? null) as $caller) {
                $callers[strtolower($caller)] = $caller;
            }
        }

        $callers = array_values($callers);
        natcasesort($callers);

        return array_values($callers);
    }

    /** @return array<int, string> */
    public function callerKeysFromValue($value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [$value];
        }

        $callers = [];
        $items = array_is_list($decoded) ? $decoded : [$decoded];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (array_keys($item) as $caller) {
                $caller = trim((string) $caller);
                if ($caller !== '') {
                    $callers[strtolower($caller)] = $caller;
                }
            }
        }

        return array_values($callers);
    }

    /** @return array<int, array{data_type: string, genome_version: ?string, path: string}> */
    public function expressionMatrices(int $projectId): array
    {
        $directory = storage_path("project_data/{$projectId}");
        $files = glob($directory.'/expression.count*.tsv') ?: [];
        sort($files);

        return array_map(static function (string $path): array {
            $name = basename($path);
            preg_match('/^expression\.count(?:\.([^.]+))?\.tsv$/', $name, $matches);

            return [
                'data_type' => 'count',
                'genome_version' => isset($matches[1]) ? $matches[1] : null,
                'path' => $name,
            ];
        }, $files);
    }

    /**
     * @param array{diagnosis?: ?string, left_gene: string, right_gene: string, caller?: ?string, fusion_status?: ?string} $cohort
     * @return array<int, array<string, string>>
     */
    public function fusionCohortSamples(int $projectId, array $cohort): array
    {
        $sampleSql = "select distinct p.patient_id, p.sample_id, p.sample_name,
                              p.sample_alias, p.rnaseq_sample
                      from project_samples p
                      where p.project_id=? and p.exp_type='RNAseq'";
        $sampleBindings = [$projectId];
        if (!empty($cohort['diagnosis'])) {
            $sampleSql .= ' and p.diagnosis=?';
            $sampleBindings[] = trim((string) $cohort['diagnosis']);
        }
        $sampleSql .= ' order by p.sample_id';

        $fusionSql = "select distinct p.sample_id, f.tool
                      from project_samples p, var_fusion f
                      where p.project_id=? and p.exp_type='RNAseq'
                        and p.sample_id=f.sample_id
                        and ((upper(f.left_gene)=? and upper(f.right_gene)=?)
                          or (upper(f.left_gene)=? and upper(f.right_gene)=?))";
        $left = strtoupper(trim($cohort['left_gene']));
        $right = strtoupper(trim($cohort['right_gene']));
        $fusionBindings = [$projectId, $left, $right, $right, $left];

        if (!empty($cohort['diagnosis'])) {
            $fusionSql .= ' and p.diagnosis=?';
            $fusionBindings[] = trim((string) $cohort['diagnosis']);
        }

        $requestedCaller = strtolower(trim((string) ($cohort['caller'] ?? '')));
        $fusionPositiveIds = [];
        foreach (DB::select($fusionSql, $fusionBindings) as $row) {
            if ($requestedCaller !== '') {
                $callerKeys = array_map('strtolower', $this->callerKeysFromValue($row->tool ?? null));
                if (!in_array($requestedCaller, $callerKeys, true)) {
                    continue;
                }
            }
            $fusionPositiveIds[trim((string) ($row->sample_id ?? ''))] = true;
        }

        $requestedStatus = strtolower(trim((string) ($cohort['fusion_status'] ?? 'positive')));
        $samples = [];
        foreach (DB::select($sampleSql, $sampleBindings) as $row) {
            $sampleId = trim((string) ($row->sample_id ?? ''));
            $isPositive = isset($fusionPositiveIds[$sampleId]);
            if (($requestedStatus === 'negative' && $isPositive)
                || ($requestedStatus !== 'negative' && !$isPositive)) {
                continue;
            }
            $samples[$sampleId] = [
                'patient_id' => trim((string) ($row->patient_id ?? '')),
                'sample_id' => $sampleId,
                'sample_name' => trim((string) ($row->sample_name ?? '')),
                'sample_alias' => trim((string) ($row->sample_alias ?? '')),
                'rnaseq_sample' => trim((string) ($row->rnaseq_sample ?? '')),
            ];
        }

        return array_values($samples);
    }

    public function countMatrixPath(int $projectId, ?string $genomeVersion): string
    {
        $directory = storage_path("project_data/{$projectId}");
        $candidates = [];
        if ($genomeVersion !== null && $genomeVersion !== '') {
            $candidates[] = $directory.'/expression.count.'.$genomeVersion.'.tsv';
        }
        $candidates[] = $directory.'/expression.count.tsv';

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No readable project-level count matrix was found. Expected '.implode(' or ', $candidates).'.');
    }

    /**
     * @param array<int, array<string, string>> $groupA
     * @param array<int, array<string, string>> $groupB
     * @return array<string, mixed>
     */
    public function runDifferentialExpression(string $matrixPath, array $groupA, array $groupB, float $alpha): array
    {
        $workDir = storage_path('app/mcp/cohort-analysis/'.bin2hex(random_bytes(12)));
        if (!mkdir($workDir, 0700, true) && !is_dir($workDir)) {
            throw new RuntimeException('Unable to create the differential-expression working directory.');
        }

        $manifest = $workDir.'/cohorts.tsv';
        $output = $workDir.'/results.tsv';
        $plot = $workDir.'/volcano.png';
        $sampleQc = $workDir.'/sample_qc.tsv';
        $handle = fopen($manifest, 'wb');
        if ($handle === false) {
            $this->removeDirectory($workDir);
            throw new RuntimeException('Unable to create the cohort manifest.');
        }
        fputcsv($handle, ['patient_id', 'sample_id', 'sample_name', 'sample_alias', 'rnaseq_sample', 'group'], "\t");
        foreach ([['samples' => $groupA, 'group' => 'group_a'], ['samples' => $groupB, 'group' => 'group_b']] as $set) {
            foreach ($set['samples'] as $sample) {
                fputcsv($handle, [
                    $sample['patient_id'] ?? '', $sample['sample_id'] ?? '', $sample['sample_name'] ?? '',
                    $sample['sample_alias'] ?? '', $sample['rnaseq_sample'] ?? '', $set['group'],
                ], "\t");
            }
        }
        fclose($handle);

        try {
            $rPath = rtrim((string) config('site.R_PATH'), '/');
            $rscript = is_file($rPath) ? $rPath : $rPath.'/Rscript';
            $process = new Process([
                $rscript,
                app_path('Mcp/Scripts/mcp_differential_expression.R'),
                $matrixPath,
                $manifest,
                $output,
                (string) $alpha,
                $plot,
                $sampleQc,
            ], base_path(), ['R_LIBS' => (string) config('site.R_LIBS')]);
            $process->setTimeout(900);
            $process->mustRun();

            if (!is_file($output)) {
                throw new RuntimeException('DESeq2 completed without producing a result file.');
            }
            $rows = $this->readTsv($output);
            $sampleRows = is_file($sampleQc) ? $this->readTsv($sampleQc) : [];
            $missingSampleIds = array_values(array_filter(array_map(
                static fn (array $row): ?string => ($row['matrix_status'] ?? null) === 'missing'
                    ? (string) ($row['sample_id'] ?? '')
                    : null,
                $sampleRows
            )));
            $includedGroupCounts = ['group_a' => 0, 'group_b' => 0];
            foreach ($sampleRows as $sampleRow) {
                if (($sampleRow['matrix_status'] ?? null) !== 'included') {
                    continue;
                }
                $group = (string) ($sampleRow['group'] ?? '');
                if (array_key_exists($group, $includedGroupCounts)) {
                    $includedGroupCounts[$group]++;
                }
            }
            $significant = count(array_filter($rows, static fn (array $row): bool =>
                isset($row['padj']) && is_numeric($row['padj']) && (float) $row['padj'] <= $alpha
            ));

            return [
                'rows' => $rows,
                'tested_gene_count' => count($rows),
                'significant_gene_count' => $significant,
                'included_group_counts' => $includedGroupCounts,
                'missing_sample_ids' => $missingSampleIds,
                'volcano_plot_mime_type' => 'image/png',
                'volcano_plot_base64' => is_file($plot) ? base64_encode((string) file_get_contents($plot)) : null,
                'lfc_shrinkage' => 'normal',
                'lfc_threshold' => 1.0,
            ];
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            $detail = trim($e->getProcess()->getErrorOutput() ?: $e->getProcess()->getOutput());
            throw new RuntimeException('Differential expression failed: '.$detail, 0, $e);
        } finally {
            $this->removeDirectory($workDir);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function readTsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read the differential-expression results.');
        }
        $headers = fgetcsv($handle, 0, "\t") ?: [];
        $rows = [];
        while (($values = fgetcsv($handle, 0, "\t")) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $value = $values[$index] ?? null;
                $row[$header] = $value === 'NA' || $value === '' ? null : (is_numeric($value) ? (float) $value : $value);
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($directory);
    }
}
