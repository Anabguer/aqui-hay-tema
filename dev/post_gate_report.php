<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PostGateIntegralRunner;

$root = dirname(__DIR__);
echo "Running POST GATE integral...\n";
$runner = new PostGateIntegralRunner($root);
$data = $runner->run();

$jsonPath = $root . '/docs/POST_GATE_REPORT.json';
$mdPath = $root . '/docs/POST_GATE_REPORT.md';
file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($mdPath, PostGateIntegralRunner::renderMarkdown($data));

echo 'Neni: ' . ($data['neni']['veredicto'] ?? '?') . PHP_EOL;
echo 'Resumen: ' . json_encode($data['resumen'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "Wrote $mdPath\n";
