<?php
declare(strict_types=1);

/**
 * Lab B3+B4 peticiones. Uso:
 *   php dev/simulador_peticiones_pueblo.php
 *   php dev/simulador_peticiones_pueblo.php 2 8
 *   php dev/simulador_peticiones_pueblo.php 2 8,16,32
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorPeticionesPueblo;

$root = dirname(__DIR__);
$seeds = isset($argv[1]) ? max(1, (int) $argv[1]) : 2;
$tamanos = [8];
if (isset($argv[2]) && trim((string) $argv[2]) !== '') {
    $tamanos = [];
    foreach (explode(',', (string) $argv[2]) as $n) {
        $n = (int) trim($n);
        if ($n > 0) {
            $tamanos[] = $n;
        }
    }
    if ($tamanos === []) {
        $tamanos = [8];
    }
}

$lab = SimuladorPeticionesPueblo::ejecutarComparacion(
    $root,
    ['E1', 'E2', 'E3', 'E4', 'E5'],
    $tamanos,
    [30, 100, 365],
    $seeds,
    'lab-peticiones-b4'
);
echo json_encode($lab, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
