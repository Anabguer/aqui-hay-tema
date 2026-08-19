<?php
declare(strict_types=1);

/**
 * Laboratorio pueblo vivo 3/6/16/32 × 30/100.
 * Uso: php dev/simulador_pueblo_vivo.php [pueblos=1] [seed=lab-vivo]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorPuebloVivo;

$root = dirname(__DIR__);
$pueblos = isset($argv[1]) ? (int) $argv[1] : 1;
$seed = isset($argv[2]) ? (string) $argv[2] : 'lab-vivo';

$lab = SimuladorPuebloVivo::ejecutar($root, [3, 6, 16, 32], [30, 100], max(1, $pueblos), $seed, 200);
echo json_encode($lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
