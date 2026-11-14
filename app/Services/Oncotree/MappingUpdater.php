<?php

namespace App\Services\Oncotree;

use Illuminate\Support\Facades\DB;

class MappingUpdater
{
    public function taxonomy(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) throw new \RuntimeException('Cannot read OncoTree TSV.');
        try {
            $header = fgetcsv($handle, 0, "\t", '"', '');
            if (!is_array($header) || array_diff(['ID', 'TEXT', 'MAIN_TYPE', 'PARENT'], $header)) {
                throw new \RuntimeException('OncoTree TSV requires ID, TEXT, MAIN_TYPE, PARENT headers.');
            }
            $terms = [];
            while (($values = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
                if ($values === [null]) continue;
                if (count($values) !== count($header)) throw new \RuntimeException('Malformed OncoTree TSV row.');
                $row = array_combine($header, $values);
                $id = trim($row['ID']);
                if ($id === '' || strlen($id) > 100 || isset($terms[$id])) {
                    throw new \RuntimeException('Empty, duplicate, or oversized OncoTree code.');
                }
                $terms[$id] = $row;
            }
            if ($terms === []) throw new \RuntimeException('Empty OncoTree TSV.');
            // User-approved local classification, not an official OncoTree term.
            if (!isset($terms['NORMAL'])) {
                $terms['NORMAL'] = ['ID' => 'NORMAL', 'TEXT' => 'Normal / non-tumor tissue or non-tumor-derived cell line (local convention)',
                    'MAIN_TYPE' => 'Normal (local convention)', 'PARENT' => ''];
            }
            return $terms;
        } finally {
            fclose($handle);
        }
    }

    public function unmapped(): array
    {
        // Keep the original ID unchanged: mapping joins use exact diagnosis text.
        return DB::table('cancer_types as c')->select('c.id as id', 'c.name as name', 'c.description as description')
            ->whereNotNull('c.id')->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('oncotree_mapping as m')->whereColumn('m.diagnosis', 'c.id');
            })->distinct()->orderBy('c.id')->get()->map(static fn ($r) => (array) $r)->all();
    }

    public function validate(array $proposal, array $diagnosis, array $taxonomy): array
    {
        if (($proposal['diagnosis'] ?? null) !== $diagnosis['id']) {
            throw new \InvalidArgumentException('Proposal diagnosis does not exactly match cancer_types.ID.');
        }
        $status = $proposal['status'] ?? null;
        if (!in_array($status, ['matched', 'ambiguous', 'unresolved'], true)
            || !is_string($proposal['reason'] ?? null) || trim($proposal['reason']) === ''
            || !is_array($proposal['sources'] ?? null)) {
            throw new \InvalidArgumentException('Invalid proposal structure.');
        }
        if ($status !== 'matched') {
            if (($proposal['code'] ?? null) !== null) throw new \InvalidArgumentException('Uncertain proposals must not assign a code.');
            return $proposal;
        }
        $code = $proposal['code'] ?? null;
        if (!is_string($code) || !isset($taxonomy[$code])) {
            throw new \InvalidArgumentException('Proposed code is not in the supplied OncoTree TSV.');
        }
        if (strlen($diagnosis['id']) > 1000) {
            throw new \InvalidArgumentException('Diagnosis exceeds the mapping column size (1000 bytes).');
        }
        $basis = $proposal['mapping_basis'] ?? 'literature';
        if (!in_array($basis, ['taxonomy', 'literature', 'local_convention'], true)) {
            throw new \InvalidArgumentException('Invalid mapping basis.');
        }
        if ($basis !== 'literature') {
            if ($proposal['sources'] !== []) throw new \InvalidArgumentException('Non-literature mappings must not claim external sources.');
            $proposal['mapping_basis'] = $basis;
            $proposal['method'] = 'llm_'.$basis;
            return $proposal;
        }
        $proposal['sources'] = app(EvidenceVerifier::class)->verify($proposal['sources']);
        return $proposal;
    }

    public function insert(array $diagnosis, string $code): bool
    {
        $connection = DB::connection();
        return app(MappingWriteLock::class)->run($connection, function () use ($connection, $diagnosis, $code) {
            if ($connection->table('oncotree_mapping')->where('diagnosis', $diagnosis['id'])->exists()) return false;
            $current = $connection->table('cancer_types')->select('name as name', 'description as description')
                ->where('id', $diagnosis['id'])->first();
            if ($current === null || (string) $current->name !== (string) $diagnosis['name']
                || (string) $current->description !== (string) $diagnosis['description']) {
                throw new \RuntimeException('Diagnosis changed during research; no mapping applied.');
            }
            $connection->table('oncotree_mapping')->insert(['diagnosis' => $diagnosis['id'], 'id' => $code]);
            return true;
        });
    }

    public function recipients(string $mode): array
    {
        if ($mode === 'dev') return ['chouh@nih.gov'];
        if ($mode !== 'production') throw new \InvalidArgumentException('Mode must be dev or production.');
        $emails = [];
        foreach (DB::table('users')->select('email as email', 'permissions as permissions')->get() as $user) {
            $permissions = json_decode((string) $user->permissions, true);
            if (!is_array($permissions)) continue;
            $enabled = static fn ($key) => in_array($permissions[$key] ?? null, [1, '1', true], true);
            if (!$enabled('_superadmin') && !$enabled('_projectmanager')) continue;
            $email = strtolower(trim((string) $user->email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $emails[$email] = $email;
        }
        return array_values($emails);
    }
}
