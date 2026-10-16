<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

class CacheProjectOncoprint extends Command
{
    protected $signature = 'oncoprint:cache {project_id : Project to precalculate or refresh}';

    protected $description = 'Precalculate a project Oncoprint and cache it for one hour';

    public function handle(): int
    {
        $project = Project::getProject($this->argument('project_id'));
        if (!$project) {
            $this->error('Project not found.');
            return self::FAILURE;
        }
        $this->info('Precalculating Oncoprint for '.$project->name.'…');
        $data = Project::getCachedOncoprintMatrix($project->id, true);
        $this->info(count($data['patients']).' patients, '.count($data['columns']).' alterations cached for one hour.');
        return self::SUCCESS;
    }
}
