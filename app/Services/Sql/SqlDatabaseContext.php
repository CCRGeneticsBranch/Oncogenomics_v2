<?php
namespace App\Services\Sql;

use Illuminate\Support\Facades\DB;

/** Shares the resolved connection between model context and execution; never exposes credentials. */
class SqlDatabaseContext
{
    public static function configuration(): array
    {
        $source = config('chatbot.sql.connection') ?: config('database.default');
        // Laravel resolves DATABASE_URL here. Creating the connection object does not open PDO.
        return DB::connection($source)->getConfig();
    }

    public static function describe(): array
    {
        $driver = (string) (self::configuration()['driver'] ?? '');
        return [
            'db_type' => $driver,
            'sql_input' => 'restricted_parameterized_select',
            'numeric_conversion' => 'SAFE_NUMBER(column); rendered server-side for the selected database',
            'row_limit' => 'Pass the limit tool argument; the server adds the database-specific row limit.',
            'dialect_notes' => match ($driver) {
                'mysql' => 'Target MySQL. The server uses guarded CAST AS DECIMAL, LIMIT, read-only transactions and MAX_EXECUTION_TIME. Do not generate Oracle TO_NUMBER, ROWNUM, NVL or DUAL.',
                'oracle' => 'Target Oracle. The server uses guarded TO_NUMBER, ROWNUM, read-only transactions and OCI timeout. Do not generate MySQL LIMIT or backticks.',
                'sqlite' => 'SQLite is for automated tests only; production execution requires Oracle or MySQL.',
                default => 'This database driver is not supported for SQL fallback execution.',
            },
            'schema_note' => 'The allowlisted logical schema is shared. Catalog physical type labels were captured from Oracle; they are not instructions to use Oracle syntax on MySQL.',
        ];
    }

    public static function instructions(): string
    {
        return "SQL execution database (server-selected; never override):\n"
            .json_encode(self::describe(), JSON_UNESCAPED_SLASHES)."\n"
            .'Generate only the restricted SELECT grammar from getSqlSchema, even if the database supports more syntax. Use SAFE_NUMBER for numeric metadata and bound values; do not generate native casts or row-limit clauses yourself.';
    }
}
