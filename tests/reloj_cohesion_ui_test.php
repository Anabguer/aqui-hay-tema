<?php
declare(strict_types=1);

/**
 * AHT-RELOJ-ABIERTO: Tests de coherencia UI — hay_cambios_visibles flag.
 *
 * Caso 1: Solo reloj (sin cambios visibles) → flag false.
 * Caso 2: Encuentro generado → flag true.
 * Caso 3: Cambio narrativo (misiones caducan) → flag true.
 * Caso 4: Dos syncs sin novedad → flag false en segundo sync.
 * Caso 5: Visibility return (acumular tiempo, sync una vez).
 * Caso 6: Siguiente después de autosync (no doble avance).
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

function relojAbs(array $p): int
{
    return (int) ($p['reloj']['dia_pueblo'] ?? 1) * 24 + (int) ($p['reloj']['hora_actual'] ?? 0);
}

function partidaBase(string $seed = 'coh-test'): array
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
        'buzon_enabled' => true,
        'mensajitos_espontaneos_enabled' => true,
        'npc_autonomy_enabled' => true,
    ];
    VidaPuebloEngine::ensure($p, $cal);
    MisionDiariaEngine::alComenzarDia($p, $cal, $rng);
    return [$p, $rng];
}

/**
 * Simula el mismo flujo que reloj.sincronizar: catch-up + return de datos.
 * Devuelve [resultado_catch_up, datos_response].
 */
function simularSync(array &$p, int $horasReales): array
{
    global $root, $cal;
    $ahora = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $desde = $ahora->sub(new \DateInterval("PT{$horasReales}H"));
    $p['reloj']['ultimo_catch_up_iso'] = $desde->format(DATE_ATOM);
    $cuResult = CatchUpEngine::ejecutarAlCargar($p, $root, $cal, null, null, $ahora);
    CatchUpEngine::marcarSesion($p);

    $cu = $p['reloj']['catch_up_pendiente'] ?? [];
    $horasProcesadas = (int) ($cu['horas_juego_avanzadas'] ?? 0);
    $ejecutado = !empty($cu['ejecutado']);
    $response = [
        'ok' => true,
        'reloj_vista' => Reloj::vista($p['reloj']),
        'reloj_texto' => Reloj::formatear($p['reloj']),
        'hay_cambios_visibles' => $ejecutado && $horasProcesadas > 0,
        'catch_up' => [
            'ejecutado' => $ejecutado,
            'horas_procesadas' => $horasProcesadas,
            'encuentros_offline' => (int) ($cu['encuentros_offline'] ?? 0),
            'eventos_offline' => (int) ($cu['eventos_offline'] ?? 0),
            'salidas_offline' => (int) ($cu['salidas_offline'] ?? 0),
        ],
    ];
    return [$cuResult, $response];
}

// ═══════════════════════════════════════════════════════════════
// CASO 1 — SOLO RELOJ: sync sin tiempo suficiente → flag false
// ═══════════════════════════════════════════════════════════════
echo "\n--- CASO 1: Solo reloj (sin cambios visibles) ---\n";

[$p1] = partidaBase('coh-1');
$abs1a = relojAbs($p1);

// Sync con 30 segundos (bajo umbral de 60s) → no ejecuta catch-up
$ahora1 = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$desde1 = $ahora1->sub(new \DateInterval('PT30S'));
$p1['reloj']['ultimo_catch_up_iso'] = $desde1->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($p1, $root, $cal, null, null, $ahora1);
CatchUpEngine::marcarSesion($p1);
$cu1 = $p1['reloj']['catch_up_pendiente'] ?? [];
$ejecutado1 = !empty($cu1['ejecutado']);
$horas1 = (int) ($cu1['horas_juego_avanzadas'] ?? 0);
$hayCambios1 = $ejecutado1 && $horas1 > 0;

ok(!$hayCambios1, 'C1-1: hay_cambios_visibles=false con sync de 30s (bajo umbral)');
ok(relojAbs($p1) === $abs1a, 'C1-2: reloj no cambió');
ok(!$ejecutado1, 'C1-3: ejecutado=false (bajo umbral)');

// Sync con 50 segundos → también bajo umbral
[$p1b] = partidaBase('coh-1b');
$abs1b = relojAbs($p1b);
$ahora1b = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$desde1b = $ahora1b->sub(new \DateInterval('PT50S'));
$p1b['reloj']['ultimo_catch_up_iso'] = $desde1b->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($p1b, $root, $cal, null, null, $ahora1b);
CatchUpEngine::marcarSesion($p1b);
$cu1b = $p1b['reloj']['catch_up_pendiente'] ?? [];
$hayCambios1b = !empty($cu1b['ejecutado']) && (int) ($cu1b['horas_juego_avanzadas'] ?? 0) > 0;
ok(!$hayCambios1b, 'C1-4: hay_cambios_visibles=false con sync de 50s');

// ═══════════════════════════════════════════════════════════════
// CASO 2 — ENCUENTRO GENERADO: sync con tiempo → flag true
// ═══════════════════════════════════════════════════════════════
echo "\n--- CASO 2: Encuentro generado ---\n";

[$p2] = partidaBase('coh-2');
$p2['reloj']['hora_actual'] = 10;
$encId = 'enc_coh2';
$dia2 = (int) ($p2['reloj']['dia_pueblo'] ?? 1);
$p2['encuentros'] = [[
    'id' => $encId,
    'estado' => 'programado',
    'dia' => $dia2,
    'hora' => 12,
    'duracion_horas' => 2,
    'participantes' => ['lab_r01', 'lab_r02'],
    'lugar' => 'lug_cafeteria',
    'tipo' => 'quedar',
    'intencion' => 'celeste_organizado',
]];

[, $resp2] = simularSync($p2, 3);
ok($resp2['hay_cambios_visibles'] === true, 'C2-1: hay_cambios_visibles=true con 3h de catch-up');
ok($resp2['catch_up']['horas_procesadas'] === 3, 'C2-2: horas_procesadas=3');
ok(relojAbs($p2) === $dia2 * 24 + 13, 'C2-3: reloj avanzó a hora 13');

// Verificar que el encounter cambió de estado
$encDesp = null;
foreach ($p2['encuentros'] as $enc) {
    if ($enc['id'] === $encId) { $encDesp = $enc['estado']; break; }
}
ok($encDesp === 'en_curso', 'C2-4: encuentro pasó a en_curso (cambio visible)');

// ═══════════════════════════════════════════════════════════════
// CASO 3 — CAMBIO NARRATIVO: encuentro se resuelve → flag true
// (misiones solo caducan en cambio de día; usamos encuentro como
//  proxy de cambio narrativo visible)
// ═══════════════════════════════════════════════════════════════
echo "\n--- CASO 3: Cambio narrativo (encuentro resuelve) ---\n";

[$p3] = partidaBase('coh-3');
$p3['reloj']['hora_actual'] = 8;
$encId3 = 'enc_coh3';
$dia3 = (int) ($p3['reloj']['dia_pueblo'] ?? 1);
$p3['encuentros'] = [[
    'id' => $encId3,
    'estado' => 'programado',
    'dia' => $dia3,
    'hora' => 10,
    'duracion_horas' => 2,
    'participantes' => ['lab_r01', 'lab_r02'],
    'lugar' => 'lug_cafeteria',
    'tipo' => 'quedar',
    'intencion' => 'celeste_organizado',
]];

[, $resp3] = simularSync($p3, 5);
ok($resp3['hay_cambios_visibles'] === true, 'C3-1: hay_cambios_visibles=true tras catch-up de 5h');

$encEstado3 = null;
foreach ($p3['encuentros'] as $enc) {
    if ($enc['id'] === $encId3) { $encEstado3 = $enc['estado']; break; }
}
ok($encEstado3 === 'terminado', "C3-2: encuentro terminado ($encEstado3) — cambio narrativo visible");

// ═══════════════════════════════════════════════════════════════
// CASO 4 — DOS SYNCS SIN NOVEDAD
// Primer sync procesa tiempo → flag true.
// Segundo sync inmediato sin nuevo tiempo → flag false.
// ═══════════════════════════════════════════════════════════════
echo "\n--- CASO 4: Dos syncs sin novedad ---\n";

[$p4] = partidaBase('coh-4');
$abs4a = relojAbs($p4);

// Primer sync: 2 horas reales
[, $resp4a] = simularSync($p4, 2);
ok($resp4a['hay_cambios_visibles'] === true, 'C4-1: primer sync → flag true');
ok(relojAbs($p4) === $abs4a + 2, 'C4-2: reloj avanzó 2h');

// Segundo sync inmediato: 0 segundos adicionales
$ahora4b = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$p4['reloj']['ultimo_catch_up_iso'] = $ahora4b->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($p4, $root, $cal, null, null, $ahora4b);
CatchUpEngine::marcarSesion($p4);
$cu4b = $p4['reloj']['catch_up_pendiente'] ?? [];
$ejecutado4b = !empty($cu4b['ejecutado']);
$horas4b = (int) ($cu4b['horas_juego_avanzadas'] ?? 0);
$hayCambios4b = $ejecutado4b && $horas4b > 0;

ok(!$hayCambios4b, 'C4-3: segundo sync → flag false (sin nuevo tiempo)');
ok(relojAbs($p4) === $abs4a + 2, 'C4-4: reloj no cambió en segundo sync');

// ═══════════════════════════════════════════════════════════════
// CASO 5 — VISIBILITY RETURN
// Acumular tiempo sin heartbeat. Al volver visible, sync procesa TODO.
// ═══════════════════════════════════════════════════════════════
echo "\n--- CASO 5: Visibility return ---\n";

[$p5] = partidaBase('coh-5');
$abs5a = relojAbs($p5);

// Simular 6 horas de ausencia (pestaña en background)
[, $resp5] = simularSync($p5, 6);
ok($resp5['hay_cambios_visibles'] === true, 'C5-1: sync tras ausencia → flag true');
ok($resp5['catch_up']['horas_procesadas'] === 6, 'C5-2: procesó las 6 horas pendientes');
ok(relojAbs($p5) === $abs5a + 6, 'C5-3: reloj avanzó las 6 horas completas');

// Verificar que no hay tiempo residual pendiente
$cu5 = $p5['reloj']['catch_up_pendiente'] ?? [];
ok(($cu5['horas_juego_avanzadas'] ?? 0) === 6, 'C5-4: horas_juego_avanzadas=6 (sin duplicados)');

// ═══════════════════════════════════════════════════════════════
// CASO 6 — SIGUIENTE DESPUÉS DE AUTOSYNC
// Verificar no doble avance: sync + Siguiente = total coherente.
// ═══════════════════════════════════════════════════════════════
echo "\n--- CASO 6: Siguiente después de autosync ---\n";

[$p6] = partidaBase('coh-6');
$abs6a = relojAbs($p6);

// Auto-sync: 4 horas reales
[, $resp6a] = simularSync($p6, 4);
ok($resp6a['hay_cambios_visibles'] === true, 'C6-1: sync → flag true');
ok(relojAbs($p6) === $abs6a + 4, 'C6-2: sync procesó 4h');

// Siguiente: +1 manual
$relojOps = new RelojOperations($root, null);
$relojOps->avanzarPasoAPaso($p6, 1);
ok(relojAbs($p6) === $abs6a + 5, 'C6-3: Siguiente +1 → total 5h (no duplica)');

// Verificar que catch_up_pendiente refleja el sync, no el Siguiente
$cu6 = $p6['reloj']['catch_up_pendiente'] ?? [];
ok(($cu6['horas_juego_avanzadas'] ?? 0) === 4, 'C6-4: catch_up refleja las 4h del sync');

echo $failures > 0 ? "\nFALLOS: {$failures}\n" : "\nOK reloj_cohesion_ui_test\n";
exit($failures > 0 ? 1 : 0);
