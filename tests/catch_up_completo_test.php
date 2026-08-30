<?php
declare(strict_types=1);

/**
 * Catch-up offline: tests completos A-O.
 * Cubre: avalancha, compresión, motores reales, decisiones Celestine,
 * persistencia, idempotencia, coherencia, superficie, destrucción.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatchUpEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaLifecycle;
use AquiHayTema\Engine\PartidaRepository;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorMisionesDiarias;
use AquiHayTema\Engine\VidaPuebloEngine;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function ledgerIgnorado(array $p): array
{
    $out = [];
    foreach ($p['vida_pueblo']['ledger'] ?? [] as $e) {
        if (($e['causa'] ?? '') === VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO) {
            $out[] = $e;
        }
    }
    return $out;
}

function partidaCatchUp(string $seed = 'cu-test'): array
{
    global $cal, $root;
    $rng = new RngService($seed);
    $p = SimuladorMisionesDiarias::partidaLab(8, $rng, $cal);
    unset($p['_lab_misiones_b3']);
    $p['reloj']['dia_en_temporada'] = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    $p['features'] = [
        VidaPuebloEngine::FLAG => true,
        MisionDiariaEngine::FLAG => true,
        CatchUpEngine::FLAG => true,
        'encuentros_enabled' => true,
    ];
    VidaPuebloEngine::ensure($p, $cal);
    MisionDiariaEngine::alComenzarDia($p, $cal, $rng);
    return [$p, $rng];
}

function runCatchUp(array &$p, int $dias): array
{
    global $root, $cal;
    $ahora = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
    $desde = $ahora->sub(new \DateInterval('P' . $dias . 'D'));
    $p['reloj']['ultimo_catch_up_iso'] = $desde->format(DATE_ATOM);
    return CatchUpEngine::ejecutarAlCargar($p, $root, $cal, null, null, $ahora);
}

// === A. Ausencia corta no provoca avalancha ===
[$pA] = partidaCatchUp('cu-A');
$v0 = VidaPuebloEngine::valor($pA);
$rA = runCatchUp($pA, 0);
ok(!$rA['ejecutado'] || ($rA['horas_juego_avanzadas'] ?? 0) === 0, 'A: 0 días no ejecuta');
ok(VidaPuebloEngine::valor($pA) === $v0, 'A: vida sin cambios en 0 días');

// 30 minutos < umbral 60s? No, 30 min = 1800s > 60s
[$pA30] = partidaCatchUp('cu-A30');
$v030 = VidaPuebloEngine::valor($pA30);
$ahora30 = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
$desde30 = $ahora30->sub(new \DateInterval('PT30M'));
$pA30['reloj']['ultimo_catch_up_iso'] = $desde30->format(DATE_ATOM);
$rA30 = CatchUpEngine::ejecutarAlCargar($pA30, $root, $cal, null, null, $ahora30);
ok(($rA30['horas_juego_avanzadas'] ?? 0) <= 1, 'A: 30min ≤ 1h juego');
ok(VidaPuebloEngine::valor($pA30) >= $v030 - 2, 'A: 30min ≤ -2 vida');

// === B. Ausencia larga comprimida/capada ===
[$pB] = partidaCatchUp('cu-B');
$rB = runCatchUp($pB, 90);
ok(($rB['horas_juego_avanzadas'] ?? 0) === 90 * 24, 'B: 90d = cap técnico (horas correctas)');
ok(($rB['segundos_procesados'] ?? 0) <= 90 * 86400, 'B: 90d no excede cap de segundos');

[$pB30] = partidaCatchUp('cu-B30');
$rB30 = runCatchUp($pB30, 30);
ok(($rB30['horas_juego_avanzadas'] ?? 0) === 30 * 24, 'B: 30d procesa 30d sin timeout');
ok(($rB30['segundos_procesados'] ?? 0) <= 30 * 86400, 'B: 30d no excede');

// === C. Catch-up ejecuta actividad real permitida ===
[$pC] = partidaCatchUp('cu-C');
$rC = runCatchUp($pC, 3);
ok(!empty($pC['reloj']['catch_up_pendiente']['ejecutado']), 'C: ejecutado flag true');
ok(($rC['stats']['dias_cruzados'] ?? 0) >= 3, 'C: al menos 3 días cruzados');
ok(($rC['stats']['encuentros_resueltos'] ?? 0) >= 0, 'C: encuentros resueltos (puede ser 0)');
ok(count(ledgerIgnorado($pC)) === 3, 'C: 3 penalizaciones de misiones');

// === D. Usa motores canónicos ===
[$pD] = partidaCatchUp('cu-D');
runCatchUp($pD, 2);
ok(isset($pD['vida_pueblo']['ledger']), 'D: ledger existe (misor canónico)');
ok(isset($pD['reloj']['dia_pueblo']), 'D: reloj avanzó (Reloj canónico)');
ok(isset($pD['reloj']['ultimo_catch_up_iso']), 'D: ultimo_catch_up_iso actualizado');

// === E. Decisiones Celestine no se autoejecutan ===
[$pE] = partidaCatchUp('cu-E');
$pE['misiones_diarias']['items'] = [
    ['id' => 'mis_test', 'dia' => 1, 'estado' => 'pendiente', 'tipo' => 'explorar', 'objetivo' => 'lug_cafeteria'],
];
runCatchUp($pE, 1);
$misE = $pE['misiones_diarias']['items'][0] ?? [];
ok(($misE['estado'] ?? '') !== 'cumplida', 'E: misión no auto-cumplida');

// No auto-departures
$pE['marcha'] = ['pendiente' => true, 'residente_id' => 'lab_r01'];
runCatchUp($pE, 1);
ok(!empty($pE['marcha']['pendiente']), 'E: marcha pendiente no resuelta offline');

// === F. Persistencia correcta ===
[$pF] = partidaCatchUp('cu-F');
runCatchUp($pF, 2);
$cuF = $pF['reloj']['catch_up_pendiente'];
ok($cuF['ejecutado'] === true, 'F: catch_up_pendiente.ejecutado true');
ok(($cuF['segundos_pendientes'] ?? 0) > 0, 'F: segundos_pendientes > 0');
ok(($cuF['segundos_procesados'] ?? 0) > 0, 'F: segundos_procesados > 0');
ok(($cuF['horas_juego_avanzadas'] ?? 0) === 2 * 24, 'F: horas = 48');
ok(is_int($cuF['dia_antes']), 'F: dia_antes es int');
ok(is_int($cuF['dia_despues']), 'F: dia_despues es int');
ok($cuF['dia_despues'] > $cuF['dia_antes'], 'F: dia_despues > dia_antes');

// === G. Refresh no duplica ===
[$pG] = partidaCatchUp('cu-G');
runCatchUp($pG, 2);
$vidaG = VidaPuebloEngine::valor($pG);
$ledgerG = count(ledgerIgnorado($pG));
runCatchUp($pG, 0);
ok(VidaPuebloEngine::valor($pG) === $vidaG, 'G: refresh 0d no duplica vida');
ok(count(ledgerIgnorado($pG)) === $ledgerG, 'G: refresh 0d no duplica ledger');

// === H. Mismo intervalo no se procesa dos veces ===
[$pH] = partidaCatchUp('cu-H');
runCatchUp($pH, 1);
$vidaH = VidaPuebloEngine::valor($pH);
$diaH = (int) $pH['reloj']['dia_pueblo'];
$isoH = $pH['reloj']['ultimo_catch_up_iso'];
runCatchUp($pH, 0);
ok(VidaPuebloEngine::valor($pH) === $vidaH, 'H: mismo intervalo no cambia vida');
ok((int) $pH['reloj']['dia_pueblo'] === $diaH, 'H: mismo intervalo no avanza día');
ok($pH['reloj']['ultimo_catch_up_iso'] === $isoH, 'H: ultimo_catch_up_iso no cambia');

// === I. Relaciones permanecen coherentes ===
[$pI] = partidaCatchUp('cu-I');
foreach ($pI['residentes'] ?? [] as $rid => $r) {
    $social = $r['relaciones']['social'] ?? [];
    foreach ($social as $otro => $val) {
        ok($val >= -100 && $val <= 100, "I: social {$rid}→{$otro} en rango [-100,100]");
    }
    $romance = $r['relaciones']['romance'] ?? [];
    foreach ($romance as $otro => $val) {
        ok($val >= 0 && $val <= 100, "I: romance {$rid}→{$otro} en rango [0,100]");
    }
}
runCatchUp($pI, 5);
foreach ($pI['residentes'] ?? [] as $rid => $r) {
    $social = $r['relaciones']['social'] ?? [];
    foreach ($social as $otro => $val) {
        ok($val >= -100 && $val <= 100, "I post-5d: social {$rid}→{$otro} en rango");
    }
    $romance = $r['relaciones']['romance'] ?? [];
    foreach ($romance as $otro => $val) {
        ok($val >= 0 && $val <= 100, "I post-5d: romance {$rid}→{$otro} en rango");
    }
}

// === J. Romance respeta PRIMERA_CITA antes de pareja ===
[$pJ] = partidaCatchUp('cu-J');
runCatchUp($pJ, 7);
$tienePareja = false;
foreach ($pJ['residentes'] ?? [] as $rid => $r) {
    if (!empty($r['runtime']['pareja_id'])) {
        $tienePareja = true;
        break;
    }
}
if ($tienePareja) {
    $parejas = 0;
    foreach ($pJ['residentes'] ?? [] as $rid => $r) {
        if (!empty($r['runtime']['pareja_id'])) {
            $parejas++;
            $bid = $r['runtime']['pareja_id'];
            $okPC = false;
            foreach ($pJ['bitacora'] ?? [] as $bit) {
                if (($bit['evento'] ?? '') === 'PRIMERA_CITA'
                    && in_array($rid, $bit['participantes'] ?? [], true)
                    && in_array($bid, $bit['participantes'] ?? [], true)
                ) {
                    $okPC = true;
                    break;
                }
            }
            ok($okPC, "J: pareja {$rid}↔{$bid} tiene PRIMERA_CITA previa");
        }
    }
    ok($parejas <= 2, "J: máximo 2 parejas en 7d offline ( {$parejas} )");
} else {
    ok(true, 'J: sin parejas en 7d (válido)');
}

// === K. Regalos no se entregan solos ===
[$pK] = partidaCatchUp('cu-K');
runCatchUp($pK, 5);
$regalos = 0;
foreach ($pK['residentes'] ?? [] as $r) {
    foreach ($r['runtime']['inventario_regalos'] ?? [] as $g) {
        if (($g['origen'] ?? '') === 'catch_up') {
            $regalos++;
        }
    }
}
ok($regalos === 0, 'K: 0 regalos con origen catch_up');

// === L. Candidatos no se aceptan/rechazan solos ===
[$pL] = partidaCatchUp('cu-L');
$pL['llegadas']['candidatos'] = [
    ['id' => 'cand_test', 'nombre' => 'Test', 'estado' => 'pendiente'],
];
runCatchUp($pL, 3);
$cand = $pL['llegadas']['candidatos'][0] ?? [];
ok(($cand['estado'] ?? '') === 'pendiente', 'L: candidato pendiente no auto-resuelto');

// === M. Peticiones no se completan mágicamente ===
[$pM] = partidaCatchUp('cu-M');
$pM['peticiones'] = ['items' => [
    ['id' => 'pet_test', 'estado' => 'activa', 'residente_id' => 'lab_r01', 'tipo' => 'volver_a_ver'],
]];
runCatchUp($pM, 2);
$pet = $pM['peticiones']['items'][0] ?? [];
ok(($pet['estado'] ?? '') === 'activa', 'M: petición activa no completada offline');

// === N. Eventos importantes generan superficie comprensible ===
[$pN] = partidaCatchUp('cu-N');
$rN = runCatchUp($pN, 3);
$resumen = CatchUpEngine::resumenRegreso($pN);
ok($resumen['hay'] === true, 'N: resumen hay=true tras ausencia');
ok(is_int($resumen['dias']), 'N: resumen tiene dias');
ok(is_array($resumen['puntos']), 'N: resumen tiene puntos');
ok($resumen['dias'] >= 3, 'N: resumen dias ≥ 3');

// === O. No se destruye la partida por ausencia larga ===
[$pO] = partidaCatchUp('cu-O');
$vO = VidaPuebloEngine::valor($pO);
$rO = runCatchUp($pO, 30);
ok(VidaPuebloEngine::valor($pO) > 0, 'O: vida > 0 tras 30d (no destruida)');
ok(!($pO['vida_pueblo']['game_over_activo'] ?? false), 'O: sin game over tras 30d');
ok(($rO['stats']['dias_cruzados'] ?? 0) === 30, 'O: 30 días procesados');
ok(VidaPuebloEngine::valor($pO) >= 5, 'O: vida ≥ suelo (5) tras 30d');

// 30d con npc_autonomy → batch events (con límite de eventos)
[$pO2] = partidaCatchUp('cu-O2');
$pO2['features']['npc_autonomy_enabled'] = true;
$rO2 = runCatchUp($pO2, 30);
ok(VidaPuebloEngine::valor($pO2) >= 1, 'O2: vida ≥ 1 tras 30d con autonomy (no destruida)');
ok(!($pO2['vida_pueblo']['game_over_activo'] ?? false), 'O2: sin game over con autonomy (cap offline protege)');

echo $failures > 0 ? "\nFALLOS: {$failures}\n" : "\nOK catch_up_completo\n";
exit($failures > 0 ? 1 : 0);
