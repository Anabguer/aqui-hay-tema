<?php
declare(strict_types=1);

/*
 * F8 legacy compat — simula un mensaje F8 antiguo sin familia_mensajito.
 * Verifica que BuzonEngine::normalizar() infiere la familia desde tipo
 * y que organizar_algo funciona correctamente.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\MensajitoConsejoEngine;
use AquiHayTema\Engine\MensajitoDudaPermanenciaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\Reloj;

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

Reloj::fijarAhora(new DateTimeImmutable(Reloj::TEST_AHORA, Reloj::zona()));
DomainBootstrap::resetForTests();
DomainBootstrap::boot();

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'f8-legacy-' . time());
$root2 = $root;

// ── 1. Find any resident ──
$rid = array_key_first($p['residentes'] ?? []);
ok(is_string($rid) && $rid !== '', 'hay residente');

if (!is_string($rid)) {
    exit(1);
}

// ── 2. Inject a LEGACY F8 message directly (no familia_mensajito) ──
// This simulates a save from before normalizar() infers familia_mensajito.
$dia = (int) ($p['reloj']['dia_pueblo'] ?? 1);
$msgId = 'msg_f8_legacy_' . $dia . '_' . bin2hex(random_bytes(2));
$legacyEntry = [
    'id' => $msgId,
    'clasificacion' => BuzonEngine::IMPORTANTE,
    'tipo' => 'espontaneo_f_duda_permanencia',
    'de_persona' => $rid,
    'actores' => [$rid],
    'texto' => 'A veces pienso que nadie me echa de menos, ¿tú crees que debería quedarme?',
    'acciones' => [
        MensajitoAcciones::ORGANIZAR_ALGO,
        MensajitoAcciones::RESPONDER_ESCUCHAR,
        MensajitoAcciones::NO_METERSE,
    ],
    // NOTE: NO familia_mensajito — simulates legacy save before the field existed
    'datos_familia' => [
        'motivo' => 'poco contacto',
        'dias_sin_contacto' => 7,
        'clave' => 'f_duda_permanencia|' . $rid . '|7',
    ],
    'hilo_id' => $msgId,
    'hilo_estado' => 'abierto',
    'estado' => 'pendiente',
    'dia' => $dia,
];
$p['buzon'][] = $legacyEntry;

// ── 3. Verify raw message lacks familia_mensajito ──
$raw = null;
foreach ($p['buzon'] as $m) {
    if (is_array($m) && ($m['id'] ?? '') === $msgId) {
        $raw = $m;
        break;
    }
}
ok($raw !== null, 'raw message found in buzón');
ok(empty($raw['familia_mensajito']), 'raw message has NO familia_mensajito (legacy)');

// ── 4. Register the F8 state ──
MensajitoDudaPermanenciaEngine::registrarPendientePublico($p, $rid, $msgId);

// ── 5. Verify buscarRaw normalizes (infers familia_mensajito) ──
$enriched = BuzonEngine::buscar($p, $msgId);
ok($enriched !== null, 'buscar finds the message');
ok(($enriched['familia_mensajito'] ?? '') === 'f_duda_permanencia', 'buscar infers familia_mensajito from tipo');
ok(BuzonEngine::tieneDecisionPendiente($enriched ?? []), 'decision is pending');

// ── 6. Verify consejo choices are populated (requires familia_mensajito) ──
$ui = BuzonEngine::enriquecerParaUi($enriched ?? [], $p);
ok(!empty($ui['opciones_consejo']), 'opciones_consejo populated for legacy F8');
ok(count($ui['opciones_consejo'] ?? []) === 3, '3 consejo options for legacy F8');

// ── 7. Verify organizar_algo works (the actual fix) ──
$rOrg = MensajitoAcciones::resolver($p, $msgId, MensajitoAcciones::ORGANIZAR_ALGO, $root2);
ok($rOrg['ok'] ?? false, 'organizar_algo returns ok for legacy F8');
ok(!empty($rOrg['preset_organizar']), 'preset_organizar returned');
ok(($rOrg['preset_organizar']['modo'] ?? '') === 'solo', 'preset modo=solo');
ok(($rOrg['preset_organizar']['a'] ?? '') === $rid, 'preset a=rid (de_persona used, not observado_id)');
ok(!empty($rOrg['mensaje_ui']), 'mensaje_ui returned');

// ── 8. Verify decision is resolved ──
$msgAfter = BuzonEngine::buscar($p, $msgId);
ok(!BuzonEngine::tieneDecisionPendiente($msgAfter ?? []), 'decision resolved');
ok(($msgAfter['estado_decision'] ?? '') === 'resuelto', 'estado_decision = resuelto');

// ── 9. Verify F8 state is attended ──
$dudaState = $p['mensajitos_duda_permanencia'][$rid] ?? null;
ok(is_array($dudaState), 'duda_permanencia state exists');
ok(($dudaState['estado'] ?? '') === 'atendida', 'F8 state = atendida');

// ── 10. Message without tipo or familia_mensajito should still fail safely ──
$msgId2 = 'msg_no_identity_' . bin2hex(random_bytes(4));
$r2 = BuzonEngine::crear($p, [
    'id' => $msgId2,
    'clasificacion' => BuzonEngine::IMPORTANTE,
    'de_persona' => $rid,
    'texto' => 'Mensaje sin identidad de familia.',
    'acciones' => [MensajitoAcciones::ORGANIZAR_ALGO],
    'datos_familia' => [],
    'hilo_id' => $msgId2,
    'hilo_estado' => 'abierto',
]);
ok($r2['ok'] ?? false, 'no-identity message created');
$rOrg2 = MensajitoAcciones::resolver($p, $msgId2, MensajitoAcciones::ORGANIZAR_ALGO, $root2);
ok(!($rOrg2['ok'] ?? false), 'no-identity message correctly rejected');
ok(($rOrg2['error'] ?? '') === 'sin_observado', 'no-identity returns sin_observado (safe rejection)');

// ── 11. Idempotency: second call fails ──
$rOrg3 = MensajitoAcciones::resolver($p, $msgId, MensajitoAcciones::ORGANIZAR_ALGO, $root2);
ok(($rOrg3['error'] ?? '') === 'sin_decision_pendiente', 'idempotency: second call returns sin_decision_pendiente');

echo "\n" . ($failures > 0 ? "FAILURES: $failures" : "ALL TESTS PASSED") . "\n";
exit($failures > 0 ? 1 : 0);
