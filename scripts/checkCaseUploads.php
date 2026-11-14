#!/usr/bin/env php
<?php

// Read-only case audit using this instance's Laravel Oracle/MySQL connection.
$root = dirname(__DIR__);
$options = getopt('i:o:h', ['input:', 'output:', 'table:', 'help']);
if (isset($options['h']) || isset($options['help'])) {
    echo "Usage: php scripts/checkCaseUploads.php [-i input.txt] [-o report.tsv] [--table=cases]\n"
        ."Default input: misc/cases_with_successful_txt.txt; default output: stdout.\n"
        ."Reads patient_id/case_id lines. Blank lines and # comments are ignored.\n"
        ."Success means database status passed, not independent verification of every file.\n"
        ."Pending/unknown statuses are unconfirmed. All distinct status/version pairs are reported.\n"
        ."Output files must not already exist. Exit: 0 all passed; 1 discrepancies; 2 operational error.\n";
    exit(0);
}
$input = $options['i'] ?? $options['input'] ?? $root.'/misc/cases_with_successful_txt.txt';
$output = $options['o'] ?? $options['output'] ?? null;
$table = $options['table'] ?? 'cases';
if (!in_array($table, ['cases', 'case'], true)) {
    fwrite(STDERR, "Table must be cases or case.\n"); exit(2);
}
if (!is_readable($input) || ($output !== null && realpath($input) === realpath($output))) {
    fwrite(STDERR, "Input must be readable and output must be a different file.\n"); exit(2);
}
$counts = [];
$exit = 0;
try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    // Check connection/schema before creating an output file.
    Illuminate\Support\Facades\DB::table($table)->select('patient_id', 'case_id', 'status', 'version')->whereRaw('1 = 0')->get();
    $in = fopen($input, 'r');
    $out = $output === null ? STDOUT : fopen($output, 'x');
    if ($in === false || $out === false) throw new RuntimeException('Cannot open input/output.');
    $write = static function (array $row) use ($out): void {
        if (fputcsv($out, $row, "\t", '"', '') === false) throw new RuntimeException('Cannot write report.');
    };
    $write(['line', 'patient_id', 'case_id', 'found', 'upload_check', 'database_status', 'pipeline_version', 'distinct_status_version_pairs']);
    $lineNumber = 0;
    while (($line = fgets($in)) !== false) {
        $lineNumber++;
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!preg_match('~^([^/\s]+)/([^/\s]+)$~u', $line, $parts)) {
            $write([$lineNumber, '', '', '', 'invalid_input', '', '', '']);
            $counts['invalid_input'] = ($counts['invalid_input'] ?? 0) + 1;
            $exit = 1; continue;
        }
        [, $patient, $case] = $parts;
        $rows = Illuminate\Support\Facades\DB::table($table)
            ->where('patient_id', $patient)->where('case_id', $case)
            ->select('status as case_status', 'version as pipeline_version')->distinct()
            ->orderBy('status')->orderBy('version')->get();
        if ($rows->isEmpty()) {
            $write([$lineNumber, $patient, $case, 'no', 'missing', '', '', 0]);
            $counts['missing'] = ($counts['missing'] ?? 0) + 1;
            $exit = 1; continue;
        }
        foreach ($rows as $row) {
            $check = match (strtolower(trim($row->case_status ?? ''))) {
                'passed' => 'success',
                'failed', 'not_successful' => 'failed',
                default => 'unconfirmed',
            };
            $version = $row->pipeline_version;
            if ($check === 'success' && in_array(strtolower(trim($version ?? '')), ['', 'na', 'n/a', 'unknown'], true)) {
                $check = 'success_version_missing';
            }
            $write([$lineNumber, $patient, $case, 'yes', $check, $row->case_status, $version, $rows->count()]);
            $counts[$check] = ($counts[$check] ?? 0) + 1;
            if ($check !== 'success' || $rows->count() > 1) $exit = 1;
        }
    }
    if (!feof($in)) throw new RuntimeException('Input read failed.');
    fclose($in);
    if ($output !== null) fclose($out);
    fwrite(STDERR, 'Report outcomes: '.json_encode($counts).PHP_EOL);
} catch (Throwable $e) {
    // Avoid printing connection credentials or SQL containing patient identifiers.
    preg_match('/ORA-\d+/', $e->getMessage(), $oracle);
    fwrite(STDERR, 'Case audit failed'.(isset($oracle[0]) ? ' ('.$oracle[0].')' : '').". Check database connectivity, table columns and file permissions; any partial output is incomplete.\n");
    exit(2);
}
exit($exit);
