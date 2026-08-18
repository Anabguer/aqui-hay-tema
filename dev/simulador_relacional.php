<?php
declare(strict_types=1);

/**
 * Laboratorio relacional. No escribe partidas.
 * Uso: php dev/simulador_relacional.php [pueblos=20] [dias=14] [seed=lab-rel]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorRelacional;

$root = dirname(__DIR__);
$pueblos = isset($argv[1]) ? (int) $argv[1] : 20;
$dias = isset($argv[2]) ? (int) $argv[2] : 14;
$seed = isset($argv[3]) ? (string) $argv[3] : 'lab-rel';

$lab = SimuladorRelacional::ejecutar($root, $pueblos, [16, 32], $dias, $seed);
echo json_encode($lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
