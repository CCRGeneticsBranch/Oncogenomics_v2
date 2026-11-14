<?php
namespace App\Services\Sql;

use InvalidArgumentException;

/** Only the compiler's SAFE_NUMBER(column/parameter) nodes reach this renderer. */
class NumericSqlExpression
{
    // Bounded decimal precision makes conversion predictable and excludes missing-value labels.
    private const DECIMAL = '^[+-]?[0-9]{1,18}([.][0-9]{1,12})?$';

    public static function isDecimal(mixed $value): bool
    {
        return (is_string($value) || is_int($value) || is_float($value))
            && preg_match('/'.self::DECIMAL.'/D', trim((string) $value)) === 1;
    }

    public static function render(string $sql, string $driver): string
    {
        return preg_replace_callback('/SAFE_NUMBER\(([a-z][a-z0-9_]*\.[a-z][a-z0-9_]*|:p[1-9][0-9]*)\)/', static function ($match) use ($driver) {
            $value = 'TRIM('.$match[1].')';
            $pattern = self::DECIMAL;
            return match ($driver) {
                'oracle' => "(CASE WHEN REGEXP_LIKE($value, '$pattern') THEN TO_NUMBER(LTRIM($value, '+'), '999999999999999999D999999999999', 'NLS_NUMERIC_CHARACTERS=''.,''') ELSE NULL END)",
                'mysql' => "(CASE WHEN $value REGEXP '$pattern' THEN CAST($value AS DECIMAL(30,12)) ELSE NULL END)",
                'sqlite' => 'clinomics_safe_number('.$match[1].')',
                default => throw new InvalidArgumentException('Unsupported numeric SQL dialect.'),
            };
        }, $sql);
    }
}
