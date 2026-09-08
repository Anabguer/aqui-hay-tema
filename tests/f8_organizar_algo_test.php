<?php
declare(strict_types=1);

/*
 * F8 (f_duda_permanencia) — organizar_algo action end-to-end.
 * Tests that clicking "Ayudarle a quedarse" on an F8 mensajito resolves correctly.
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

function debug($val): void
{
    echo '  DEBUG: ' . json_encode($val, JSON_UNESCAPED_UNICODE) . "\n";
}

Reloj::fijarAhora(new DateTimeImmutable(Reloj::TEST_AHORA, Reloj::zona()));
DomainBootstrap::resetForTests();
DomainBootstrap::boot();

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'f8-organizar-' . time());
$root2 = $root;

// ── 1. Find any resident ──
$rid = array_key_first($p['residentes'] ?? []);
ok(is_string($rid) && $rid !== '', 'hay residente');

if (!is_string($rid)) {
    exit(1);
}

// ── 2. Manually create an F8 message in the buzón ──
$dia = (int) ($p['reloj']['dia_pueblo'] ?? 1);
$msgId = 'msg_f8_test_' . $dia . '_' . bin2hex(random_bytes(2));
$r = BuzonEngine::crear($p, [
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
    'familia_mensajito' => MensajitoDudaPermanenciaEngine::FAMILIA,
    'datos_familia' => [
        'motivo' => 'poco contacto',
        'dias_sin_contacto' => 7,
        'clave' => 'f_duda_permanencia|' . $rid . '|7',
    ],
    'hilo_id' => $msgId,
    'hilo_estado' => 'abierto',
]);
ok($r['ok'] ?? false, 'F8 message created');

// ── 3. Register the F8 state ──
MensajitoDudaPermanenciaEngine::registrarPendientePublico($p, $rid, $msgId);

// ── 4. Verify the message exists and has decision pending ──
$msg = BuzonEngine::buscar($p, $msgId);
ok($msg !== null, 'message found');
ok(BuzonEngine::tieneDecisionPendiente($msg ?? []), 'decision is pending');
ok(in_array(MensajitoAcciones::ORGANIZAR_ALGO, $msg['acciones'] ?? [], true), 'organizar_algo in actions');

// ── 5. Verify action buttons are populated (no consejo panel for F8) ──
$ui = BuzonEngine::enriquecerParaUi($msg ?? [], $p);
ok(empty($ui['opciones_consejo']), 'no opciones_consejo for F8 (second layer removed)');
ok(!empty($ui['acciones_ui']), 'acciones_ui populated');
ok(count($ui['acciones_ui'] ?? []) === 2, '2 acciones_ui (organizar_algo + no_meterse)');
$uiAccionIds = array_map(fn($a) => $a['id'], $ui['acciones_ui']);
ok(in_array('organizar_algo', $uiAccionIds, true), 'organizar_algo in acciones_ui');
ok(in_array('no_meterse', $uiAccionIds, true), 'no_meterse in acciones_ui');

// ── 6. Simulate reading the message (estado = 'leido') ──
BuzonEngine::marcarLeido($p, $msgId);
$msgRead = BuzonEngine::buscar($p, $msgId);
ok(($msgRead['estado'] ?? '') === 'leido', 'message marked as leido');
ok(!empty($msgRead['leido']), 'leido flag true');
// Decision should still be pending after just reading
ok(BuzonEngine::tieneDecisionPendiente($msgRead ?? []), 'decision still pending after read');

// ── 7. After reading, action buttons should still be available from backend ──
$uiAfterRead = BuzonEngine::enriquecerParaUi($msgRead ?? [], $p);
ok(empty($uiAfterRead['opciones_consejo']), 'no opciones_consejo after read for F8');
ok(!empty($uiAfterRead['acciones_ui']), 'acciones_ui still populated after read (backend)');
ok($uiAfterRead['requiere_decision'] ?? false, 'requiere_decision true after read');

// ── 8. Resolve via organizar_algo ──
$rOrg = MensajitoAcciones::resolver($p, $msgId, MensajitoAcciones::ORGANIZAR_ALGO, $root2);
ok($rOrg['ok'] ?? false, 'organizar_algo returns ok');
ok(!empty($rOrg['preset_organizar']), 'preset_organizar returned');
ok(($rOrg['preset_organizar']['modo'] ?? '') === 'solo', 'preset modo=solo');
ok(($rOrg['preset_organizar']['a'] ?? '') === $rid, 'preset a=rid');
ok(!empty($rOrg['mensaje_ui']), 'mensaje_ui returned');

// ── 9. Verify decision is now resolved ──
$msgAfter = BuzonEngine::buscar($p, $msgId);
ok($msgAfter !== null, 'message still exists after resolve');
ok(!BuzonEngine::tieneDecisionPendiente($msgAfter ?? []), 'decision resolved');
ok(($msgAfter['estado_decision'] ?? '') === 'resuelto', 'estado_decision = resuelto');
ok(($msgAfter['estado'] ?? '') === 'resuelto', 'estado = resuelto');
ok(!empty($msgAfter['respuesta_celestine']), 'respuesta_celestine set');
ok(($msgAfter['hilo_estado'] ?? '') === 'respondido', 'hilo closed');

// ── 10. Verify F8 state is attended ──
$dudaState = $p['mensajitos_duda_permanencia'][$rid] ?? null;
ok(is_array($dudaState), 'duda_permanencia state exists');
ok(($dudaState['estado'] ?? '') === 'atendida', 'F8 state = atendida');
ok(($dudaState['via'] ?? '') === 'organizar', 'F8 via = organizar');

// ── 11. Idempotency: second call should fail ──
$rOrg2 = MensajitoAcciones::resolver($p, $msgId, MensajitoAcciones::ORGANIZAR_ALGO, $root2);
ok(($rOrg2['error'] ?? '') === 'sin_decision_pendiente', 'idempotency: second call returns sin_decision_pendiente');

// ── 12. Verify no_leidos is returned ──
ok(isset($rOrg['no_leidos']) && is_int($rOrg['no_leidos']), 'no_leidos returned as int');

// ── 13. Verify panel renders after resolve (acciones_ui empty) ──
$uiResolved = BuzonEngine::enriquecerParaUi($msgAfter ?? [], $p);
ok(!($uiResolved['requiere_decision'] ?? false), 'requiere_decision false after resolve');
ok($uiResolved['acciones_ui'] === [], 'acciones_ui empty after resolve');

echo "\n" . ($failures > 0 ? "FAILURES: $failures" : "ALL TESTS PASSED") . "\n";
exit($failures > 0 ? 1 : 0);
