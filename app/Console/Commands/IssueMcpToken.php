<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueMcpToken extends Command
{
    protected $signature = 'mcp:token:issue
                            {name? : A label identifying the client this token is for}
                            {--user= : Clinomics user ID whose project permissions the token should inherit}';

    protected $description = 'Generate an MCP bearer token and optionally associate it with a Clinomics user.';

    public function handle(): int
    {
        $name = trim((string) ($this->argument('name') ?? 'mcp-client'));
        $name = $name !== '' ? $name : 'mcp-client';
        $userId = trim((string) ($this->option('user') ?? ''));

        if ($userId !== '' && (! ctype_digit($userId) || User::find((int) $userId) === null)) {
            $this->error("Clinomics user {$userId} does not exist.");

            return self::FAILURE;
        }

        $token = 'mcp_' . Str::random(48);
        $hash = hash('sha256', $token);

        if ($userId !== '') {
            $this->storeUserMapping($hash, (int) $userId);
        }

        $this->newLine();
        $this->info('MCP token generated for: ' . $name);
        $this->line('  Token (shown ONCE - give this to the client):');
        $this->line('    <fg=yellow>' . $token . '</>');
        $this->newLine();
        $this->line('  SHA-256 hash:');
        $this->line('    <fg=green>' . $hash . '</>');
        $this->newLine();

        if ($userId !== '') {
            $this->info("Associated with Clinomics user {$userId}.");
        } else {
            $this->line('Add the hash to MCP_TOKEN_HASHES for an identity-free legacy token.');
        }

        $this->warn('The plaintext token is not stored anywhere. If lost, issue a new one.');

        return self::SUCCESS;
    }

    private function storeUserMapping(string $hash, int $userId): void
    {
        $path = storage_path('app/mcp_token_users.json');
        $mappings = [];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $mappings = is_array($decoded) ? $decoded : [];
        }

        $mappings[$hash] = $userId;
        ksort($mappings);

        $json = json_encode($mappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to persist the MCP token user mapping.');
        }
    }
}
