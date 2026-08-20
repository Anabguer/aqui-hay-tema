<?php
declare(strict_types=1);

/**
 * Lab economía. No escribe partidas. No enciende economy_enabled.
 * Uso: php dev/simulador_economia.php [seeds=8] [seed=lab-economia]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorEconomia;

$root = dirname(__DIR__);
$seeds = isset($argv[1]) ? max(1, (int) $argv[1]) : 8;
$seed = isset($argv[2]) ? (string) $argv[2] : 'lab-economia';

$lab = SimuladorEconomia::ejecutar($root, [30, 100, 365], $seeds, $seed);
$json = json_encode($lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "json_encode fail\n");
    exit(1);
}
if (isset($argv[3]) && $argv[3] !== '') {
    file_put_contents($argv[3], $json);
}
echo $json . "\n";
