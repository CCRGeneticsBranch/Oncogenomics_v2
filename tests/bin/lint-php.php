<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$patterns = ['*.php', '*.blade.php', '*.inc', '*.phtml'];
$command = ['git', '-C', $root, 'ls-files', '-z', '--', ...$patterns];

$process = proc_open(
    $command,
    [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes,
);

if (! is_resource($process)) {
    fwrite(STDERR, "Unable to enumerate tracked PHP files.\n");
    exit(1);
}

$trackedFiles = stream_get_contents($pipes[1]);
$gitError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$gitExitCode = proc_close($process);

if ($gitExitCode !== 0) {
    fwrite(STDERR, $gitError ?: "Unable to enumerate tracked PHP files.\n");
    exit($gitExitCode);
}

$files = array_values(array_filter(explode("\0", $trackedFiles)));
$failures = [];

foreach ($files as $file) {
    $path = $root.DIRECTORY_SEPARATOR.$file;

    // A developer may run the gate before committing tracked deletions.
    if (! is_file($path)) {
        continue;
    }

    $output = [];
    $exitCode = 0;

    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        $failures[$file] = implode(PHP_EOL, $output);
    }
}

if ($failures !== []) {
    foreach ($failures as $file => $failure) {
        fwrite(STDERR, "{$file}:\n{$failure}\n");
    }

    fwrite(STDERR, sprintf("%d PHP file(s) failed syntax validation.\n", count($failures)));
    exit(1);
}

fwrite(STDOUT, sprintf("Validated PHP syntax in %d tracked file(s).\n", count($files)));
