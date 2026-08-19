<?php
declare(strict_types=1);

/**
 * Lab B3 misiones reales. Uso: php dev/simulador_misiones_diarias.php [seeds=2]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorMisionesDiarias;

$root = dirname(__DIR__);
$seeds = isset($argv[1]) ? max(1, (int) $argv[1]) : 2;
$lab = SimuladorMisionesDiarias::ejecutar($root, [8, 16, 32], [30, 100, 365], $seeds, 'lab-misiones-b3');
echo json_encode($lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
