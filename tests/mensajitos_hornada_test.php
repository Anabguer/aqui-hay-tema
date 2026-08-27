<?php
declare(strict_types=1);

/**
 * Tests focalizados — Mensajitos 2.0 primera hornada (F1/F2/F3/F5/F7/F9).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\ConsejoEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\MensajitoConsejoEngine;
use AquiHayTema\Engine\MensajitoGeneradorEspontaneo;
use AquiHayTema\Engine\MensajitosCadenciaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SeguimientoConsejoEngine;
use AquiHayTema\Engine\CalibracionConfig;

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

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$svc = new PartidaService($root);

// --- F1: opinión con opciones y consejo sin pareja ---
$p1 = $svc->nuevaPartida('juego_v1', 'hornada-f1-' . time());
$p1['features']['buzon_enabled'] = true;
$p1['relaciones']['per_p001']['per_p002'] = ['social' => 35, 'romance' => 12];
$p1['residentes']['per_p002']['identidad_publica']['nombre'] = 'Yeray';
$nPropsAntes = count($p1['propuestas_encuentro'] ?? []);
$msgId = 'msg_f1_test';
BuzonEngine::crear($p1, [
    'id' => $msgId,
    'de_persona' => 'per_p001',
    'texto' => 'Eh… Yeray me cayó bastante bien. ¿Tú qué dices?',
    'familia_mensajito' => 'f_opinion',
    'datos_familia' => ['otro_id' => 'per_p002', 'otro_nombre' => 'Yeray'],
    'acciones' => ['responder_consejo'],
    'hilo_id' => $msgId,
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
$ui1 = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($p1, $msgId) ?? [], $p1);
ok(count($ui1['opciones_consejo'] ?? []) >= 3, 'F1: opciones_consejo en UI');
ok(BuzonEngine::tieneDecisionPendiente($ui1), 'F1: decisión pendiente');
$r1 = MensajitoAcciones::resolver($p1, $msgId, MensajitoAcciones::RESPONDER_CONSEJO, $root, null, [
    'opcion_id' => 'op_animar',
]);
ok($r1['ok'] ?? false, 'F1: respuesta animar ok');
ok(count($p1['inclinaciones_consejo'] ?? []) >= 1, 'F1: inclinación registrada');
ok(count($p1['propuestas_encuentro'] ?? []) === $nPropsAntes, 'F1: no crea pareja/propuesta');
$fin1 = null;
foreach ($p1['buzon'] as $m) {
    if (($m['id'] ?? '') === $msgId) { $fin1 = $m; break; }
}
ok(($fin1['estado_decision'] ?? '') === BuzonEngine::DECISION_RESUELTO, 'F1: hilo cerrado');

// --- F2: dilema orientar entre dos ---
$p2 = $svc->nuevaPartida('juego_v1', 'hornada-f2-' . time());
$p2['features']['buzon_enabled'] = true;
$msg2 = 'msg_f2_test';
BuzonEngine::crear($p2, [
    'id' => $msg2,
    'de_persona' => 'per_p001',
    'texto' => 'Estoy entre Jaime y Fernando… ¿qué harías tú?',
    'familia_mensajito' => 'f_dilema',
    'datos_familia' => [
        'opcion_a_id' => 'per_p002', 'opcion_a_nombre' => 'Jaime',
        'opcion_b_id' => 'per_p003', 'opcion_b_nombre' => 'Fernando',
    ],
    'acciones' => ['responder_consejo'],
    'hilo_id' => $msg2,
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
$ui2 = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($p2, $msg2) ?? [], $p2);
ok(count($ui2['opciones_consejo'] ?? []) === 4, 'F2: cuatro opciones de orientación');
$r2 = MensajitoAcciones::resolver($p2, $msg2, MensajitoAcciones::RESPONDER_CONSEJO, $root, null, [
    'opcion_id' => 'op_inclinar_a',
]);
ok($r2['ok'] ?? false, 'F2: orientar hacia A');
$inc2 = ConsejoEngine::activas($p2, 'per_p001', 'per_p002');
ok($inc2 !== [], 'F2: inclinación hacia objetivo A');

// --- F3/F5: petición accionable + preset organizar (sintético) ---
$rid = null;
foreach (PeticionPuebloEngine::residentes($p1) as $cand) {
    if (count(PeticionPuebloEngine::presentablesParaConocer($p1, $cand)) >= 2) {
        $rid = $cand;
        break;
    }
}
ok($rid !== null, 'F5-pre: hay peticionario con presentables');
if ($rid !== null) {
    foreach ($p1['peticiones'] as &$lp) {
        if ((string) ($lp['residente_id'] ?? '') === $rid && (string) ($lp['estado'] ?? '') === PeticionPuebloEngine::EST_ABIERTA) {
            $lp['estado'] = PeticionPuebloEngine::EST_CADUCADA;
        }
    }
    unset($lp);
    $pet = PeticionPuebloEngine::nacerConocer($p1, $rid);
    ok($pet !== null, 'F3/F5: petición nacida');
    $msgPet = BuzonEngine::buscar($p1, (string) ($pet['buzon_id'] ?? ''));
    ok(($msgPet['tipo'] ?? '') === 'peticion' || !empty($msgPet['peticion_id']), 'F3: mensajito de petición');
    ok(trim((string) ($msgPet['texto'] ?? '')) !== '', 'F3: copy con contexto');
    $otroId = 'per_p002';
    $preset = PeticionPuebloEngine::presetOrganizarParaUi($p1, [
        'estado' => PeticionPuebloEngine::EST_ABIERTA,
        'schema_b4' => true,
        'residente_id' => $rid,
        'plantilla_id' => 'quedar_con_x',
        'params' => ['otro' => $otroId],
    ]);
    ok(is_array($preset) && ($preset['modo'] ?? '') === 'pareja', 'F3: preset organizar disponible');
}

// --- F7: solo con causa real + investigar ---
$p7 = $svc->nuevaPartida('juego_v1', 'hornada-f7-' . time());
$p7['features']['buzon_enabled'] = true;
$p7['features']['mensajitos_espontaneos_enabled'] = true;
$p7['relaciones']['per_p001']['per_p002'] = ['social' => 25, 'romance' => 0];
$p7['residentes']['per_p002']['runtime']['estado_emocional'] = [
    'id' => 'triste', 'intensidad' => 2, 'origen' => 'test',
];
$sinCausa = MensajitoGeneradorEspontaneo::evaluar($p7, 'per_p003', $cal, new RngService('f7-nocausa'));
// per_p003 may not know anyone - force candidato via direct create without observado
$p7b = $svc->nuevaPartida('juego_v1', 'hornada-f7b-' . time());
$p7b['features']['buzon_enabled'] = true;
$p7b['relaciones']['per_p001']['per_p002'] = ['social' => 30, 'romance' => 0];
$p7b['residentes']['per_p002']['runtime']['estado_emocional'] = ['id' => 'triste', 'origen' => 'test'];
$p7b['reloj']['dia_pueblo'] = 3;
$gen7 = null;
for ($i = 0; $i < 30; $i++) {
    $gen7 = MensajitoGeneradorEspontaneo::evaluar($p7b, 'per_p001', $cal, new RngService("f7-$i"));
    if ($gen7 !== null && ($gen7['familia_mensajito'] ?? '') === 'f_alerta_vecinal') {
        break;
    }
}
ok($gen7 !== null && ($gen7['familia_mensajito'] ?? '') === 'f_alerta_vecinal', 'F7: genera con causa emocional real');
if ($gen7 !== null) {
    $ui7 = BuzonEngine::enriquecerParaUi($gen7, $p7b);
    ok(count($ui7['acciones_ui'] ?? []) === 3, 'F7: tres CTAs (ficha/organizar/dejar)');
    $r7 = MensajitoAcciones::resolver($p7b, (string) $gen7['id'], MensajitoAcciones::INVESTIGAR, $root);
    ok(($r7['abrir_ficha'] ?? '') === 'per_p002', 'F7: investigar devuelve ficha');
}

// --- F9: seguimiento enlazado ---
$p9 = $svc->nuevaPartida('juego_v1', 'hornada-f9-' . time());
$p9['features']['buzon_enabled'] = true;
$origen = 'msg_origen_f9';
BuzonEngine::crear($p9, [
    'id' => $origen,
    'de_persona' => 'per_p001',
    'texto' => '¿Qué me dices de Yeray?',
    'familia_mensajito' => 'f_opinion',
    'hilo_id' => $origen,
    'acciones' => ['responder_consejo'],
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
MensajitoAcciones::resolver($p9, $origen, MensajitoAcciones::RESPONDER_CONSEJO, $root, null, ['opcion_id' => 'op_animar']);
$p9['reloj']['dia_pueblo'] = 5;
SeguimientoConsejoEngine::evaluarPendientes($p9, $cal);
$seg = null;
foreach ($p9['buzon'] as $m) {
    if (($m['tipo'] ?? '') === 'seguimiento_consejo') {
        $seg = $m;
        break;
    }
}
ok($seg !== null, 'F9: seguimiento tras consejo');
ok(($seg['mensaje_origen_id'] ?? '') === $origen, 'F9: enlazado al hilo original');

// --- Dedup: mismo hecho no repite ---
$pD = $svc->nuevaPartida('juego_v1', 'hornada-dedup-' . time());
$pD['features']['buzon_enabled'] = true;
$pD['features']['mensajitos_espontaneos_enabled'] = true;
$pD['relaciones']['per_p001']['per_p002'] = ['social' => 40, 'romance' => 15];
$pD['relaciones']['per_p001']['per_p003'] = ['social' => 35, 'romance' => 18];
$pD['reloj']['dia_pueblo'] = 2;
MensajitosCadenciaEngine::registrar($pD, 'per_p001', 'f_opinion', 'espontaneo', 'f_opinion|per_p002');
ok(MensajitoConsejoEngine::yaExisteHiloReciente($pD, 'per_p001', 'f_opinion', 'f_opinion|per_p002'), 'dedup: detecta hilo reciente');

// --- Aviso sin CTA falso ---
$pA = $svc->nuevaPartida('juego_v1', 'hornada-aviso-' . time());
BuzonEngine::crear($pA, [
    'id' => 'msg_aviso',
    'tipo' => 'respuesta_plan',
    'texto' => 'Al final no pude, lo siento.',
    'de_persona' => 'per_p002',
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::IMPORTANTE,
    '_placeholder_contenido' => false,
]);
$av = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($pA, 'msg_aviso') ?? [], $pA);
ok(($av['acciones_ui'] ?? []) === [], 'aviso informativo sin CTA falso');

// --- Variantes copy ---
$variants = [];
for ($v = 0; $v < 8; $v++) {
    $pv = $svc->nuevaPartida('juego_v1', "hornada-v-$v");
    $variants[] = \AquiHayTema\Engine\MensajitoVoz::linea(
        $pv,
        'f_opinion',
        ['otro' => 'Laura', 'texto' => 'amigo'],
        "seed-$v",
        'per_p001'
    );
}
ok(count(array_unique($variants)) >= 2, 'copy: al menos 2 variantes F1 distintas');

echo "\n";
echo $failures === 0 ? "OK mensajitos_hornada\n" : "FAIL mensajitos_hornada ({$failures})\n";
exit($failures > 0 ? 1 : 0);
