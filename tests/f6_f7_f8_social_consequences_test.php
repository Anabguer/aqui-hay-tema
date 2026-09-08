<?php
declare(strict_types=1);

/*
 * F6/F7/F8 social consequences — validates approved design changes.
 *
 * F6 — Confidencia: +4 social (empática), +2 social (contenida)
 * F7 — Alerta Vecinal: ignorar → -2 social
 * F8 — Duda de Permanencia: second layer removed; organizar/no_meterse preserved
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\MensajitoConsejoEngine;
use AquiHayTema\Engine\MensajitoDudaPermanenciaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
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
$p = $svc->nuevaPartida('juego_v1', 'f678-social-' . time());

// Inject Celestine as placeholder resident (mimics production)
$p['residentes']['per_celestine'] = [
    'nombre' => 'Celestine',
    '_placeholder' => true,
    'presencia' => 'residente',
    'runtime' => [],
];
$celestineId = 'per_celestine';

// Pick an NPC as the "other" resident
$otherRids = array_keys(array_filter($p['residentes'] ?? [], fn($r) => is_array($r) && empty($r['_placeholder'])));
$otherId = $otherRids[0] ?? null;
ok(is_string($otherId) && $otherId !== '', 'other NPC resident found');

if ($otherId === null) {
    echo "\nCANNOT PROCEED: no NPC residents\n";
    exit(1);
}

// ═══════════════════════════════════════════════════════════════
// F6 — CONFIDENCIA: social consequences
// ═══════════════════════════════════════════════════════════════
echo "\n=== F6 — CONFIDENCIA ===\n";

$dia = (int) ($p['reloj']['dia_pueblo'] ?? 1);

// ── F6 Test A: op_escucha → +4 social ──
$msgIdF6a = 'msg_f6a_' . bin2hex(random_bytes(2));
$rF6a = BuzonEngine::crear($p, [
    'id' => $msgIdF6a,
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    'tipo' => 'espontaneo_f_confidencia',
    'de_persona' => $otherId,
    'actores' => [$otherId],
    'texto' => 'Necesito contarte algo...',
    'acciones' => ['responder_escuchar'],
    'familia_mensajito' => 'f_confidencia',
    'datos_familia' => ['motivo' => 'test_a', 'clave' => 'f_confidencia|' . $otherId . '|a'],
    'hilo_id' => $msgIdF6a,
    'hilo_estado' => 'abierto',
]);
ok($rF6a['ok'] ?? false, 'F6a message created');

$socialBeforeA = RelacionEngine::valorSocialHacia($p, $otherId, $celestineId);
$rEscucha = MensajitoAcciones::resolver($p, $msgIdF6a, MensajitoAcciones::RESPONDER_ESCUCHAR, $root, null, [
    'opcion_id' => 'op_escucha',
]);
ok($rEscucha['ok'] ?? false, 'F6 op_escucha returns ok');
$socialAfterA = RelacionEngine::valorSocialHacia($p, $otherId, $celestineId);
$deltaA = $socialAfterA - $socialBeforeA;
ok($deltaA === 4, "F6 op_escucha → +4 social (got $deltaA)");

// ── F6 Test B: op_apoyo → +2 social ──
$msgIdF6b = 'msg_f6b_' . bin2hex(random_bytes(2));
$rF6b = BuzonEngine::crear($p, [
    'id' => $msgIdF6b,
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    'tipo' => 'espontaneo_f_confidencia',
    'de_persona' => $otherId,
    'actores' => [$otherId],
    'texto' => 'Necesito contarte algo más...',
    'acciones' => ['responder_escuchar'],
    'familia_mensajito' => 'f_confidencia',
    'datos_familia' => ['motivo' => 'test_b', 'clave' => 'f_confidencia|' . $otherId . '|b'],
    'hilo_id' => $msgIdF6b,
    'hilo_estado' => 'abierto',
]);
ok($rF6b['ok'] ?? false, 'F6b message created');

$socialBeforeB = RelacionEngine::valorSocialHacia($p, $otherId, $celestineId);
$rApoyo = MensajitoAcciones::resolver($p, $msgIdF6b, MensajitoAcciones::RESPONDER_ESCUCHAR, $root, null, [
    'opcion_id' => 'op_apoyo',
]);
ok($rApoyo['ok'] ?? false, 'F6 op_apoyo returns ok');
$socialAfterB = RelacionEngine::valorSocialHacia($p, $otherId, $celestineId);
$deltaB = $socialAfterB - $socialBeforeB;
ok($deltaB === 2, "F6 op_apoyo → +2 social (got $deltaB)");

// ── F6 Test C: social never zero after listening ──
ok($socialAfterA > 0 || $socialAfterB > 0, 'F6: social bar > 0 after at least one confidencia response');

// ═══════════════════════════════════════════════════════════════
// F7 — ALERTA VECINAL: social consequences for ignorar
// ═══════════════════════════════════════════════════════════════
echo "\n=== F7 — ALERTA VECINAL ===\n";

$thirdRids = array_keys(array_filter($p['residentes'] ?? [], fn($r) => is_array($r) && empty($r['_placeholder'])));
$observadoId = null;
foreach ($thirdRids as $cand) {
    if ($cand !== $otherId) {
        $observadoId = $cand;
        break;
    }
}
if ($observadoId === null && count($thirdRids) > 0) {
    $observadoId = $thirdRids[0];
}
ok(is_string($observadoId) && $observadoId !== '', 'observado NPC found');

if (is_string($observadoId)) {
    // ── F7 Test: ignorar → -2 social ──
    $msgIdF7 = 'msg_f7_' . bin2hex(random_bytes(2));
    $rF7 = BuzonEngine::crear($p, [
        'id' => $msgIdF7,
        'clasificacion' => BuzonEngine::IMPORTANTE,
        'tipo' => 'espontaneo_f_alerta_vecinal',
        'de_persona' => $otherId,
        'actores' => [$otherId],
        'texto' => 'Creo que ' . ($p['residentes'][$observadoId]['nombre'] ?? 'alguien') . ' no está bien...',
        'acciones' => ['investigar', 'organizar_algo', 'no_meterse'],
        'familia_mensajito' => 'f_alerta_vecinal',
        'datos_familia' => [
            'observado_id' => $observadoId,
            'observado_nombre' => $p['residentes'][$observadoId]['nombre'] ?? 'alguien',
            'clave' => 'f_alerta|' . $observadoId,
        ],
        'hilo_id' => $msgIdF7,
        'hilo_estado' => 'abierto',
    ]);
    ok($rF7['ok'] ?? false, 'F7 message created');

    $socialBeforeIgnorar = RelacionEngine::valorSocialHacia($p, $celestineId, $observadoId);
    $rIgnorar = MensajitoAcciones::resolver($p, $msgIdF7, MensajitoAcciones::NO_METERSE, $root);
    ok($rIgnorar['ok'] ?? false, 'F7 ignorar returns ok');
    $socialAfterIgnorar = RelacionEngine::valorSocialHacia($p, $celestineId, $observadoId);
    $deltaIgnorar = $socialAfterIgnorar - $socialBeforeIgnorar;
    ok($deltaIgnorar === -2, "F7 ignorar → -2 social (got $deltaIgnorar)");

    // ── F7: no new option created ──
    $rF7check = BuzonEngine::buscar($p, $msgIdF7);
    $accionesAfter = $rF7check['acciones'] ?? [];
    ok(count($accionesAfter) === 3, 'F7 has exactly 3 options (no fourth created)');

    // ── F7: implicarse options don't change social (investigar) ──
    $msgIdF7b = 'msg_f7b_' . bin2hex(random_bytes(2));
    $rF7b = BuzonEngine::crear($p, [
        'id' => $msgIdF7b,
        'clasificacion' => BuzonEngine::IMPORTANTE,
        'tipo' => 'espontaneo_f_alerta_vecinal',
        'de_persona' => $otherId,
        'actores' => [$otherId],
        'texto' => 'Oye, ' . ($p['residentes'][$observadoId]['nombre'] ?? 'alguien') . ' parece...',
        'acciones' => ['investigar', 'organizar_algo', 'no_meterse'],
        'familia_mensajito' => 'f_alerta_vecinal',
        'datos_familia' => [
            'observado_id' => $observadoId,
            'observado_nombre' => $p['residentes'][$observadoId]['nombre'] ?? 'alguien',
            'clave' => 'f_alerta|' . $observadoId . '|b',
        ],
        'hilo_id' => $msgIdF7b,
        'hilo_estado' => 'abierto',
    ]);
    $socialBeforeInvestigar = RelacionEngine::valorSocialHacia($p, $celestineId, $observadoId);
    $rInvestigar = MensajitoAcciones::resolver($p, $msgIdF7b, MensajitoAcciones::INVESTIGAR, $root);
    ok($rInvestigar['ok'] ?? false, 'F7 investigar returns ok');
    $socialAfterInvestigar = RelacionEngine::valorSocialHacia($p, $celestineId, $observadoId);
    $deltaInvestigar = $socialAfterInvestigar - $socialBeforeInvestigar;
    ok($deltaInvestigar === 0, "F7 investigar → +0 social (got $deltaInvestigar)");
}

// ═══════════════════════════════════════════════════════════════
// F8 — DUDA DE PERMANENCIA: second layer removed
// ═══════════════════════════════════════════════════════════════
echo "\n=== F8 — DUDA DE PERMANENCIA ===\n";

// ── F8 Test A: no consejo panel, only action buttons ──
$msgIdF8a = 'msg_f8a_' . bin2hex(random_bytes(2));
$rF8a = BuzonEngine::crear($p, [
    'id' => $msgIdF8a,
    'clasificacion' => BuzonEngine::IMPORTANTE,
    'tipo' => 'espontaneo_f_duda_permanencia',
    'de_persona' => $otherId,
    'actores' => [$otherId],
    'texto' => 'A veces pienso que nadie me echa de menos...',
    'acciones' => [
        MensajitoAcciones::ORGANIZAR_ALGO,
        MensajitoAcciones::RESPONDER_ESCUCHAR,
        MensajitoAcciones::NO_METERSE,
    ],
    'familia_mensajito' => MensajitoDudaPermanenciaEngine::FAMILIA,
    'datos_familia' => [
        'motivo' => 'poco contacto',
        'dias_sin_contacto' => 7,
        'clave' => 'f_duda_permanencia|' . $otherId . '|7a',
    ],
    'hilo_id' => $msgIdF8a,
    'hilo_estado' => 'abierto',
]);
ok($rF8a['ok'] ?? false, 'F8a message created');
MensajitoDudaPermanenciaEngine::registrarPendientePublico($p, $otherId, $msgIdF8a);

$uiF8 = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($p, $msgIdF8a) ?? [], $p);
ok(empty($uiF8['opciones_consejo']), 'F8 has no opciones_consejo (second layer removed)');
ok(!empty($uiF8['acciones_ui']), 'F8 has acciones_ui');
$accIds = array_map(fn($a) => $a['id'], $uiF8['acciones_ui'] ?? []);
ok(in_array('organizar_algo', $accIds, true), 'F8 acciones_ui includes organizar_algo');
ok(in_array('no_meterse', $accIds, true), 'F8 acciones_ui includes no_meterse');
ok(!in_array('responder_escuchar', $accIds, true), 'F8 acciones_ui does NOT include responder_escuchar (filtered)');

// ── F8 Test B: organizar_algo → atendida via='organizar' ──
$rOrgF8 = MensajitoAcciones::resolver($p, $msgIdF8a, MensajitoAcciones::ORGANIZAR_ALGO, $root);
ok($rOrgF8['ok'] ?? false, 'F8 organizar_algo returns ok');
ok(!empty($rOrgF8['preset_organizar']), 'F8 organizar_algo returns preset_organizar');
$dudaState = $p['mensajitos_duda_permanencia'][$otherId] ?? null;
ok(($dudaState['estado'] ?? '') === 'atendida', 'F8 state = atendida via organizar');
ok(($dudaState['via'] ?? '') === 'organizar', 'F8 via = organizar');

// ── F8 Test C: no_meterse → escalada=true ──
$msgIdF8c = 'msg_f8c_' . bin2hex(random_bytes(2));
$rF8c = BuzonEngine::crear($p, [
    'id' => $msgIdF8c,
    'clasificacion' => BuzonEngine::IMPORTANTE,
    'tipo' => 'espontaneo_f_duda_permanencia',
    'de_persona' => $otherId,
    'actores' => [$otherId],
    'texto' => 'A veces pienso que nadie me echa de menos...',
    'acciones' => [
        MensajitoAcciones::ORGANIZAR_ALGO,
        MensajitoAcciones::RESPONDER_ESCUCHAR,
        MensajitoAcciones::NO_METERSE,
    ],
    'familia_mensajito' => MensajitoDudaPermanenciaEngine::FAMILIA,
    'datos_familia' => [
        'motivo' => 'poco contacto',
        'dias_sin_contacto' => 10,
        'clave' => 'f_duda_permanencia|' . $otherId . '|10c',
    ],
    'hilo_id' => $msgIdF8c,
    'hilo_estado' => 'abierto',
]);
ok($rF8c['ok'] ?? false, 'F8c message created');
MensajitoDudaPermanenciaEngine::registrarPendientePublico($p, $otherId, $msgIdF8c);

$rNoMeterseF8 = MensajitoAcciones::resolver($p, $msgIdF8c, MensajitoAcciones::NO_METERSE, $root);
ok($rNoMeterseF8['ok'] ?? false, 'F8 no_meterse returns ok');
$dudaState2 = $p['mensajitos_duda_permanencia'][$otherId] ?? null;
ok(($dudaState2['escalada'] ?? false) === true, 'F8 no_meterse → escalada=true');

// ── F8 Test D: after choosing, no second decision ──
$msgAfterF8 = BuzonEngine::buscar($p, $msgIdF8a);
ok(!BuzonEngine::tieneDecisionPendiente($msgAfterF8 ?? []), 'F8: no second decision after resolving');
ok(($msgAfterF8['hilo_estado'] ?? '') === 'respondido', 'F8: thread closed after resolve');

// ═══════════════════════════════════════════════════════════════
// INTEGRITY: other families unaffected
// ═══════════════════════════════════════════════════════════════
echo "\n=== INTEGRITY CHECKS ===\n";

// F1 still has consejo panel
$msgIdF1 = 'msg_f1_int_' . bin2hex(random_bytes(2));
BuzonEngine::crear($p, [
    'id' => $msgIdF1,
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    'tipo' => 'espontaneo_f_opinion',
    'de_persona' => $otherId,
    'actores' => [$otherId],
    'texto' => '¿Qué opinas de mí?',
    'acciones' => ['responder_consejo'],
    'familia_mensajito' => 'f_opinion',
    'datos_familia' => ['otro_nombre' => 'test', 'otro_id' => $observadoId ?? ''],
    'hilo_id' => $msgIdF1,
    'hilo_estado' => 'abierto',
]);
$uiF1 = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($p, $msgIdF1) ?? [], $p);
ok(!empty($uiF1['opciones_consejo']), 'F1 still has consejo panel');

echo "\n" . ($failures > 0 ? "FAILURES: $failures" : "ALL TESTS PASSED") . "\n";
exit($failures > 0 ? 1 : 0);
