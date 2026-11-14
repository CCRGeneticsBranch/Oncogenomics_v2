<?php
namespace App\Services\Sql;

use RuntimeException;
use Throwable;

/** Exposes only classified diagnostics, never the driver's raw message or connection details. */
class SqlExecutionException extends RuntimeException
{
    public readonly array $diagnostic;

    public function __construct(Throwable $previous, public readonly array $technicalDetails, string $stage)
    {
        $oracle = null;
        $state = null;
        $native = null;
        for ($error = $previous; $error !== null; $error = $error->getPrevious()) {
            if (preg_match('/\bORA-([0-9]{5})\b/', $error->getMessage(), $m)) $oracle ??= 'ORA-'.$m[1];
            if (preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/', $error->getMessage(), $m)) $state ??= $m[1];
            if ($error instanceof \PDOException && is_array($error->errorInfo ?? null)) {
                if (preg_match('/^[A-Z0-9]{5}$/', (string) ($error->errorInfo[0] ?? ''))) $state ??= $error->errorInfo[0];
                if (ctype_digit((string) ($error->errorInfo[1] ?? ''))) $native ??= (string) $error->errorInfo[1];
            }
        }
        $code = $oracle ?? $native ?? $state;
        $category = match (true) {
            in_array($code, ['ORA-01013','ORA-03156','ORA-03136','3024','1317'], true) => 'timeout_or_cancelled',
            in_array($code, ['ORA-00904','ORA-00942','1054','1146','42S22','42S02'], true) => 'schema_mismatch',
            in_array($code, ['ORA-00932','ORA-01722'], true) => 'datatype_mismatch',
            in_array($code, ['ORA-00900','ORA-00923','ORA-00933','1064'], true) => 'sql_syntax',
            $stage === 'read_only_setup' => 'read_only_setup',
            default => 'database_error',
        };
        $hint = match ($category) {
            'timeout_or_cancelled' => 'The database timed out or cancelled the query. This does not mean there are no matches. Keep the requested gene, mutation type, patient prefix and authorization filters; do not broaden or replace them.',
            'schema_mismatch' => 'The database rejected a table or column. The SQL catalog may differ from the deployed schema; check the attempted SQL and catalog against this database.',
            'datatype_mismatch' => 'The database rejected a datatype operation. Check selected column types, DISTINCT and numeric conversions.',
            'sql_syntax' => 'The database rejected the generated SQL syntax. Check the attempted SQL for this database dialect.',
            'read_only_setup' => 'The read-only transaction or required timeout could not be configured. The data query was not executed.',
            default => 'The database query failed. Use the error reference and technical details to diagnose it; no conclusion about matching records can be drawn.',
        };
        $this->diagnostic = ['category'=>$category,'database_code'=>$code,'sqlstate'=>$state,'stage'=>$stage];
        parent::__construct($hint, 0, $previous);
    }
}
