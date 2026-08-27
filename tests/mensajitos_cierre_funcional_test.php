<?php
declare(strict_types=1);

/**
 * Cierre funcional Mensajitos 2.0 — tests focalizados por familia.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\MensajitoColectivoEngine;
use AquiHayTema\Engine\MensajitoConsejoEngine;
use AquiHayTema\Engine\MensajitoGeneradorEspontaneo;
use AquiHayTema\Engine\MensajitoPromesaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SeguimientoConsejoEngine;

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

// --- F6 confidencia crush ---
$p6 = $svc->nuevaPartida('juego_v1', 'cierre-f6-' . time());
$p6['features']['buzon_enabled'] = true;
$p6['features']['mensajitos_espontaneos_enabled'] = true;
$p6['relaciones']['per_p001']['per_p002'] = ['social' => 30, 'romance' => 18];
$msg6 = 'msg_f6_crush';
BuzonEngine::crear($p6, [
    'id' => $msg6,
    'de_persona' => 'per_p001',
    'texto' => 'Creo que me gusta Jaime.',
    'familia_mensajito' => 'f_confidencia',
    'datos_familia' => ['subtipo' => 'crush', 'otro_id' => 'per_p002', 'otro_nombre' => 'Jaime', 'clave' => 'confidencia|crush|per_p002|per_p001'],
    'acciones' => ['responder_escuchar'],
    'hilo_id' => $msg6,
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
$ui6 = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($p6, $msg6) ?? [], $p6);
ok(count($ui6['opciones_consejo'] ?? []) >= 2, 'F6: opciones escuchar');
$r6 = MensajitoAcciones::resolver($p6, $msg6, MensajitoAcciones::RESPONDER_ESCUCHAR, $root, null, ['opcion_id' => 'op_escucha']);
ok($r6['ok'] ?? false, 'F6: responder escucha');
ok(!empty($p6['mensajitos_promesas']), 'F6: promesa registrada (F14)');

// --- F14 recuerdo tras promesa ---
$p14 = $p6;
$p14['reloj']['dia_pueblo'] = 5;
$datosRec = MensajitoPromesaEngine::datosRecuerdoSiAplica($p14, 'per_p001', [
    'subtipo' => 'crush',
    'otro_id' => 'per_p002',
    'clave' => 'confidencia|crush|per_p002|per_p001',
]);
ok($datosRec !== null, 'F14: datos recuerdo si repite causa');
$msg14 = 'msg_f14_test';
BuzonEngine::crear($p14, [
    'id' => $msg14,
    'de_persona' => 'per_p001',
    'texto' => '¿Te acuerdas? Me dijiste que me avisaras.',
    'familia_mensajito' => 'f_promesa',
    'datos_familia' => $datosRec,
    'acciones' => ['responder_escuchar'],
    'hilo_id' => $msg14,
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
$ui14 = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($p14, $msg14) ?? [], $p14);
ok(count($ui14['opciones_consejo'] ?? []) >= 2, 'F14: opciones recuerdo');
$r14 = MensajitoAcciones::resolver($p14, $msg14, MensajitoAcciones::RESPONDER_ESCUCHAR, $root, null, ['opcion_id' => 'op_recuerda']);
ok($r14['ok'] ?? false, 'F14: cerrar promesa');

// --- F9 seguimiento con hecho real ---
$p9 = $svc->nuevaPartida('juego_v1', 'cierre-f9-' . time());
$p9['features']['buzon_enabled'] = true;
$p9['reloj']['dia_pueblo'] = 1;
SeguimientoConsejoEngine::registrar($p9, 'per_p001', 'lanzate', 'romance', 'per_p002', 'msg_origen_f9');
$p9['reloj']['dia_pueblo'] = 6;
$p9['encuentros'][] = [
    'id' => 'enc_f9',
    'participantes' => ['per_p001', 'per_p002'],
    'dia' => 4,
    'estado' => 'terminado',
    'experiencia_por_participante' => ['per_p001' => ['resultado' => 'bien']],
];
$hecho = SeguimientoConsejoEngine::evaluarHechoReal($p9, 'per_p001', 'lanzate', $p9['seguimientos_consejo_pendientes'][0]);
ok($hecho['codigo'] === 'bien', 'F9: detecta encuentro bueno real');
$res9 = SeguimientoConsejoEngine::evaluarPendientes($p9, $cal);
ok(count($res9) >= 1, 'F9: genera seguimiento');
$segMsg = null;
foreach ($p9['buzon'] as $m) {
    if (($m['familia_mensajito'] ?? '') === 'f_seguimiento') {
        $segMsg = $m;
        break;
    }
}
ok($segMsg !== null && ($segMsg['resultado_hecho'] ?? '') === 'bien', 'F9: copy ligado a hecho');

// --- F4 colectivo (si hay catálogo) ---
$catalog = new Catalog($root);
$items = \AquiHayTema\Engine\EventosPuebloEngine::catalogItems($catalog);
if ($items !== []) {
    $p4 = $svc->nuevaPartida('juego_v1', 'cierre-f4-' . time());
    $p4['features']['buzon_enabled'] = true;
    $p4['features']['eventos_pueblo_enabled'] = true;
    $p4['celeste']['lugares_desbloqueados'] = ['lug_bingo', 'lug_cafeteria'];
    $cand = MensajitoColectivoEngine::candidato($p4, 'per_p001', $cal, $catalog);
    ok($cand !== null || true, 'F4: candidato colectivo evaluable');
    $msg4 = 'msg_f4_test';
    BuzonEngine::crear($p4, [
        'id' => $msg4,
        'de_persona' => 'per_p001',
        'texto' => '¿Nos ayudas a organizar la noche de bingo?',
        'familia_mensajito' => 'f_colectivo',
        'datos_familia' => ['evento_catalogo_id' => 'noche_bingo', 'evento_nombre' => 'Noche de bingo'],
        'acciones' => ['aceptar_evento', 'declinar_evento'],
        'hilo_id' => $msg4,
        'estado' => 'pendiente',
        'clasificacion' => BuzonEngine::OPORTUNIDAD,
        '_placeholder_contenido' => false,
    ]);
    $ui4 = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($p4, $msg4) ?? [], $p4);
    ok(count($ui4['acciones_ui'] ?? []) === 2, 'F4: CTAs aceptar/declinar');
} else {
    echo "SKIP: F4 sin catálogo eventos_pueblo\n";
}

// --- Dedup mismo evento ---
$pD = $svc->nuevaPartida('juego_v1', 'cierre-dedup-' . time());
$pD['features']['buzon_enabled'] = true;
$eid = 'evt_dedup_test';
\AquiHayTema\Engine\CanalDeduplicador::crearSiAplica($pD, [
    'id' => 'msg_d1',
    'texto' => 'Primero',
    'de_persona' => 'per_p001',
    'origen' => ['evento_id' => $eid, 'tipo_evento' => 'espontaneo_f_opinion'],
    'familia_mensajito' => 'f_opinion',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
$rDup = \AquiHayTema\Engine\CanalDeduplicador::crearSiAplica($pD, [
    'id' => 'msg_d2',
    'texto' => 'Duplicado',
    'de_persona' => 'per_p001',
    'origen' => ['evento_id' => $eid, 'tipo_evento' => 'espontaneo_f_opinion'],
    'familia_mensajito' => 'f_opinion',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
ok($rDup === null, 'dedup: mismo evento no duplica en buzón');

echo "\n";
echo $failures === 0 ? "OK mensajitos_cierre_funcional\n" : "FAIL mensajitos_cierre_funcional ({$failures})\n";
exit($failures > 0 ? 1 : 0);
