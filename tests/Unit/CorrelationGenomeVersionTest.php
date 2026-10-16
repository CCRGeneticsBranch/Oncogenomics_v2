<?php
namespace Tests\Unit;

use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Tests\TestCase;

class CorrelationGenomeVersionTest extends TestCase
{
    public function test_endpoints_reject_annotation_names_before_loading_data(): void
    {
        $controller = new ProjectController;
        foreach (['ensembl', 'refseq'] as $version) {
            foreach (['getCorrelationData', 'getTwoGenesDotplotData'] as $method) {
                try {
                    $controller->$method(42, 'FGFR4', $method === 'getCorrelationData' ? 0.2 : 'PITX3', $version);
                    $this->fail('Annotation name was accepted as a genome.');
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    $this->assertSame(422, $e->getStatusCode());
                }
            }
        }
    }

    public function test_matrix_selection_does_not_reuse_another_genomes_data(): void
    {
        $dir = sys_get_temp_dir().'/correlation-genome-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/expression.coding.tpm.RDS', 'legacy');
        file_put_contents($dir.'/expression.coding.hg38.tpm.RDS', 'hg38');
        $project = new class extends Project {
            public function getExpressionGenomeVersion() { return ['hg19']; }
            public function matrix($dir, $genome) { return $this->correlationMatrixFile($dir, $genome, 'tpm'); }
        };
        try {
            $this->assertSame($dir.'/expression.coding.tpm.RDS', $project->matrix($dir, 'hg19'));
            $this->assertSame($dir.'/expression.coding.hg38.tpm.RDS', $project->matrix($dir, 'hg38'));
            unlink($dir.'/expression.coding.hg38.tpm.RDS');
            $this->expectException(\RuntimeException::class);
            $project->matrix($dir, 'hg38');
        } finally {
            foreach (glob($dir.'/*') as $file) unlink($file);
            rmdir($dir);
        }
    }
}
