<?php
declare(strict_types=1);

/**
 * Runner pesado del gate integral. No entra en run_all.
 * Uso: php dev/playtest_integral_gate.php [--full]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PlaytestIntegralRunner;

$root = dirname(__DIR__);
$full = in_array('--full', $argv ?? [], true);
$runner = new PlaytestIntegralRunner($root);

echo "Running gate rápido...\n";
$data = $runner->runGateRapido();

if ($full) {
    echo "Running horizontes 30/100/365 (2–3 seeds × 4 perfiles)...\n";
    $data['horizontes'] = $runner->runHorizontes(
        [30, 100, 365],
        ['activa', 'normal', 'torpe', 'inactiva'],
        ['s1', 's2'],
        'playtest_01'
    );
} else {
    echo "Running horizontes 30d + 100d (2 seeds × 4 perfiles)...\n";
    $data['horizontes'] = $runner->runHorizontes(
        [30, 100],
        ['activa', 'normal', 'torpe', 'inactiva'],
        ['s1', 's2'],
        'playtest_01'
    );
}

$outDir = $root . '/docs';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$jsonPath = $outDir . '/PLAYTEST_INTEGRAL_REPORT.json';
$mdPath = $outDir . '/PLAYTEST_INTEGRAL_REPORT.md';
file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($mdPath, PlaytestIntegralRunner::renderMarkdown($data));

echo "Neni: " . ($data['neni']['veredicto'] ?? '?') . PHP_EOL;
echo "Resumen: " . json_encode($data['resumen'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "Wrote $mdPath\n";
echo "Wrote $jsonPath\n";
