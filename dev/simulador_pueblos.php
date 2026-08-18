<?php
declare(strict_types=1);

/**
 * Laboratorio de distribuciones. No escribe partidas.
 * Uso: php dev/simulador_pueblos.php [pueblos=1000] [residentes=16] [seed=lab-1000]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorPueblos;

$root = dirname(__DIR__);
$pueblos = isset($argv[1]) ? (int) $argv[1] : 1000;
$residentes = isset($argv[2]) ? (int) $argv[2] : 16;
$seed = isset($argv[3]) ? (string) $argv[3] : 'lab-1000';

$lab = SimuladorPueblos::ejecutar($root, $pueblos, $residentes, $seed);
echo json_encode($lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
