<?php
declare(strict_types=1);

/**
 * Smoke tests del motor — ejecutar: php tests/smoke.php
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\BloqueA;
use AquiHayTema\Engine\CitaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Reloj;

$root = dirname(__DIR__);
$failures = 0;

function assertTrue(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    } else {
        echo "OK: {$msg}\n";
    }
}

$service = new PartidaService($root);
$partida = $service->nuevaPartida('debug_v0', 'smoke-test');

assertTrue(isset($partida['meta']['partida_id']), 'partida tiene id');
assertTrue(count($partida['bloque_a']['viviendas']) === 16, 'bloque A tiene 16 viviendas');
assertTrue(isset($partida['residentes']['per_i03']), 'Rocío incorporada día 1');
assertTrue($partida['residentes']['per_i03']['vivienda_id'] === 'A01', 'Rocío en A01');

$ocupadas = BloqueA::resumen($partida)['ocupadas'];
assertTrue($ocupadas === 1, 'solo 1 vivienda ocupada al inicio');

$ph = $service->crearResidentePlaceholderDev($partida);
assertTrue($ph['ok'] ?? false, 'placeholder dev creado');
assertTrue(($partida['residentes'][$ph['residente']['catalog_id']]['_placeholder'] ?? false) === true, 'marcado placeholder');

$agenda = AgendaEngine::resolverDia($partida, 'per_i03', 1);
assertTrue(count($agenda['slots']) === 24, 'agenda 24 slots');
$trabajo9 = $agenda['slots'][9]['ocupado'] ?? false;
assertTrue($trabajo9 === true, 'Rocío oficina ocupada 9h (lunes)');

$disp19 = AgendaEngine::estaDisponible($partida, 'per_i03', 1, 19);
assertTrue(($disp19['disponible'] ?? false) === true, 'Rocío libre 19h lunes');
$dispPh19 = AgendaEngine::estaDisponible($partida, $ph['residente']['catalog_id'], 1, 19);
assertTrue(($dispPh19['disponible'] ?? false) === true, 'placeholder libre 19h');

$cita = CitaEngine::programar(
    $partida,
    'per_i03',
    $ph['residente']['catalog_id'],
    1,
    19,
    'lug_cafeteria'
);
assertTrue($cita['ok'] ?? false, 'cita programada');

$citaDup = CitaEngine::programar(
    $partida,
    'per_i03',
    $ph['residente']['catalog_id'],
    1,
    19,
    'lug_cafeteria'
);
assertTrue(!($citaDup['ok'] ?? true), 'anti-doble-reserva');

$ocupadaPost = AgendaEngine::estaDisponible($partida, 'per_i03', 1, 19);
assertTrue(($ocupadaPost['disponible'] ?? true) === false, 'slot 19h ocupado tras cita');

Reloj::avanzarHoras($partida, 25);
assertTrue((int) $partida['reloj']['dia_pueblo'] === 2, 'reloj avanzó 1 día');

$service->guardar($partida);
$id = $partida['meta']['partida_id'];
$cargada = $service->cargar($id);
assertTrue($cargada['meta']['partida_id'] === $id, 'persistencia save/load');

$rel = RelacionEngine::upsertSocial($partida, 'per_i03', $ph['residente']['catalog_id'], 'conocidos', 1, true);
assertTrue($rel['ok'] ?? false, 'relación social');

$rom = RelacionEngine::upsertRomance($partida, 'per_i03', $ph['residente']['catalog_id'], ['vinculo' => 2]);
assertTrue($rom['ok'] ?? false, 'relación romance scaffold');

$ficha = $service->fichaResidente($partida, 'per_i03');
assertTrue($ficha['identidad']['nombre'] === 'Rocío', 'ficha Rocío');
assertTrue($ficha['_ui'] === 'provisional_v0', 'ficha UI provisional');

echo "\n---\n";
if ($failures > 0) {
    echo "SMOKE FALLIDO: {$failures} errores\n";
    exit(1);
}
echo "SMOKE OK — todos los tests pasaron\n";
exit(0);
