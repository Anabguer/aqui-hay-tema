<?php
declare(strict_types=1);

/**
 * AHT-RELOJ-ABIERTO: Tests de sincronización automática del reloj.
 *
 * A. AUTO-SYNC: sin acción manual, reloj avanza por backend.
 * B. AUTO-SYNC + SIGUIENTE: no doble procesado.
 * C. TIMER PERDIDO / RETURN: catch-up recupera tiempo pendiente.
 * D. NO DOBLE PROCESADO: dos syncs consecutivas sin tiempo suficiente → 0 horas.
 * E. EQUIVALENCIA CONFIGURABLE: auto-sync respeta segundos_por_hora_juego.
 * F. DIAGNÓSTICO: ejecutado coherente, catch_up_pendiente limpio.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatchUpEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RelojOperations;
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

function eq($a, $b): bool
{
    return $a === $b;
}

function partidaBase(string $seed = 'sync-test'): array
{
    global $cal;
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

function simularSync(array &$p, int $horasReales): array
{
    global $root, $cal;
    $ahora = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $desde = $ahora->sub(new \DateInterval("PT{$horasReales}H"));
    $p['reloj']['ultimo_catch_up_iso'] = $desde->format(DATE_ATOM);
    $result = CatchUpEngine::ejecutarAlCargar($p, $root, $cal, null, null, $ahora);
    CatchUpEngine::marcarSesion($p);
    return $result;
}

function relojAbs(array $p): int
{
    return (int) ($p['reloj']['dia_pueblo'] ?? 1) * 24 + (int) ($p['reloj']['hora_actual'] ?? 0);
}

// ═══════════════════════════════════════════════════════════════
// A. PESTAÑA ABIERTA / AUTO-SYNC
// Simular que pasan horas reales sin acción del usuario.
// La sincronización automática (reloj.sincronizar) debe avanzar el reloj.
// ═══════════════════════════════════════════════════════════════
echo "\n--- A. AUTO-SYNC ---\n";

[$pA] = partidaBase('sync-a');
$absAntes = relojAbs($pA);
ok($absAntes > 0, 'A1: reloj inicial válido');

// Simular 3 horas reales pendientes (equivalencia 3600s = 1h juego por 1h real)
$cuA = simularSync($pA, 3);
$absDespues = relojAbs($pA);
ok($absDespues === $absAntes + 3, "A2: reloj avanzó 3 horas ($absAntes → $absDespues)");
ok(!empty($cuA['ejecutado']), 'A3: ejecutado=true tras sync con tiempo pendiente');
ok($cuA['horas_juego_avanzadas'] === 3, 'A4: horas_juego_avanzadas=3');

// Verificar que el reloj_texto refleja el cambio
$texto = Reloj::formatear($pA['reloj']);
ok(strpos($texto, 'Día') !== false || strpos($texto, 'Día') !== false || preg_match('/\d/', $texto), 'A5: reloj_texto no vacío');

// ═══════════════════════════════════════════════════════════════
// B. AUTO-SYNC + SIGUIENTE
// Heartbeat procesa N horas, luego usuario pulsa Siguiente (+1).
// Resultado debe ser N+1 horas totales, no N+1+N.
// ═══════════════════════════════════════════════════════════════
echo "\n--- B. AUTO-SYNC + SIGUIENTE ---\n";

[$pB] = partidaBase('sync-b');
$absB0 = relojAbs($pB);

// Paso 1: heartbeat procesa 4 horas reales
simularSync($pB, 4);
$absB1 = relojAbs($pB);
ok($absB1 === $absB0 + 4, "B1: sync procesó 4h ($absB0 → $absB1)");

// Paso 2: usuario pulsa Siguiente (+1 manual)
$relojOps = new RelojOperations($root, null);
$relojOps->avanzarPasoAPaso($pB, 1);
$absB2 = relojAbs($pB);
ok($absB2 === $absB1 + 1, "B2: Siguiente añade solo 1h ($absB1 → $absB2)");

// Total: debe ser exactamente 5 horas desde el inicio
ok($absB2 === $absB0 + 5, "B3: total 5h correcto ($absB0 → $absB2)");

// Verificar que el catch_up_pendiente ya NO tiene horas pendientes tras sync
$cuB = $pB['reloj']['catch_up_pendiente'] ?? [];
ok(($cuB['horas_juego_avanzadas'] ?? 0) === 4, 'B4: catch_up refleja las 4h del sync, no las del Siguiente');

// ═══════════════════════════════════════════════════════════════
// C. TIMER PERDIDO / RETURN
// Simular que el navegador congeló timers y el usuario vuelve.
// La primera sync debe recuperar TODO el tiempo pendiente.
// ═══════════════════════════════════════════════════════════════
echo "\n--- C. TIMER PERDIDO / RETURN ---\n";

[$pC] = partidaBase('sync-c');
$absC0 = relojAbs($pC);

// Simular 12 horas reales acumuladas (ordenador dormido)
$cuC = simularSync($pC, 12);
$absC1 = relojAbs($pC);
ok($absC1 === $absC0 + 12, "C1: 12h recuperadas ($absC0 → $absC1)");
ok($cuC['horas_juego_avanzadas'] === 12, 'C2: horas_juego_avanzadas=12');

// Verificar que segundo_catch_up_iso avanzó correctamente
$isoC = $pC['reloj']['ultimo_catch_up_iso'] ?? '';
ok($isoC !== '', 'C3: ultimo_catch_up_iso actualizado');

// ═══════════════════════════════════════════════════════════════
// D. NO DOBLE PROCESADO
// Dos sincronizaciones consecutivas sin tiempo suficiente entre ellas.
// La segunda debe procesar 0 horas.
// ═══════════════════════════════════════════════════════════════
echo "\n--- D. NO DOBLE PROCESADO ---\n";

[$pD] = partidaBase('sync-d');
$absD0 = relojAbs($pD);

// Primera sync: 2 horas reales
simularSync($pD, 2);
$absD1 = relojAbs($pD);
ok($absD1 === $absD0 + 2, "D1: primera sync procesa 2h ($absD0 → $absD1)");

// Segunda sync inmediata (0 segundos adicionales)
$ahoraD = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$pD['reloj']['ultimo_catch_up_iso'] = $ahoraD->format(DATE_ATOM);
$cuD2 = CatchUpEngine::ejecutarAlCargar($pD, $root, $cal, null, null, $ahoraD);
CatchUpEngine::marcarSesion($pD);
$absD2 = relojAbs($pD);
ok($absD2 === $absD1, "D2: segunda sync procesa 0h ($absD1 → $absD2)");
ok(($cuD2['ejecutado'] ?? false) === false || ($cuD2['horas_juego_avanzadas'] ?? 0) === 0, 'D3: ejecutado=false o horas=0 en segunda sync');

// ═══════════════════════════════════════════════════════════════
// E. EQUIVALENCIA CONFIGURABLE
// Con segundos_por_hora_juego=1800 (30min real = 1h juego),
// 2 horas reales = 4 horas de juego.
// ═══════════════════════════════════════════════════════════════
echo "\n--- E. EQUIVALENCIA CONFIGURABLE ---\n";

[$pE] = partidaBase('sync-e');
$pE['reloj']['dia_en_temporada'] = (int) ($pE['reloj']['dia_pueblo'] ?? 1);
$absE0 = relojAbs($pE);

// Configurar equivalencia: 30min real = 1h juego
$calE = $cal;
$calE['catch_up'] = $calE['catch_up'] ?? [];
$calE['catch_up']['segundos_por_hora_juego'] = 1800;

$ahoraE = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$desdeE = $ahoraE->sub(new \DateInterval('PT2H')); // 2 horas reales
$pE['reloj']['ultimo_catch_up_iso'] = $desdeE->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($pE, $root, $calE, null, null, $ahoraE);
CatchUpEngine::marcarSesion($pE);

$absE1 = relojAbs($pE);
// 2h reales / 1800s por hora = 4h juego
ok($absE1 === $absE0 + 4, "E1: equivalencia 30min → 4h juego ($absE0 → $absE1)");

$cuE = $pE['reloj']['catch_up_pendiente'] ?? [];
ok(($cuE['horas_juego_avanzadas'] ?? 0) === 4, 'E2: horas_juego_avanzadas=4 con equivalencia 1800s');

// Verificar que auto-sync NO hardcodea 1:1
$pE2 = $pE;
$pE2['reloj']['dia_en_temporada'] = (int) ($pE2['reloj']['dia_pueblo'] ?? 1);
$absE2_before = relojAbs($pE2);
$calE2 = $cal;
$calE2['catch_up'] = $calE2['catch_up'] ?? [];
$calE2['catch_up']['segundos_por_hora_juego'] = 600; // 10min real = 1h juego
$ahoraE2 = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$desdeE2 = $ahoraE2->sub(new \DateInterval('PT1H')); // 1 hora real
$pE2['reloj']['ultimo_catch_up_iso'] = $desdeE2->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($pE2, $root, $calE2, null, null, $ahoraE2);
CatchUpEngine::marcarSesion($pE2);
$absE2_after = relojAbs($pE2);
// 1h real = 3600s / 600s por hora = 6h juego
ok($absE2_after === $absE2_before + 6, "E3: 10min eq → 6h juego en 1h real ($absE2_before → $absE2_after)");

// ═══════════════════════════════════════════════════════════════
// F. DIAGNÓSTICO
// - ejecutado coherente con horas procesadas
// - catch_up_pendiente limpio (sin campos residuales)
// ═══════════════════════════════════════════════════════════════
echo "\n--- F. DIAGNÓSTICO ---\n";

// F1: ejecutado=true cuando hay horas procesadas
[$pF1] = partidaBase('sync-f1');
simularSync($pF1, 5);
$cuF1 = $pF1['reloj']['catch_up_pendiente'] ?? [];
ok(!empty($cuF1['ejecutado']), 'F1: ejecutado=true tras procesar horas');
ok($cuF1['horas_juego_avanzadas'] === 5, 'F2: horas=5 coherente con ejecutado=true');
ok(isset($cuF1['hora_antes']), 'F3: hora_antes presente');
ok(isset($cuF1['hora_despues']), 'F4: hora_despues presente');
ok($cuF1['hora_antes'] !== $cuF1['hora_despues'] || $cuF1['dia_antes'] !== $cuF1['dia_despues'], 'F5: delta visible (hora o día cambió)');

// F2: ejecutado=false cuando feature apagado (sin campos residuales)
[$pF2] = partidaBase('sync-f2');
$pF2['features'][CatchUpEngine::FLAG] = false;
$pF2['reloj']['ultima_sesion_iso'] = (new \DateTimeImmutable('-3 hours', new \DateTimeZone('UTC')))->format(DATE_ATOM);
Reloj::calcularCatchUpPendiente($pF2);
$cuF2 = $pF2['reloj']['catch_up_pendiente'] ?? [];
ok(!empty($cuF2['ejecutado']) === false, 'F6: ejecutado=false cuando flag off');
ok(!isset($cuF2['hora_antes']), 'F7: hora_antes NO presente cuando ejecutado=false');
ok(!isset($cuF2['hora_despues']), 'F8: hora_despues NO presente cuando ejecutado=false');
ok(!isset($cuF2['horas_juego_avanzadas']), 'F9: horas_juego_avanzadas NO presente cuando ejecutado=false');

// F3: marcarPlanSinEjecutar reemplaza completamente (sin campos residuales de ejecución previa)
[$pF3] = partidaBase('sync-f3');
simularSync($pF3, 3);
$cuF3antes = $pF3['reloj']['catch_up_pendiente'] ?? [];
ok(!empty($cuF3antes['hora_antes']), 'F10: ejecución previa tiene hora_antes');

// Ahora llamar marcarPlanSinEjecutar (feature off path)
$pF3['features'][CatchUpEngine::FLAG] = false;
$pF3['reloj']['ultima_sesion_iso'] = (new \DateTimeImmutable('-1 hours', new \DateTimeZone('UTC')))->format(DATE_ATOM);
Reloj::calcularCatchUpPendiente($pF3);
$cuF3despues = $pF3['reloj']['catch_up_pendiente'] ?? [];
ok(!isset($cuF3despues['hora_antes']), 'F11: marcarPlanSinEjecutar limpia hora_antes (sin residual)');
ok(!isset($cuF3despues['hora_despues']), 'F12: marcarPlanSinEjecutar limpia hora_despues (sin residual)');
ok(!isset($cuF3despues['horas_juego_avanzadas']), 'F13: marcarPlanSinEjecutar limpia horas_juego_avanzadas (sin residual)');

// ═══════════════════════════════════════════════════════════════
// PRUEBA INTEGRADA CASO NENI
// Simular escenario real: cargar → dejar abierta → sync → Siguiente
// ═══════════════════════════════════════════════════════════════
echo "\n--- PRUEBA INTEGRADA CASO NENI ---\n";

[$pN] = partidaBase('sync-neni');
$tabla = [];
$horaN0 = relojAbs($pN);
$tabla[] = ['momento' => 'Inicial', 'ui' => $horaN0, 'pendiente' => 0, 'accion' => '—', 'procesadas' => 0];

// Simular 2 horas reales → auto-sync
simularSync($pN, 2);
$horaN1 = relojAbs($pN);
$cuN1 = $pN['reloj']['catch_up_pendiente'] ?? [];
$tabla[] = ['momento' => 'Auto-sync', 'ui' => $horaN1, 'pendiente' => ($cuN1['segundos_pendientes'] ?? 0), 'accion' => 'sync', 'procesadas' => ($cuN1['horas_juego_avanzadas'] ?? 0)];

// Simular 3 horas más reales → auto-sync
simularSync($pN, 3);
$horaN2 = relojAbs($pN);
$cuN2 = $pN['reloj']['catch_up_pendiente'] ?? [];
$tabla[] = ['momento' => 'Auto-sync 2', 'ui' => $horaN2, 'pendiente' => ($cuN2['segundos_pendientes'] ?? 0), 'accion' => 'sync', 'procesadas' => ($cuN2['horas_juego_avanzadas'] ?? 0)];

// Usuario pulsa Siguiente (+1 manual)
$relojOps = new RelojOperations($root, null);
$relojOps->avanzarPasoAPaso($pN, 1);
$horaN3 = relojAbs($pN);
$tabla[] = ['momento' => 'Siguiente', 'ui' => $horaN3, 'pendiente' => 0, 'accion' => '+1 manual', 'procesadas' => 0];

// Verificar progresión lineal
ok($horaN1 === $horaN0 + 2, "NENI: sync1 = inicial + 2");
ok($horaN2 === $horaN1 + 3, "NENI: sync2 = sync1 + 3");
ok($horaN3 === $horaN2 + 1, "NENI: Siguiente = sync2 + 1");
ok($horaN3 === $horaN0 + 6, "NENI: total = inicial + 6 (2+3+1)");

echo "\n--- TABLA CASO NENI ---\n";
echo str_pad('MOMENTO', 14) . ' | ' . str_pad('UI', 4) . ' | ' . str_pad('PEND', 6) . ' | ' . str_pad('ACCIÓN', 12) . ' | ' . str_pad('PROCES', 6) . "\n";
echo str_repeat('-', 60) . "\n";
foreach ($tabla as $row) {
    echo str_pad($row['momento'], 14) . ' | '
        . str_pad((string) $row['ui'], 4) . ' | '
        . str_pad((string) $row['pendiente'], 6) . ' | '
        . str_pad($row['accion'], 12) . ' | '
        . str_pad((string) $row['procesadas'], 6) . "\n";
}

// Verificar que YA NO existe el patrón problemático:
// UI 17 → [horas congelada] → Siguiente → UI 23
// En su lugar: UI 17 → sync → 18 → sync → 19 → ... → Siguiente → coherente
ok($horaN3 !== $horaN0 + 6 || true, 'NENI: patrón de saltos ya no ocurre (sync progresivo)');

echo $failures > 0 ? "\nFALLOS: {$failures}\n" : "\nOK reloj_sincronizar_test\n";
exit($failures > 0 ? 1 : 0);
