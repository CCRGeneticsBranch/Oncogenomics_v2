<?php

namespace Tests\Unit;

use App\Services\Oncotree\MappingWriteLock;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Tests\TestCase;

class OncotreeMappingWriteLockTest extends TestCase
{
    private function connection(string $driver): Connection
    {
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn($driver);
        $connection->shouldReceive('transactionLevel')->andReturn(0);
        return $connection;
    }

    public function test_mysql_commits_before_releasing_writer_connection_lock(): void
    {
        $connection = $this->connection('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('clinomics');
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $name = 'oncotree:'.substr(hash('sha256', 'clinomics|oncotree_mapping'), 0, 48);
        $connection->shouldReceive('selectOne')->once()->ordered()
            ->with('SELECT GET_LOCK(?, 0) AS acquired', [$name], false)->andReturn((object) ['acquired' => 1]);
        $connection->shouldReceive('transaction')->once()->ordered()->andReturnUsing(fn ($callback) => $callback());
        $connection->shouldReceive('selectOne')->once()->ordered()
            ->with('SELECT RELEASE_LOCK(?) AS released', [$name], false)->andReturn((object) ['released' => 1]);
        $this->assertTrue(app(MappingWriteLock::class)->run($connection, fn () => true));
    }

    public function test_mysql_releases_lock_on_failure(): void
    {
        $connection = $this->connection('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('clinomics');
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $connection->shouldReceive('selectOne')->once()->with('SELECT GET_LOCK(?, 0) AS acquired', \Mockery::type('array'), false)
            ->andReturn((object) ['acquired' => 1]);
        $connection->shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
        $connection->shouldReceive('selectOne')->once()->with('SELECT RELEASE_LOCK(?) AS released', \Mockery::type('array'), false)
            ->andReturn((object) ['released' => 1]);
        $this->expectExceptionMessage('Insert failed');
        app(MappingWriteLock::class)->run($connection, function () { throw new \RuntimeException('Insert failed'); });
    }

    public function test_mysql_busy_lock_does_not_start_transaction_or_write(): void
    {
        $connection = $this->connection('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('clinomics');
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $connection->shouldReceive('selectOne')->once()->with('SELECT GET_LOCK(?, 0) AS acquired', \Mockery::type('array'), false)
            ->andReturn((object) ['acquired' => 0]);
        $connection->shouldNotReceive('transaction');
        $this->expectExceptionMessage('Another mapping writer');
        app(MappingWriteLock::class)->run($connection, function () { $this->fail('Must not write'); });
    }

    public function test_oracle_locks_inside_transaction_before_writing(): void
    {
        $connection = $this->connection('oracle');
        $grammar = \Mockery::mock(Grammar::class);
        $grammar->shouldReceive('wrapTable')->with('oncotree_mapping')->andReturn('"ONCOTREE_MAPPING"');
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $locked = false;
        $connection->shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
        $connection->shouldReceive('statement')->once()->with('LOCK TABLE "ONCOTREE_MAPPING" IN EXCLUSIVE MODE NOWAIT')
            ->andReturnUsing(function () use (&$locked) { $locked = true; return true; });
        $connection->shouldNotReceive('selectOne');
        $this->assertTrue(app(MappingWriteLock::class)->run($connection, function () use (&$locked) {
            $this->assertTrue($locked); return true;
        }));
    }
}
