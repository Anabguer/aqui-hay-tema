<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$service = new PartidaService($root);
$p = $service->nuevaPartida('juego_v1', 'vec-contador-cap16');

CapacidadViviendas::ensure($p);
ok(
    (int) ($p['celeste']['objetivo_poblacion_activa'] ?? 0) === 16,
    'celeste.objetivo_poblacion_activa = 16'
);
ok((int) ($p['celeste']['vivienda_capacidad_max'] ?? 0) === 16, 'celeste.vivienda_capacidad_max = 16');

$estado = $service->estadoResumido($p);
ok((int) ($estado['pueblo_capacidad_max'] ?? 0) === 16, 'estado pueblo_capacidad_max = 16');
ok((int) ($estado['pueblo_residentes_activos'] ?? 0) === 3, 'estado pueblo_residentes_activos = 3');

$js = file_get_contents($root . '/assets/js/play-v3.js');
ok(strpos($js, 'capObjetivoPoblacionVisible') !== false, 'JS capObjetivoPoblacionVisible');
ok(strpos($js, 'objetivo_poblacion_activa') !== false, 'JS lee celeste.objetivo_poblacion_activa');
ok(strpos($js, 'CELESTINE_OBJETIVO_POBLACION_FALLBACK = 16') !== false, 'JS fallback objetivo 16');

echo $failures === 0 ? "OK vecinos_contador_objetivo\n" : "FAIL vecinos_contador_objetivo ({$failures})\n";
exit($failures > 0 ? 1 : 0);
