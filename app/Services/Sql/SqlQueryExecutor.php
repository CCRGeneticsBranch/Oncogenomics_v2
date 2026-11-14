<?php
namespace App\Services\Sql;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class SqlQueryExecutor
{
    public function execute(array $query, int $limit): array
    {
        $configuration = SqlDatabaseContext::configuration();
        if (!is_array($configuration)) throw new RuntimeException('SQL connection is not configured.');
        // Isolate read-only transaction and session settings from normal application writes.
        $name = 'chatbot_sql_runtime';
        config(['database.connections.'.$name => $configuration]);
        DB::purge($name);
        $connection = DB::connection($name);
        $limit = max(1, min($limit, 500));
        $milliseconds = max(100, min((int) config('chatbot.sql.timeout_ms', 5000), 30000));
        $pdo = null;
        $sql = $query['sql'];
        $values = [];
        $driver = (string) ($configuration['driver'] ?? '');
        $stage = 'connection';
        try {
            $driver = $connection->getDriverName();
            $pdo = $connection->getPdo();
            $query['sql'] = NumericSqlExpression::render($query['sql'], $driver);
            $stage = 'read_only_setup';
            if ($driver === 'oracle') {
                if (!method_exists($pdo, 'setCallTimeout') || !$pdo->setCallTimeout($milliseconds)) throw new RuntimeException('Oracle call timeout is required.');
                $pdo->beginTransaction();
                $pdo->exec('SET TRANSACTION READ ONLY');
                $sql = 'SELECT * FROM ('.$query['sql'].') chatbot_result WHERE ROWNUM <= '.($limit + 1);
            } elseif ($driver === 'mysql') {
                $pdo->exec('SET SESSION MAX_EXECUTION_TIME = '.$milliseconds);
                $pdo->exec('SET TRANSACTION READ ONLY');
                $pdo->beginTransaction();
                $sql = 'SELECT /*+ MAX_EXECUTION_TIME('.$milliseconds.') */ * FROM ('.$query['sql'].') chatbot_result LIMIT '.($limit + 1);
            } elseif ($driver === 'sqlite' && app()->environment('testing')) {
                $pdo->sqliteCreateFunction('clinomics_safe_number', static fn ($value) => NumericSqlExpression::isDecimal($value) ? (float) $value : null, 1);
                $pdo->exec('PRAGMA query_only = ON');
                $pdo->beginTransaction();
                $sql = 'SELECT * FROM ('.$query['sql'].') chatbot_result LIMIT '.($limit + 1);
            } else throw new RuntimeException('SQL execution supports Oracle and MySQL with enforced read-only transactions and timeouts.');
            // Unique positional bindings avoid PDO differences for repeated named parameters.
            $values = [];
            $sql = preg_replace_callback('/:[a-z][a-z0-9_]*/i', function ($m) use ($query, &$values) {
                $values[] = $query['bindings'][substr($m[0],1)]; return '?';
            }, $sql);
            $started = hrtime(true);
            $stage = 'prepare';
            $statement = $pdo->prepare($sql);
            foreach ($values as $i => $value) {
                $statement->bindValue($i + 1, $value, $value === null ? PDO::PARAM_NULL : (is_int($value) || is_bool($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
            }
            $stage = 'execute';
            $statement->execute();
            $stage = 'fetch';
            $rows = []; $bytes = 0; $truncated = false;
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                if ((hrtime(true) - $started) / 1000000 > $milliseconds) { $truncated = true; break; }
                if (count($rows) >= $limit) { $truncated = true; break; }
                $row = array_change_key_case($row, CASE_LOWER);
                foreach ($row as &$value) {
                    if (is_resource($value)) $value = stream_get_contents($value, 4001);
                    if (is_string($value) && mb_strlen($value) > 4000) { $value = mb_substr($value,0,4000).' [truncated]'; $truncated = true; }
                }
                unset($value);
                $bytes += strlen(json_encode($row, JSON_INVALID_UTF8_SUBSTITUTE));
                if ($bytes > 1000000) { $truncated = true; break; }
                $rows[] = array_map(fn ($column) => $row[$column] ?? null, $query['columns']);
            }
            $statement->closeCursor();
            return ['rows' => $rows, 'truncated' => $truncated, 'duration_ms' => (int) round((hrtime(true)-$started)/1000000),
                'technical_details' => ['sql' => $sql, 'bindings' => $values, 'dialect' => $driver,
                    'read_only' => true, 'authorization' => 'Validated user_projects join with server-bound user and enforced cohort scope.',
                    'row_limit' => $limit, 'timeout_ms' => $milliseconds]];
        } catch (\Throwable $e) {
            throw new SqlExecutionException($e, [
                'sql'=>$sql, 'bindings'=>$values, 'dialect'=>$driver, 'execution_status'=>'failed',
                'row_limit'=>$limit, 'timeout_ms'=>$milliseconds,
            ], $stage);
        } finally {
            try { if ($pdo !== null && $pdo->inTransaction()) $pdo->rollBack(); }
            finally { DB::purge($name); }
        }
    }
}
