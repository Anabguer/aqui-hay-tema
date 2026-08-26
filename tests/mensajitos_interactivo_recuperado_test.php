<?php
declare(strict_types=1);

/*
 * Recuperación quirúrgica: acciones interactivas de Mensajitos (sin tutorial ni avance masivo).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;

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

final class VolStubAcepta implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'stub_acepta',
            'copy_id' => null,
            'factores' => [],
            '_bloqueado_decision' => false,
        ];
    }
}

Reloj::fijarAhora(new DateTimeImmutable(Reloj::TEST_AHORA, Reloj::zona()));
DomainBootstrap::resetForTests();
DomainBootstrap::boot();

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'mensajitos-interactivo-' . time());

$rid = null;
$presentables = [];
foreach (PeticionPuebloEngine::residentes($p) as $cand) {
    $ops = PeticionPuebloEngine::presentablesParaConocer($p, $cand);
    if (count($ops) >= 2) {
        $rid = $cand;
        $presentables = $ops;
        break;
    }
}
ok($rid !== null, 'hay peticionario con >=2 presentables');

if ($rid !== null) {
    foreach ($p['peticiones'] as &$lp) {
        if ((string) ($lp['residente_id'] ?? '') === $rid && (string) ($lp['estado'] ?? '') === PeticionPuebloEngine::EST_ABIERTA) {
            $lp['estado'] = PeticionPuebloEngine::EST_CADUCADA;
        }
    }
    unset($lp);

    $pet = PeticionPuebloEngine::nacerConocer($p, $rid);
    ok($pet !== null, 'nace conocer_a_alguien');
    $ops = is_array($pet['params']['opciones'] ?? null) ? $pet['params']['opciones'] : [];
    ok(count($ops) >= 2, 'snapshot con >=2 opciones');
    $msg = BuzonEngine::buscar($p, (string) ($pet['buzon_id'] ?? ''));
    ok($msg !== null, 'mensajito vinculado');
    ok(BuzonEngine::tieneDecisionPendiente($msg ?? []), 'decision pendiente');
    ok(in_array(MensajitoAcciones::ELEGIR_PERSONA, $msg['acciones'] ?? [], true), 'accion elegir_persona');
    $ui = BuzonEngine::enriquecerParaUi($msg ?? []);
    ok(count($ui['selector_opciones'] ?? []) >= 2, 'selector_opciones en UI');

    $objetivo = (string) $ops[0]['personaje_id'];
    $payload = [
        'mensaje_id' => (string) $msg['id'],
        'accion' => MensajitoAcciones::ELEGIR_PERSONA,
        'personaje_id' => $objetivo,
    ];
    $nPropsAntes = count($p['propuestas_encuentro'] ?? []);
    $r = MensajitoAcciones::resolver($p, (string) $msg['id'], MensajitoAcciones::ELEGIR_PERSONA, $root, null, $payload);
    ok($r['ok'] ?? false, 'resolver elegir_persona ok');
    ok(PropuestaNivel::aliasTipo((string) (($p['propuestas_encuentro'][$nPropsAntes]['tipo'] ?? ''))) === PropuestaNivel::PRESENTAR, 'propuesta PRESENTAR creada');
    $fin = BuzonEngine::buscar($p, (string) $msg['id']);
    ok(!BuzonEngine::tieneDecisionPendiente($fin ?? []), 'decision cerrada tras elegir');

    $r2 = MensajitoAcciones::resolver($p, (string) $msg['id'], MensajitoAcciones::ELEGIR_PERSONA, $root, null, $payload);
    ok(($r2['error'] ?? '') === 'sin_decision_pendiente', 'idempotencia capa acciones');
}

// Marcha: acciones_ui expuestas y resolubles
$p2 = $svc->nuevaPartida('juego_v1', 'mensajitos-marcha-' . time());
$rid2 = array_key_first($p2['residentes'] ?? []);
if (is_string($rid2)) {
    $int = \AquiHayTema\Engine\MarchaEngine::forzarIntencionDev($p2, $rid2);
    $msgId = (string) ($int['mensaje_id'] ?? '');
    $msgM = BuzonEngine::buscar($p2, $msgId);
    ok(count($msgM['acciones_ui'] ?? []) === 2, 'marcha: dos acciones_ui');
    $rM = MensajitoAcciones::resolver($p2, $msgId, MensajitoAcciones::PEDIR_QUEDARSE, $root);
    ok($rM['ok'] ?? false, 'marcha: pedir quedarse via buzon.resolver');
}

exit($failures > 0 ? 1 : 0);
