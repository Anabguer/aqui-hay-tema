<?php
declare(strict_types=1);

/**
 * Capacidad real del pueblo = 16 residentes simultáneos.
 * Ejecutar: php tests/capacidad_pueblo_16_test.php
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialIncorporaciones;

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

ok(CapacidadViviendas::CAP_PRODUCTO === 16, 'CAP_PRODUCTO = 16');
ok(CapacidadViviendas::capObjetivoPoblacionActiva() === 16, 'capObjetivo = 16');

$p = $service->nuevaPartida('test_fixtures_v0', 'cap16-nueva');
ok(CapacidadViviendas::capacidadTotal($p) === 16, 'capacidadTotal 16');
ok(count($p['viviendas']['slots']) === 16, 'pool jugable 16 slots A');
ok((int) ($p['celeste']['vivienda_capacidad_max'] ?? 0) === 16, 'celeste cap 16');
ok(!CapacidadViviendas::tienePoolLegacyAmpliado($p), 'sin pool legacy en partida nueva');

$estado = $service->estadoResumido($p);
ok((int) ($estado['pueblo_capacidad_max'] ?? 0) === 16, 'estado pueblo_capacidad_max 16');

// 1. Crecer hasta 16
while (count(TutorialIncorporaciones::residentesActivos($p)) < 16) {
    $r = $service->crearResidentePlaceholderDev($p);
    if (!($r['ok'] ?? false)) {
        ok(false, 'relleno hasta 16 (falló en n=' . count(TutorialIncorporaciones::residentesActivos($p)));
        break;
    }
}
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 16, '1: puede crecer hasta 16');

// 2. Con 16 activos, huecos = 0
ok(CapacidadViviendas::huecos($p) === 0, '2: huecos=0 con 16 activos');

// 3. Residente 17 bloqueado
$r17 = $service->crearResidentePlaceholderDev($p);
ok(($r17['ok'] ?? false) !== true, '3: residente 17 bloqueado');
ok(
    ($r17['error'] ?? '') === 'bloque_a_lleno' || ($r17['error'] ?? '') === 'viviendas_llenas',
    '3: error viviendas_llenas'
);
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 16, '3: sigue en 16 tras intento 17');

// 4–6. Marcha desde 16 → 15 + hueco → llegada → 16, no 17
$activos = TutorialIncorporaciones::residentesActivos($p);
$ridMarcha = (string) ($activos[0] ?? '');
$p['residentes'][$ridMarcha]['presencia'] = 'antiguo_residente';
CapacidadViviendas::liberarResidente($p, $ridMarcha);
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 15, '4: salida deja 15 activos');
ok(CapacidadViviendas::huecos($p) === 1, '4: 1 hueco tras salida');

$rLlegada = $service->crearResidentePlaceholderDev($p);
ok(($rLlegada['ok'] ?? false) === true, '5: nueva llegada rellena vacante');
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 16, '5: vuelve a 16');
$r18 = $service->crearResidentePlaceholderDev($p);
ok(($r18['ok'] ?? false) !== true, '6: no vuelve a 17');

// 7. UI / estado: contador coherente
$est2 = $service->estadoResumido($p);
ok((int) ($est2['pueblo_residentes_activos'] ?? 0) === 16, '7: estado 16 activos');
ok((int) ($est2['pueblo_capacidad_max'] ?? 0) === 16, '7: UI cap 16');

// 8. Save <=16 carga correctamente
$id = $service->guardar($p);
$loaded = $service->cargar($p['meta']['partida_id']);
ok(count(TutorialIncorporaciones::residentesActivos($loaded)) === 16, '8: save/load 16 activos');
ok(CapacidadViviendas::capacidadTotal($loaded) === 16, '8: cap tras load');

// Legacy: pool 46 en save no se recorta; cap funcional sigue 16
$pLegacy = $service->nuevaPartida('test_fixtures_v0', 'cap16-legacy-pool');
while (count(TutorialIncorporaciones::residentesActivos($pLegacy)) < 12) {
    $service->crearResidentePlaceholderDev($pLegacy);
}
$pLegacy['viviendas']['slots'] = array_merge(
    $pLegacy['viviendas']['slots'],
    array_map(static fn($i) => ['id' => 'B' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'ocupante_id' => null, 'estado' => 'libre'], range(1, 8))
);
for ($i = 1; $i <= 16; $i++) {
    $pLegacy['viviendas']['slots'][] = ['id' => 'C' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'ocupante_id' => null, 'estado' => 'libre'];
}
for ($i = 1; $i <= 6; $i++) {
    $pLegacy['viviendas']['slots'][] = ['id' => 'D' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'ocupante_id' => null, 'estado' => 'libre'];
}
$nAntes = count(TutorialIncorporaciones::residentesActivos($pLegacy));
CapacidadViviendas::ensure($pLegacy);
ok(count($pLegacy['viviendas']['slots']) === 46, 'legacy: conserva 46 slots en save');
ok(count(TutorialIncorporaciones::residentesActivos($pLegacy)) === $nAntes, 'legacy: no borra residentes');
ok(CapacidadViviendas::capacidadTotal($pLegacy) === 16, 'legacy: cap funcional 16');
ok(CapacidadViviendas::huecos($pLegacy) === max(0, 16 - $nAntes), 'legacy: huecos contra 16');

// Llegadas motor: frena en 16 (p_dia con N=16 es mínima)
ok(abs(CandidatoLlegadaEngine::pDiaV3(16) - 0.04) < 0.001, 'llegadas: p_dia mínima en cap');

$js = file_get_contents($root . '/assets/js/play-v3.js');
ok(strpos($js, 'capObjetivoPoblacionVisible') !== false, 'JS contador objetivo');
ok(strpos($js, 'CELESTINE_OBJETIVO_POBLACION_FALLBACK = 16') !== false, 'JS fallback 16');

echo $failures === 0 ? "OK capacidad_pueblo_16\n" : "FAIL capacidad_pueblo_16 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
