<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AutonomousPlanner;
use AquiHayTema\Engine\EventInspector;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SchemaFields;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'evt-npc-test');
SchemaFields::ensure($partida);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$rng = RngService::fromPartida($partida);
$hora = 18;
while (!($slot = \AquiHayTema\Engine\AgendaEngine::estaDisponible($partida, 'per_qa_valid', 1, $hora))['disponible'] && $hora < 23) {
    $hora++;
}
$r = AutonomousPlanner::planificarSlot($partida, 'per_qa_valid', 1, $hora, $rng, $service->getLogger());
ok(($r['ok'] ?? false), 'plan NPC creado');
ok(!empty($partida['npc_autonomo']['historial_eventos']), 'historial_eventos NPC escrito');

$npcEvt = $partida['npc_autonomo']['historial_eventos'][0] ?? null;
ok(is_array($npcEvt), 'entrada historial NPC array');
$cid = (string) ($npcEvt['correlacion_id'] ?? '');
ok($cid !== '', 'historial NPC conserva correlacion_id');

$timeline = EventInspector::timeline($partida, ['tipo' => 'npc_autonomo_plan', 'limit' => 50]);
ok(($timeline['ok'] ?? false), 'timeline ok');
$fuentes = array_values(array_unique(array_map(static fn($e) => $e['fuente'] ?? '', $timeline['eventos'] ?? [])));
ok(in_array('npc_autonomo.historial_eventos', $fuentes, true), 'timeline incluye fuente npc_autonomo.historial_eventos');

$corr = EventInspector::correlacionados($partida, $cid, 50);
ok(($corr['ok'] ?? false), 'correlacionados ok');
ok(($corr['total'] ?? 0) >= 2, 'correlacionados devuelve al menos domain + npc history');

$fuentesCorr = array_values(array_unique(array_map(static fn($e) => $e['fuente'] ?? '', $corr['eventos'] ?? [])));
ok(in_array('domain_events', $fuentesCorr, true), 'correlacionados incluye domain_events');
ok(in_array('npc_autonomo.historial_eventos', $fuentesCorr, true), 'correlacionados incluye historial NPC');

exit($failures > 0 ? 1 : 0);
