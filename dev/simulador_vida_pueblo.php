<?php
declare(strict_types=1);

/**
 * Lab Vida del Pueblo. No escribe partidas.
 * Uso: php dev/simulador_vida_pueblo.php [seeds=5] [seed=lab-vida-pueblo]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorVidaPueblo;

$root = dirname(__DIR__);
$seeds = isset($argv[1]) ? max(1, (int) $argv[1]) : 5;
$seed = isset($argv[2]) ? (string) $argv[2] : 'lab-vida-pueblo';

$lab = SimuladorVidaPueblo::ejecutar($root, [7, 30, 100, 365], $seeds, $seed);
echo json_encode($lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
