<?php
declare(strict_types=1);

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

ok(CapacidadViviendas::CAP_PRODUCTO === 46, 'CAP_PRODUCTO = 46');

$p = $service->nuevaPartida('test_fixtures_v0', 'cap46-nueva');
ok(CapacidadViviendas::capacidadTotal($p) === 46, 'nueva partida cap 46');
ok(count($p['viviendas']['slots']) === 46, 'nueva partida pool 46 slots');
ok((int) ($p['celeste']['vivienda_capacidad_max'] ?? 0) === 46, 'celeste cap 46');

$estado = $service->estadoResumido($p);
ok((int) ($estado['pueblo_capacidad_max'] ?? 0) === 46, 'estado pueblo_capacidad_max 46');

// Migración save legacy cap 24 con ocupantes preservados
$pLegacy = $service->nuevaPartida('test_fixtures_v0', 'cap46-mig');
$mapViv = [];
while (count(TutorialIncorporaciones::residentesActivos($pLegacy)) < 24) {
    $r = $service->crearResidentePlaceholderDev($pLegacy);
    ok(($r['ok'] ?? false) === true, 'placeholder para migración');
}
foreach (TutorialIncorporaciones::residentesActivos($pLegacy) as $rid) {
    $mapViv[$rid] = (string) ($pLegacy['residentes'][$rid]['vivienda_id'] ?? '');
}
$pLegacy['viviendas']['slots'] = array_slice($pLegacy['viviendas']['slots'], 0, 24);
$pLegacy['viviendas']['cap'] = 24;
$pLegacy['celeste']['vivienda_capacidad_max'] = 24;
CapacidadViviendas::ensure($pLegacy);
ok(count($pLegacy['viviendas']['slots']) === 46, 'migración 24→46 slots');
ok(CapacidadViviendas::capacidadTotal($pLegacy) === 46, 'migración cap 46');
foreach ($mapViv as $rid => $vid) {
    if ($vid === '') {
        continue;
    }
    ok(
        (string) ($pLegacy['residentes'][$rid]['vivienda_id'] ?? '') === $vid,
        "residente $rid conserva vivienda $vid"
    );
}

// Residente 25 (primer slot C tras A+B)
$r25 = $service->crearResidentePlaceholderDev($pLegacy);
ok(($r25['ok'] ?? false) === true, 'residente 25 asignable');
ok(count(TutorialIncorporaciones::residentesActivos($pLegacy)) === 25, 'n=25 activos');
$vid25 = (string) ($r25['vivienda_id'] ?? '');
ok($vid25 !== '' && str_starts_with($vid25, 'C'), "residente 25 en slot C ($vid25)");

// Llenar hasta 46
while (count(TutorialIncorporaciones::residentesActivos($pLegacy)) < 46) {
    $rx = $service->crearResidentePlaceholderDev($pLegacy);
    if (!($rx['ok'] ?? false)) {
        ok(false, 'relleno hasta 46 (falló en n=' . count(TutorialIncorporaciones::residentesActivos($pLegacy)));
        break;
    }
}
ok(count(TutorialIncorporaciones::residentesActivos($pLegacy)) === 46, 'n=46 activos');
ok(CapacidadViviendas::huecos($pLegacy) === 0, 'sin huecos en n=46');

$r46 = $service->crearResidentePlaceholderDev($pLegacy);
ok(($r46['ok'] ?? false) !== true, 'residente 47 bloqueado');
ok(($r46['error'] ?? '') === 'bloque_a_lleno' || ($r46['error'] ?? '') === 'viviendas_llenas', 'error viviendas_llenas');

// Llegadas post-24: motor no bloquea por cap 24
$pPost = $service->nuevaPartida('test_fixtures_v0', 'cap46-lleg');
while (count(TutorialIncorporaciones::residentesActivos($pPost)) < 24) {
    $service->crearResidentePlaceholderDev($pPost);
}
$pPost['llegadas']['modo'] = 'normal';
$pPost['llegadas']['cooldown_hasta_dia'] = 0;
$pPost['llegadas']['candidato_activo'] = null;
$pPost['llegadas']['en_camino'] = null;
ok(CandidatoLlegadaEngine::gapMin(12) === 13, 'gap_min N=12');
ok(CandidatoLlegadaEngine::pDiaV3(12) > 0.09, 'p_dia N=12 moderada');
ok(CapacidadViviendas::huecos($pPost) === 22, 'huecos con n=24 (no bloqueo en 24)');
ok(
    count(TutorialIncorporaciones::residentesActivos($pPost)) < CapacidadViviendas::CAP_PRODUCTO,
    'n=24 por debajo del cap 46'
);

// Tutorial sigue terminando en 8 (smoke mínimo)
$js = file_get_contents($root . '/assets/js/play-v3.js');
ok(strpos($js, 'CELESTINE_CAP_VECINOS = 46') !== false, 'JS CELESTINE_CAP_VECINOS 46');
ok(strpos($js, 'En el pueblo') !== false, 'JS Celestine En el pueblo');
ok(strpos($js, 'metricasSociales(cacheInsp') !== false && strpos($js, ' / ') !== false, 'JS Vecinos N / cap');

echo $failures === 0 ? "OK capacidad_pueblo_46\n" : "FAIL capacidad_pueblo_46 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
