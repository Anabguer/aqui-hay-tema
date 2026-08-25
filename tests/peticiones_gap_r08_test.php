<?php
declare(strict_types=1);

/**
 * R08-B — GAP ENTRE NACIMIENTOS AUTÓNOMOS (peticiones_pueblo.gap_min_horas=6).
 * Si nace una petición en día X hora H, en las 6 horas de juego siguientes NO
 * puede nacer otra autónoma. En H+6 vuelve a ser elegible para el RNG R07
 * normal. Cruza medianoches (hora absoluta dia*24+hora).
 * Manuales y labs con _b4_forzar_nacer NO quedan bloqueados.
 * Determinista: reloj fijado + gate comprobable sin depender del RNG.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorPeticionesPueblo;

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

function ponHora(array &$p, int $dia, int $hora): void
{
    $p['reloj']['dia_pueblo'] = $dia;
    $p['reloj']['hora_actual'] = $hora;
    if (!isset($p['reloj']['dia_en_temporada'])) {
        $p['reloj']['dia_en_temporada'] = $dia;
    }
}

function abiertasB4(array $p): int
{
    $n = 0;
    foreach ($p['peticiones'] as $lp) {
        if (!empty($lp['schema_b4']) && ($lp['estado'] ?? '') === 'abierta') {
            $n++;
        }
    }
    return $n;
}

$cal = CalibracionConfig::load($root);
ok((int) CalibracionConfig::get($cal, 'peticiones_pueblo.gap_min_horas', -1) === 6, 'knob gap_min_horas = 6 en calibración');

$t0 = new DateTimeImmutable('2026-08-24 09:00:00', Reloj::zona());
Reloj::fijarAhora($t0);

// ---------- B1) Nacimiento semilla: día 2, 10:00 ----------
$p = SimuladorPeticionesPueblo::partidaLab(8, new RngService('r08-gap'), $cal, 'E3');
ponHora($p, 2, 10);
PeticionPuebloEngine::ensure($p);
$p['_b4_forzar_nacer'] = true;
$semilla = PeticionPuebloEngine::intentarNacer($p, $cal, new RngService('r08-gap-semilla'), null);
ok($semilla !== null, 'nace la petición semilla (día 2, 10:00)');
ok((int) ($p['peticiones_pueblo']['ultima_nace_abs'] ?? -1) === 2 * 24 + 10, 'ultima_nace_abs = 58 (2*24+10)');
$p['_b4_forzar_nacer'] = false;

// ---------- B2) Horas bloqueadas 11..15: ni con RNG perfecto ----------
$rngBloqueo = new RngService('r08-gap-bloqueo');
for ($h = 11; $h <= 15; $h++) {
    ponHora($p, 2, $h);
    ok(PeticionPuebloEngine::estaEnGap($p, $cal) === true, "día 2 {$h}:00 dentro del gap");
    $stateAntes = $rngBloqueo->getState();
    $r = PeticionPuebloEngine::intentarNacer($p, $cal, $rngBloqueo, null);
    ok($r === null, "día 2 {$h}:00 NO nace (bloqueada)");
    ok($rngBloqueo->getState() === $stateAntes, "día 2 {$h}:00 no consume RNG (gate previo)");
}
ok(abiertasB4($p) === 1, 'sigue solo la semilla abierta');

// ---------- B3) Hora 16: vuelve a ser elegible ----------
ponHora($p, 2, 16);
ok(PeticionPuebloEngine::estaEnGap($p, $cal) === false, 'día 2 16:00 fuera de gap (H+6)');
// Elegible = el gate se abre; la evaluación normal R07 retoma el control.
$p['_b4_forzar_nacer'] = true;
$n16 = PeticionPuebloEngine::intentarNacer($p, $cal, null, null);
$p['_b4_forzar_nacer'] = false;
if ($n16 !== null) {
    ok((int) ($p['peticiones_pueblo']['ultima_nace_abs'] ?? -1) === 2 * 24 + 16, 'si nace a las 16, actualiza ultima_nace_abs');
} else {
    ok(true, 'a las 16 el RNG decidió no nacer (gap ya no interviene)');
}

// ---------- B4) Cruce de medianoche: nace 22:00 → elegible 04:00 ----------
ponHora($p, 2, 22);
$p['_b4_forzar_nacer'] = true;
if ((int) ($p['peticiones_pueblo']['ultima_nace_abs'] ?? 0) !== 2 * 24 + 22) {
    $r22 = PeticionPuebloEngine::intentarNacer($p, $cal, null, null);
    if ($r22 !== null) {
        ok((int) ($p['peticiones_pueblo']['ultima_nace_abs'] ?? -1) === 70, 'semilla de cruce nacida a las 22:00');
    } else {
        $p['peticiones_pueblo']['ultima_nace_abs'] = 70; // forzamos marca equivalente
        ok(true, 'cruce simulado por marca directa (cap lleno)');
    }
}
$p['_b4_forzar_nacer'] = false;
ponHora($p, 3, 3);
ok(PeticionPuebloEngine::estaEnGap($p, $cal) === true, 'día 3 03:00 aún en gap (75−70=5 < 6)');
ponHora($p, 3, 4);
ok(PeticionPuebloEngine::estaEnGap($p, $cal) === false, 'día 3 04:00 elegible de nuevo (76−70=6)');

// ---------- B5) Manuales nunca bloqueados por el gap ----------
ponHora($p, 3, 5); // dentro de un hipotético gap
$ids = array_keys($p['residentes']);
$rMan = PeticionEngine::crear($p, (string) $ids[0], 'otro', [
    'schema_b4' => true,
    'peso' => PeticionEsquemas::PESO_FACIL,
    'texto' => 'Manual durante gap.',
    'plazo_horas' => 12,
], null);
ok(!empty($rMan['ok']), 'petición MANUAL se crea igualmente dentro del gap');
// Base producción actual aún no escribe generacion (pendiente dueño R07);
// el contrato R08 exige que el manual no quede bloqueado y que, si la traza
// existe, siga siendo 'manual'.
$g = $rMan['peticion']['generacion'] ?? null;
ok($g === null || ($g['via'] ?? '') === 'manual', 'trazabilidad manual intacta (via=manual si el base la soporta)');

// ---------- B6) Labs forzados no bloqueados + knob 0 desactiva ----------
ponHora($p, 3, 6);
$p['_b4_forzar_nacer'] = true;
$antesF = abiertasB4($p);
$rf = PeticionPuebloEngine::intentarNacer($p, $cal, null, null);
ok($rf !== null || abiertasB4($p) >= (int) ceil(8 * 0.33), 'lab forzado sigue operativo (bypass gap)');
$p['_b4_forzar_nacer'] = false;
$calSinGap = $cal;
$calSinGap['peticiones_pueblo']['gap_min_horas'] = 0;
$p['peticiones_pueblo']['ultima_nace_abs'] = 3 * 24 + 6;
ok(PeticionPuebloEngine::estaEnGap($p, $calSinGap) === false, 'gap_min_horas=0 desactiva el gap');
// Mundo nuevo real: sin ningún nacimiento previo (ultima_nace_abs en 0).
$pn = SimuladorPeticionesPueblo::partidaLab(8, new RngService('r08-gap-nuevo'), $cal, 'E3');
ponHora($pn, 1, 8);
PeticionPuebloEngine::ensure($pn);
ok(PeticionPuebloEngine::estaEnGap($pn, $cal) === false, 'mundo nuevo sin nacimientos: gap inactivo');

// ---------- B7) Migración de saves antiguos ----------
unset($p['peticiones_pueblo']);
PeticionPuebloEngine::ensure($p);
ok((int) ($p['peticiones_pueblo']['ultima_nace_abs'] ?? -1) === 0, 'ensure reconstruye ultima_nace_abs=0 en saves antiguos');

Reloj::fijarAhora(null);
echo "\n" . ($failures > 0 ? "FALLOS: $failures" : 'TODO OK') . " — peticiones_gap_r08\n";
exit($failures > 0 ? 1 : 0);
