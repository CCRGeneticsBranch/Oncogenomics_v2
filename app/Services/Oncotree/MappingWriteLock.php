<?php

namespace App\Services\Oncotree;

use Illuminate\Database\Connection;

class MappingWriteLock
{
    public function run(Connection $connection, callable $write): bool
    {
        $driver = $connection->getDriverName();
        if (!in_array($driver, ['oracle', 'mysql', 'sqlite'], true)) {
            throw new \RuntimeException('Unsupported OncoTree mapping database driver: '.$driver);
        }
        // Do not release a MySQL session lock before an outer transaction commits.
        if ($connection->transactionLevel() !== 0) {
            throw new \RuntimeException('Mapping inserts must run outside an existing transaction.');
        }
        $name = null;
        if ($driver === 'mysql') {
            // GET_LOCK is session-scoped and shared by scheduled jobs on different
            // application hosts. Keep it on the writer, never a read replica.
            $name = 'oncotree:'.substr(hash('sha256', $connection->getDatabaseName().'|'.$connection->getTablePrefix().'oncotree_mapping'), 0, 48);
            $result = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$name], false);
            if ((int) ($result->acquired ?? 0) !== 1) {
                throw new \RuntimeException('Another mapping writer holds the MySQL lock; retry later.');
            }
        }
        try {
            return $connection->transaction(function () use ($connection, $driver, $write) {
                if ($driver === 'oracle') {
                    $table = $connection->getQueryGrammar()->wrapTable('oncotree_mapping');
                    $connection->statement('LOCK TABLE '.$table.' IN EXCLUSIVE MODE NOWAIT');
                }
                return $write();
            });
        } finally {
            // MySQL named locks survive commit/rollback, so always release them.
            if ($name !== null) {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name], false);
            }
        }
    }
}
