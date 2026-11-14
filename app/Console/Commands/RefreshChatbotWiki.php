<?php

namespace App\Console\Commands;

use App\Services\OncogenomicsWiki;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class RefreshChatbotWiki extends Command
{
    protected $signature = 'chatbot:refresh-wiki {--source= : Use an existing local wiki Git checkout instead of downloading}';
    protected $description = 'Refresh the local Oncogenomics tutorial search snapshot from its public GitHub wiki.';

    public function handle(OncogenomicsWiki $wiki): int
    {
        $temporary = null;
        try {
            $source = $this->option('source');
            if (!$source) {
                $temporary = sys_get_temp_dir().'/clinomics-wiki-'.bin2hex(random_bytes(8));
                (new Process(['git', 'clone', '--depth', '1', '--quiet', OncogenomicsWiki::REPOSITORY, $temporary],
                    env: ['GIT_TERMINAL_PROMPT' => '0'], timeout: 90))->mustRun();
                $source = $temporary;
            }
            $revision = new Process(['git', '-C', $source, 'rev-parse', 'HEAD'], timeout: 10);
            $revision->mustRun();
            $status = new Process(['git', '-C', $source, 'status', '--porcelain'], timeout: 10);
            $status->mustRun();
            $dirty = trim($status->getOutput()) !== '';
            $snapshot = $wiki->snapshot($source, trim($revision->getOutput()), $dirty);
            $path = config('chatbot.wiki.index_path');
            File::ensureDirectoryExists(dirname($path));
            // Replace atomically only after fetching and building a complete snapshot.
            File::replace($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->info('Indexed '.count($snapshot['pages']).' wiki pages at revision '.$snapshot['revision'].'.');
            if ($dirty) $this->info('Included local wiki edits; content SHA-256 '.$snapshot['content_sha256'].'.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Wiki refresh failed; previous snapshot preserved. '.$e->getMessage());
            return self::FAILURE;
        } finally {
            if ($temporary !== null && is_dir($temporary)) File::deleteDirectory($temporary);
        }
    }
}
