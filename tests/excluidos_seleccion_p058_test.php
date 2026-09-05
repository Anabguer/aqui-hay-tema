<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PoolJugableCanon;
use AquiHayTema\Engine\ResidenteOperations;
use AquiHayTema\Engine\ResidenteRuntime;
use AquiHayTema\Engine\RetratoResolver;
use AquiHayTema\Engine\VisualPackStore;

$root = dirname(__DIR__);
$failures = 0;
$target = 'per_p058';

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cat = new Catalog($root);
$pool = $cat->listPersonajeIdsJugables();

ok(PoolJugableCanon::esIdCanonico($target), "$target sigue en catálogo canónico");
ok(!PoolJugableCanon::esSeleccionable($target, $root), "$target no es seleccionable");
ok(!in_array($target, $pool, true), "$target ausente de listPersonajeIdsJugables");

$svc = new PartidaService($root);
for ($i = 0; $i < 50; $i++) {
    $p = $svc->nuevaPartida('juego_v1', 'excl-p058-' . $i);
    ok(!isset($p['residentes'][$target]), "nueva partida seed $i sin $target en residentes");
    foreach ($p['llegadas']['tutorial_cola'] ?? [] as $cid) {
        ok($cid !== $target, "tutorial_cola seed $i sin $target");
    }
    $disp = CandidatoLlegadaEngine::poolDisponible($p, $root);
    ok(!in_array($target, $disp, true), "poolDisponible seed $i sin $target");
}

// Save antiguo con per_p058: carga y retrato siguen funcionando
$legacy = $svc->nuevaPartida('test_fixtures_v0', 'excl-p058-legacy');
$ops = new ResidenteOperations($cat);
$r = $ops->incorporarCatalogo($legacy, $target, 'residente');
ok($r['ok'] ?? false, 'incorporarCatalogo manual en save existente');
$packs = new VisualPackStore($root);
$runtime = ResidenteRuntime::crearDesdeCatalogo($cat->loadPersonaje($target));
$tok = RetratoResolver::resolver($legacy['residentes'][$target], $target, $packs, $root);
ok($tok['url'] !== null, 'retrato resoluble en save con $target');
ok(str_contains((string) $tok['url'], 'P058_'), 'retrato usa pack P058');

echo $failures === 0 ? "OK excluidos_seleccion_p058_test\n" : "FAIL excluidos_seleccion_p058_test ({$failures})\n";
exit($failures > 0 ? 1 : 0);
