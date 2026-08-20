<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\OrganizarMotivo;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\TutorialBucle;
use AquiHayTema\Engine\VidaPuebloEngine;

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

$service = new PartidaService($root);
$p = $service->nuevaPartida('juego_v1', 'juego-v1-test');

ok(($p['meta']['config_id'] ?? '') === 'juego_v1', 'config juego_v1');
ok(isset($p['residentes']['per_i03'], $p['residentes']['per_p001'], $p['residentes']['per_p002']), 'Rocío + Carmen + José');
ok(count($p['residentes']) === 3, 'exactamente 3 habitantes');
ok(($p['celeste']['lugares_desbloqueados'] ?? []) === ['lug_cafeteria'], 'día 1: solo cafetería');
ok(!in_array('lug_parque', $p['celeste']['lugares_desbloqueados'] ?? [], true), 'parque no operativo (no es playtest)');
ok(VidaPuebloEngine::valor($p) === 65, 'Vida inicial 65');
ok(($p['economia']['dinero']['balance'] ?? $p['celeste']['dinero'] ?? null) === null, 'dinero no canon: null');
ok((int) ($p['reloj']['dia_pueblo'] ?? 0) === 1, 'día 1');
ok(!RelacionEngine::seConocen($p, 'per_p001', 'per_p002'), 'Carmen y José empiezan desconocidos');
ok(empty($p['encuentros']), 'sin encuentros de laboratorio');
ok(FeatureConfig::isEnabled($p, 'buzon_enabled'), 'buzón encendido');
ok(FeatureConfig::isEnabled($p, 'npc_autonomy_enabled'), 'autonomía V1 encendida');
ok(!FeatureConfig::isEnabled($p, 'debug_tools_enabled'), 'debug de partida apagado (taller es URL)');
ok(!FeatureConfig::isEnabled($p, 'misiones_diarias_enabled'), 'sin misiones de playtest el día 1');
ok(!FeatureConfig::isEnabled($p, 'peticiones_pueblo_enabled'), 'sin peticiones de playtest el día 1');

$bien = null;
foreach ($p['buzon'] ?? [] as $m) {
    if (($m['tipo'] ?? '') === 'bienvenida') {
        $bien = $m;
        break;
    }
}
ok(is_array($bien), 'buzón inicial: bienvenida real');
ok(($bien['de_persona'] ?? '') === 'per_i03', 'bienvenida de Rocío');
ok(($bien['canal'] ?? '') === BuzonEngine::CANAL_BUZON, 'bienvenida va al buzón, no a El Cotilleo');

$tut = TutorialBucle::vista($p);
ok(!empty($tut['activo']) && $tut['paso'] === TutorialBucle::HECHO_BUZON, 'tutorial activo: pista buzón');
ok(is_array($tut['sugerencia'] ?? null), 'hay un primer plan válido calculado');
$sug = $tut['sugerencia'];
ok(($sug['tipo'] ?? '') === 'conocerse', 'primer plan = conocerse');
ok(($sug['lugar'] ?? '') === 'lug_cafeteria', 'primer plan en cafetería');
ok(($sug['residente_a'] ?? '') !== ($sug['residente_b'] ?? ''), 'primer plan no es autopareja');

$tipos = PropuestaNivel::tiposPermitidos($p, 'per_p001', 'per_p002');
ok($tipos === ['conocerse'], 'desconocidos: solo conocerse');

$misma = EncuentroEngine::validarContexto($p, ['per_i03', 'per_i03'], 'conocerse', 'lug_cafeteria');
ok(($misma['ok'] ?? true) === false && ($misma['error'] ?? '') === GameError::MISMA_PERSONA, 'motor rechaza Rocío consigo misma');
ok(($misma['mensaje_ui'] ?? '') === 'Elige a dos personas distintas.', 'autopareja: motivo humano');
ok(OrganizarMotivo::de($p, 'per_i03', 'per_i03')['codigo'] === OrganizarMotivo::MISMA_PERSONA, 'OrganizarMotivo misma_persona');
$cands = OrganizarMotivo::candidatos($p, 'per_i03');
$idsC = array_column($cands, 'id');
ok(!in_array('per_i03', $idsC, true) && in_array('per_p001', $idsC, true), 'candidatos nunca incluyen a la misma persona');

$queda = PropuestaEncuentroEngine::proponer($p, ['per_p001', 'per_p002'], 1, 18, 'quedar', 'lug_cafeteria');
ok(($queda['ok'] ?? true) === false, 'quedar entre desconocidos no se finge');
ok(($queda['contexto']['causa'] ?? '') === OrganizarMotivo::AUN_NO_SE_CONOCEN, 'causa: aún no se conocen');
ok(strpos((string) ($queda['mensaje_ui'] ?? ''), 'encuentro no está disponible') === false, 'sin jerga de tipo no disponible');
ok(strpos((string) ($queda['mensaje_ui'] ?? ''), 'Todavía no se conocen') !== false, 'copy humano de causa');

$p2 = $service->nuevaPartida('juego_v1', 'juego-v1-bucle');
TutorialBucle::registrar($p2, TutorialBucle::HECHO_BUZON);
ok(TutorialBucle::vista($p2)['paso'] === TutorialBucle::HECHO_VECINO, 'tras leer el recado: mira un vecino');
TutorialBucle::registrar($p2, TutorialBucle::HECHO_VECINO);
ok(TutorialBucle::vista($p2)['paso'] === TutorialBucle::HECHO_PLAN, 'tras ficha: organizar');
$plan = TutorialBucle::vista($p2)['sugerencia'];
ok(is_array($plan), 'sugerencia sigue ahí hasta completar');
$rPlan = PropuestaEncuentroEngine::proponer(
    $p2,
    [$plan['residente_a'], $plan['residente_b']],
    (int) $plan['dia'],
    (int) $plan['hora'],
    (string) $plan['tipo'],
    (string) $plan['lugar']
);
ok(isset($rPlan['ok']), 'el motor real admite el primer plan (acepten o no)');
$fin = TutorialBucle::vista($p2);
ok(!empty($fin['completado']) && empty($fin['activo']), 'tutorial desaparece en la misma partida');
ok(count($p2['residentes']) === 3, 'tras el tutorial no hay reset: mismos habitantes');
ok(($p2['meta']['partida_id'] ?? '') !== '', 'misma partida persistente');

$lab = $service->nuevaPartida('playtest_01', 'playtest-01');
ok(empty($lab['tutorial']['activo'] ?? null), 'playtest_01 no arranca el tutorial del jugador');
ok(count($lab['residentes']) === 8, 'laboratorio 8 vecinos intacto');

$est = $service->estadoResumido($p);
ok(isset($est['tutorial']['paso']), 'estado PLAY expone tutorial');
ok(isset($est['taller']), 'estado distingue taller');

echo $failures === 0 ? "OK juego_v1\n" : "FAIL juego_v1 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
