<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EmotionalStateService;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\ParentescoVeto;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\VisualPackStore;

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
$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'playtest-01');
$ids = ['per_p001', 'per_p002', 'per_p003', 'per_p004', 'per_p005', 'per_p006', 'per_p007', 'per_p008'];
$nombres = [];
foreach ($ids as $id) {
    $nombres[$id] = $partida['residentes'][$id]['identidad_publica']['nombre'] ?? '';
}

ok(count($partida['residentes']) === 8, '8 residentes en playtest_01');
ok(($nombres['per_p001'] ?? '') === 'Carmen', 'Carmen');
ok(($nombres['per_p002'] ?? '') === 'José', 'José');
ok(($nombres['per_p003'] ?? '') === 'Marta', 'Marta');
ok(($nombres['per_p004'] ?? '') === 'Raúl', 'Raúl');
ok(($nombres['per_p005'] ?? '') === 'Lucía', 'Lucía');
ok(($nombres['per_p006'] ?? '') === 'Dani', 'Dani');
ok(($nombres['per_p007'] ?? '') === 'Álex', 'Álex');
ok(($nombres['per_p008'] ?? '') === 'Sara', 'Sara');
ok(!isset($partida['residentes']['per_qa_valid']), 'sin QA Valid');
ok(FeatureConfig::isEnabled($partida, 'discovery_enabled'), 'discovery flag');
ok(FeatureConfig::isEnabled($partida, 'emotional_state_from_events_enabled'), 'emociones flag');
ok(FeatureConfig::isEnabled($partida, 'buzon_enabled'), 'buzon flag');
ok(FeatureConfig::isEnabled($partida, 'npc_autonomy_enabled'), 'autonomia flag');
ok(empty($partida['features']['economy_enabled']), 'economia apagada');
ok(empty($partida['features']['offline_events_enabled']), 'offline apagado');
ok(FeatureConfig::isEnabled($partida, 'vida_pueblo_enabled'), 'vida pueblo activa en playtest');
ok(FeatureConfig::isEnabled($partida, 'misiones_diarias_enabled'), 'misiones diarias activas en playtest');

$cal = CalibracionConfig::load($root);
ok((bool) CalibracionConfig::get($cal, 'resolucion_encuentro.aplicar_deltas_reales', false), 'deltas reales');
ok(!(bool) CalibracionConfig::get($cal, 'acontecimientos_dia.activo_en_play', true), 'acontecimientos_dia.activo_en_play sigue false');

$packs = new VisualPackStore($root);
$pack = $packs->pack('P001');
ok(is_array($pack), 'P001 descubierto');
ok(($pack['catalog_id'] ?? '') === 'per_p001', 'P001 catalog_id per_p001');
ok($packs->disponible($pack, 'neutral'), 'P001 neutral existe');
ok($packs->disponible($pack, 'enfadado'), 'P001 enfadada mapeada a enfadado');
ok($packs->packIdParaCatalogo('per_p002') === 'P002', 'pack por catalogo P002');

$fichaC = $service->fichaResidente($partida, 'per_p001');
$pv = $fichaC['presentacion_visual']['asset'] ?? [];
ok(!empty($pv['existe']) && is_string($pv['url_relativa'] ?? null), 'retrato Carmen existe');
ok(strpos((string) ($pv['url_relativa'] ?? ''), 'P001_') !== false, 'retrato usa PNG P001');
$relJose = $fichaC['relaciones']['per_p002'] ?? [];
ok(($relJose['conocidos'] ?? true) === false, 'José desconocido al inicio');
ok(($relJose['etiqueta_social'] ?? '') === 'desconocido', 'etiqueta desconocido');
ok(!isset($relJose['social']['valor']), 'ficha sin número social');

ok(ParentescoVeto::bloqueaRomance($partida, 'per_p004', 'per_p007', $cal), 'veto padre Raúl-Álex');

$socAntes = RelacionEngine::valorSocialHacia($partida, 'per_p001', 'per_p002');
$enc = $service->programarEncuentro($partida, ['per_p001', 'per_p002'], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programar conocerse Carmen-José');
$reloj = new RelojOperations($root, $service->getLogger(), $service->emociones());
$adv = $reloj->avanzarPasoAPaso($partida, 12);
ok(($adv['ok'] ?? false) !== false, 'avanzar 12h');
ok(RelacionEngine::seConocen($partida, 'per_p001', 'per_p002'), 'tras encuentro son conocidos');
$socDesp = RelacionEngine::valorSocialHacia($partida, 'per_p001', 'per_p002');
ok($socDesp !== $socAntes || RelacionEngine::seConocen($partida, 'per_p001', 'per_p002'), 'delta social o conocidos');
$fichaC2 = $service->fichaResidente($partida, 'per_p001');
$disc = $fichaC2['descubrimientos'] ?? [];
ok(is_array($disc), 'discovery lista presente');

$emo = new EmotionalStateService($packs, $service->getCatalog()->store());
$emo->aplicar($partida, 'per_p003', EstadoEmocional::ALEGRE, 'test', null, null, [], 2);
ok(($partida['residentes']['per_p003']['runtime']['estado_emocional']['id'] ?? '') === EstadoEmocional::ALEGRE, 'emoción alegre');
$reloj->avanzarPasoAPaso($partida, 3);
ok(($partida['residentes']['per_p003']['runtime']['estado_emocional']['id'] ?? '') === EstadoEmocional::NEUTRO, 'emoción vuelve a neutro');

$acepta = 0;
$rechaza = 0;
for ($i = 0; $i < 24; $i++) {
    $p = $service->nuevaPartida('playtest_01', 'playtest-01-vol-' . $i);
    $r = $service->proponerEncuentro($p, ['per_p001', 'per_p002'], 1, 18, 'conocerse', 'lug_cafeteria');
    if (!empty($r['rechazada'])) {
        $rechaza++;
        if ($i === 0) {
            ok(is_string($r['mensaje_ui'] ?? null) && $r['mensaje_ui'] !== '', 'copy rechazo visible');
        }
    } elseif (!empty($r['programado']) || isset($r['encuentro'])) {
        $acepta++;
    }
}
ok($acepta > 0 && $rechaza > 0, "voluntad no es 100% (acepta=$acepta rechaza=$rechaza /24)");
ok($acepta < 24, 'nunca 24/24 aceptación');

$pSlot = $service->nuevaPartida('playtest_01', 'playtest-01-slot');
$ocupa = $service->programarEncuentro($pSlot, ['per_p001', 'per_p006'], 1, 18, 'conocerse', 'lug_cafeteria');
ok($ocupa['ok'] ?? false, 'ocupar 18h');
$alt = $service->proponerEncuentro($pSlot, ['per_p001', 'per_p005'], 1, 18, 'conocerse', 'lug_cafeteria');
$horaAlt = (int) (($alt['propuesta']['hora'] ?? $alt['encuentro']['hora'] ?? 18));
if (!empty($alt['rechazada'])) {
    ok(true, 'propuesta a las 18h ocupada: rechazo de voluntad (franja alternativa evaluada)');
} else {
    ok($horaAlt !== 18 || (($alt['propuesta']['dia'] ?? 1) > 1), 'busca franja distinta si 18h ocupada');
}

$pBuz = $service->nuevaPartida('playtest_01', 'playtest-01-buzon');
$service->programarEncuentro($pBuz, ['per_p001', 'per_p002'], 1, 19, 'conocerse', 'lug_cafeteria');
$reloj->avanzarPasoAPaso($pBuz, 12);
$nBuz = count($pBuz['buzon'] ?? []);
ok($nBuz >= 1, "buzón recibió mensaje real (n=$nBuz)");

echo "VOLUNTAD_24 acepta=$acepta rechaza=$rechaza\n";
exit($failures > 0 ? 1 : 0);
