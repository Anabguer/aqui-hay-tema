<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PlaytestDiag;
use AquiHayTema\Engine\PropuestaEncuentroEngine;

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
$p = $service->nuevaPartida('playtest_01', 'diag-unit');
$r = PropuestaEncuentroEngine::proponer($p, ['per_p001', 'per_p002'], 1, 15, 'conocerse', 'lug_cafeteria');
$v = PlaytestDiag::vista($p);
ok($v['activo'] === true, 'diag activo en playtest_01');
ok(count($v['lineas']) >= 1, 'hay al menos un registro de plan');
ok(strpos($v['texto'], 'PLAN_PROPUESTO') !== false, 'registro PLAN_PROPUESTO');
ok(strpos($v['texto'], 'RESULTADO:') !== false, 'incluye RESULTADO');
ok(isset($r['playtest_diag']['texto']), 'respuesta proponer expone playtest_diag');
$estado = $service->estadoResumido($p);
ok(isset($estado['playtest_diag']['texto']), 'estado expone playtest_diag');

// Segunda propuesta no debe fatal por resolverFranja
$r2 = PropuestaEncuentroEngine::proponer($p, ['per_p001', 'per_p003'], 1, 17, 'conocerse', 'lug_cafeteria');
ok(isset($r2['ok']) || isset($r2['error']), 'segunda propuesta no fatal');

// Rechazo de plan → buzón personal, no cotilleo
$p3 = $service->nuevaPartida('juego_v1', 'diag-buzon');
$r3 = PropuestaEncuentroEngine::proponer($p3, ['per_i03', 'per_p002'], 1, 15, 'conocerse', 'lug_cafeteria');
$msgs = BuzonEngine::listar($p3);
$rechazoMsg = null;
foreach ($msgs as $m) {
    if (!is_array($m)) {
        continue;
    }
    if (($m['tipo'] ?? '') === 'respuesta_plan' || strpos((string) ($m['texto'] ?? ''), 'no han quedado') !== false) {
        $rechazoMsg = $m;
        break;
    }
}
if (!empty($r3['rechazada'])) {
    ok($rechazoMsg !== null, 'rechazo genera mensaje de buzón');
    if ($rechazoMsg) {
        ok(($rechazoMsg['canal'] ?? '') === BuzonEngine::CANAL_BUZON, 'canal = buzon (no cotilleo)');
        ok(($rechazoMsg['clasificacion'] ?? '') !== BuzonEngine::COTILLEO, 'clasificación no es cotilleo');
    }
} else {
    ok(true, 'sin rechazo en esta seed (no aplica check buzón rechazo)');
}

ok(is_file($root . '/play.php') && strpos((string) file_get_contents($root . '/play.php'), 'playtest-cheats') !== false, 'barra Acelerar tiempo en play.php');
ok(strpos((string) file_get_contents($root . '/assets/js/play-v3.js'), 'logApiError') !== false, 'JS registra errores API');

echo $failures === 0 ? "OK playtest_diag\n" : "FAIL playtest_diag ($failures)\n";
exit($failures === 0 ? 0 : 1);
