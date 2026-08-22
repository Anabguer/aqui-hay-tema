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
use AquiHayTema\Engine\TutorialPrimerosPasos;
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
ok(count($p['residentes']) === 3, 'trio aleatorio V3');
ok(count($p['residentes']) === 3, 'exactamente 3 habitantes');
ok(count($p['celeste']['lugares_desbloqueados'] ?? []) === 9, 'día 1: 9 lugares canónicos');
ok(in_array('lug_parque', $p['celeste']['lugares_desbloqueados'] ?? [], true), 'parque operativo V3');
ok(VidaPuebloEngine::valor($p) === 65, 'Vida inicial 65');
ok(($p['economia']['dinero']['balance'] ?? $p['celeste']['dinero'] ?? null) === null, 'dinero no canon: null');
ok((int) ($p['reloj']['dia_pueblo'] ?? 0) === 1, 'día 1');
$ids = array_keys($p['residentes']);
ok(count($ids) >= 2 && !RelacionEngine::seConocen($p, $ids[0], $ids[1]), 'dos iniciales empiezan desconocidos');
ok(empty($p['encuentros']), 'sin encuentros de laboratorio');
ok(FeatureConfig::isEnabled($p, 'buzon_enabled'), 'buzón encendido');
ok(FeatureConfig::isEnabled($p, 'npc_autonomy_enabled'), 'autonomía V1 encendida');
ok(!FeatureConfig::isEnabled($p, 'debug_tools_enabled'), 'debug de partida apagado (taller es URL)');
ok(FeatureConfig::isEnabled($p, 'misiones_diarias_enabled'), 'misiones V3 activas en juego_v1');
ok(!FeatureConfig::isEnabled($p, 'peticiones_pueblo_enabled'), 'sin peticiones de playtest el día 1');

$bien = null;
foreach ($p['buzon'] ?? [] as $m) {
    if (($m['tipo'] ?? '') === 'bienvenida') {
        $bien = $m;
        break;
    }
}
ok($bien === null, 'primeros pasos: sin bienvenida legacy al arrancar');

$tut = TutorialPrimerosPasos::vistaPublica($p);
ok(($p['tutorial']['id'] ?? '') === TutorialPrimerosPasos::ID, 'tutorial primeros pasos activo');
ok(!empty($tut['intro']['pasos']) && count($tut['intro']['pasos']) === 4, 'intro 4 pantallas');
ok(!empty($tut['intro']['pasos'][1]['caras']) && count($tut['intro']['pasos'][1]['caras']) === 3, 'pantalla 2 con 3 retratos');
$pareja = $p['tutorial']['pareja_mision1'] ?? [];
ok(($pareja['a'] ?? '') !== '' && ($pareja['b'] ?? '') !== '' && ($pareja['a'] ?? '') !== ($pareja['b'] ?? ''), 'pareja mision 1 elegida');
$pp = array_values(array_filter($p['misiones_diarias']['items'] ?? [], static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'));
ok(count($pp) === 3, '3 misiones primeros pasos dia 1');

$tipos = PropuestaNivel::tiposPermitidos($p, 'per_p001', 'per_p002');
ok($tipos === ['conocerse'], 'desconocidos: solo conocerse');

$rid0 = array_key_first($p['residentes']);
$misma = EncuentroEngine::validarContexto($p, [$rid0, $rid0], 'conocerse', 'lug_cafeteria');
ok(($misma['ok'] ?? true) === false && ($misma['error'] ?? '') === GameError::MISMA_PERSONA, 'motor rechaza Rocío consigo misma');
ok(($misma['mensaje_ui'] ?? '') === 'Elige a dos personas distintas.', 'autopareja: motivo humano');
ok(OrganizarMotivo::de($p, $rid0, $rid0)['codigo'] === OrganizarMotivo::MISMA_PERSONA, 'OrganizarMotivo misma_persona');
$cands = OrganizarMotivo::candidatos($p, $rid0);
$idsC = array_column($cands, 'id');
ok(!in_array($rid0, $idsC, true) && count($idsC) >= 1, 'candidatos nunca incluyen a la misma persona');

$queda = PropuestaEncuentroEngine::proponer($p, [$ids[0], $ids[1]], 1, 18, 'quedar', 'lug_cafeteria');
ok(($queda['ok'] ?? true) === false, 'quedar entre desconocidos no se finge');
ok(($queda['contexto']['causa'] ?? '') === OrganizarMotivo::AUN_NO_SE_CONOCEN, 'causa: aún no se conocen');
ok(strpos((string) ($queda['mensaje_ui'] ?? ''), 'encuentro no está disponible') === false, 'sin jerga de tipo no disponible');
ok(strpos((string) ($queda['mensaje_ui'] ?? ''), 'Todavía no se conocen') !== false, 'copy humano de causa');

$p2 = $service->nuevaPartida('juego_v1', 'juego-v1-pp');
$par2 = $p2['tutorial']['pareja_mision1'] ?? [];
$a2 = (string) ($par2['a'] ?? '');
$b2 = (string) ($par2['b'] ?? '');
$rPlan = PropuestaEncuentroEngine::proponer($p2, [$a2, $b2], 1, 18, PropuestaNivel::PRESENTAR, 'lug_cafeteria');
ok(isset($rPlan['ok']), 'el motor admite plan pareja tutorial');
ok(count($p2['residentes']) === 3, 'sin reset de habitantes');

$lab = $service->nuevaPartida('playtest_01', 'playtest-01');
ok(empty($lab['tutorial']['activo'] ?? null), 'playtest_01 no arranca el tutorial del jugador');
ok(count($lab['residentes']) === 8, 'laboratorio 8 vecinos intacto');

$est = $service->estadoResumido($p);
ok(isset($est['tutorial']['intro']), 'estado PLAY expone tutorial intro');
ok(isset($est['taller']), 'estado distingue taller');

echo $failures === 0 ? "OK juego_v1\n" : "FAIL juego_v1 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
