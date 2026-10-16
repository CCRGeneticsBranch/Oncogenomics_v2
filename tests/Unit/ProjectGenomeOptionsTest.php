<?php

namespace Tests\Unit;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectGenomeOptionsTest extends TestCase
{
    public function test_annotation_labels_use_project_builds(): void
    {
        DB::shouldReceive('select')->once()->andReturn([(object)['genome_version' => 'ensembl'], (object)['genome_version' => 'refseq']]);
        $project = new class extends Project {
            public function getGenomeVersion() { return 'hg19, hg38'; }
        };
        $project->id = 42;
        $this->assertSame(['hg19', 'hg38'], $project->getExpressionGenomeVersion());
    }

    public function test_available_expression_builds_take_precedence(): void
    {
        DB::shouldReceive('select')->once()->andReturn([(object)['genome_version' => 'hg38'], (object)['genome_version' => 'ensembl']]);
        $project = new class extends Project {
            public function getGenomeVersion() { throw new \RuntimeException('Should not need fallback'); }
        };
        $project->id = 42;
        $this->assertSame(['hg38'], $project->getExpressionGenomeVersion());
    }

    public function test_unknown_project_builds_are_not_presented_as_genomes(): void
    {
        DB::shouldReceive('select')->once()->andReturn([(object)['genome_version' => 'ensembl']]);
        $project = new class extends Project {
            public function getGenomeVersion() { return 'ensembl'; }
        };
        $project->id = 42;
        $this->assertSame([], $project->getExpressionGenomeVersion());
    }
}
