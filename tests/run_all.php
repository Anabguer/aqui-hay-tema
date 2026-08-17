<?php
declare(strict_types=1);

/**
 * Ejecutar todos los tests: php tests/run_all.php
 */
$files = glob(__DIR__ . '/*_test.php') ?: [];
$files[] = __DIR__ . '/smoke.php';
$files = array_unique($files);
sort($files);

$failed = 0;
foreach ($files as $file) {
    echo "=== " . basename($file) . " ===\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        $failed++;
    }
    echo "\n";
}

exit($failed > 0 ? 1 : 0);
