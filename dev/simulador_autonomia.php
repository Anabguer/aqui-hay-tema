<?php
declare(strict_types=1);

/**
 * Lab vida autónoma. No escribe partidas.
 * Uso: php dev/simulador_autonomia.php [dias=7] [seeds=3]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\SimuladorAutonomia;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$dias = isset($argv[1]) ? max(1, (int) $argv[1]) : 7;
$seeds = isset($argv[2]) ? max(1, (int) $argv[2]) : 3;
$lab = SimuladorAutonomia::ejecutar($root, [8, 16, 32, 48], $dias, $seeds, 'lab-autonomia');
echo json_encode($lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
