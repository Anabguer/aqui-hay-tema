<?php
declare(strict_types=1);

/**
 * E2E Mensajitos: petición residente → buzón → leer ≠ resolver → elegir → persistir.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\PropuestaNivel;
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
$p = $svc->nuevaPartida('juego_v1', 'mensajitos-e2e-' . time());

$rid = null;
foreach (PeticionPuebloEngine::residentes($p) as $cand) {
    if (count(PeticionPuebloEngine::presentablesParaConocer($p, $cand)) >= 2) {
        $rid = $cand;
        break;
    }
}
ok($rid !== null, 'hay peticionario con opciones');

if ($rid !== null) {
    foreach ($p['peticiones'] as &$lp) {
        if ((string) ($lp['residente_id'] ?? '') === $rid && (string) ($lp['estado'] ?? '') === PeticionPuebloEngine::EST_ABIERTA) {
            $lp['estado'] = PeticionPuebloEngine::EST_CADUCADA;
        }
    }
    unset($lp);

    $pet = PeticionPuebloEngine::nacerConocer($p, $rid);
    ok($pet !== null, 'petición nacida');
    $msgId = (string) ($pet['buzon_id'] ?? '');
    $msg = BuzonEngine::buscar($p, $msgId);
    ok($msg !== null, 'mensajito en buzón');
    ok((int) BuzonEngine::contarNoLeidos($p) >= 1, 'cuenta como NUEVO');
    ok(BuzonEngine::tieneDecisionPendiente($msg ?? []), 'decisión pendiente');

    BuzonEngine::marcarLeido($p, $msgId);
    $leido = BuzonEngine::buscar($p, $msgId);
    ok(BuzonEngine::estaLeido($leido ?? []), 'marcado leído');
    ok(BuzonEngine::tieneDecisionPendiente($leido ?? []), 'leído no resuelve');

    $ops = is_array($pet['params']['opciones'] ?? null) ? $pet['params']['opciones'] : [];
    $objetivo = (string) ($ops[0]['personaje_id'] ?? '');
    ok($objetivo !== '', 'opción elegible');
    $nProps = count($p['propuestas_encuentro'] ?? []);
    $r = MensajitoAcciones::resolver($p, $msgId, MensajitoAcciones::ELEGIR_PERSONA, $root, null, [
        'personaje_id' => $objetivo,
    ]);
    ok($r['ok'] ?? false, 'aceptar/elegir ejecuta motor');
    ok(
        PropuestaNivel::aliasTipo((string) (($p['propuestas_encuentro'][$nProps]['tipo'] ?? ''))) === PropuestaNivel::PRESENTAR,
        'propuesta PRESENTAR creada'
    );

    $pid = (string) ($p['meta']['partida_id'] ?? '');
    ok($pid !== '', 'partida_id para persistencia');
    $svc->guardar($p);
    $p2 = $svc->cargar($pid);
    $fin = BuzonEngine::buscar($p2, $msgId);
    ok(!BuzonEngine::tieneDecisionPendiente($fin ?? []), 'resuelto persiste en save');

    // Aviso directo sin CTA: respuesta_plan no es accionable
    $p3 = $svc->nuevaPartida('playtest_01', 'mensajitos-e2e-aviso-' . time());
    BuzonEngine::crear($p3, [
        'id' => 'msg_aviso_test',
        'tipo' => 'respuesta_plan',
        'clasificacion' => BuzonEngine::IMPORTANTE,
        'texto' => 'Óscar ha rechazado la propuesta: "Tengo que poner la lavadora."',
        'de_persona' => 'per_p002',
        'estado' => 'pendiente',
    ]);
    $aviso = BuzonEngine::enriquecerParaUi(BuzonEngine::buscar($p3, 'msg_aviso_test') ?? []);
    ok(($aviso['acciones_ui'] ?? []) === [], 'aviso directo sin acciones_ui');
    ok(!BuzonEngine::tieneDecisionPendiente($aviso), 'aviso sin decisión pendiente');

    // Cotilleo no en mensajitos
    BuzonEngine::crear($p3, [
        'id' => 'msg_coti_test',
        'tipo' => 'cotilleo',
        'clasificacion' => BuzonEngine::COTILLEO,
        'canal' => BuzonEngine::CANAL_COTILLEO,
        'texto' => 'Ulises y Sara se han visto en el parque.',
    ]);
    $soloBuzon = BuzonEngine::listar($p3, null, null, BuzonEngine::CANAL_BUZON);
    $ids = array_map(static fn($m) => (string) ($m['id'] ?? ''), $soloBuzon);
    ok(!in_array('msg_coti_test', $ids, true), 'cotilleo no aparece en canal buzón');
}

// preset organizar (sintético)
$otroId = null;
foreach (PeticionPuebloEngine::residentes($p) as $r) {
    if ($r !== $rid) {
        $otroId = $r;
        break;
    }
}
$preset = PeticionPuebloEngine::presetOrganizarParaUi($p, [
    'estado' => PeticionPuebloEngine::EST_ABIERTA,
    'schema_b4' => true,
    'residente_id' => $rid ?? 'per_p001',
    'plantilla_id' => 'quedar_con_x',
    'params' => ['otro' => $otroId ?? 'per_p002'],
]);
ok(is_array($preset) && ($preset['modo'] ?? '') === 'pareja', 'preset organizar pareja');

exit($failures > 0 ? 1 : 0);
