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
$targets = [
    'per_p058' => 'P058',
    'per_p112' => 'P112',
];

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
$svc = new PartidaService($root);
$ops = new ResidenteOperations($cat);
$packs = new VisualPackStore($root);

foreach ($targets as $target => $packId) {
    echo "--- $target ---\n";

    ok(PoolJugableCanon::esIdCanonico($target), "$target sigue en catálogo canónico");
    ok(!PoolJugableCanon::esSeleccionable($target, $root), "$target no es seleccionable");
    ok(!in_array($target, $pool, true), "$target ausente de listPersonajeIdsJugables");

    for ($i = 0; $i < 50; $i++) {
        $p = $svc->nuevaPartida('juego_v1', "excl-{$target}-{$i}");
        ok(!isset($p['residentes'][$target]), "nueva partida seed $i sin $target en residentes");
        foreach ($p['llegadas']['tutorial_cola'] ?? [] as $cid) {
            ok($cid !== $target, "tutorial_cola seed $i sin $target");
        }
        $disp = CandidatoLlegadaEngine::poolDisponible($p, $root);
        ok(!in_array($target, $disp, true), "poolDisponible seed $i sin $target");
    }

    // Save antiguo: carga y retrato siguen funcionando
    $legacy = $svc->nuevaPartida('test_fixtures_v0', "excl-{$target}-legacy");
    $r = $ops->incorporarCatalogo($legacy, $target, 'residente');
    ok($r['ok'] ?? false, 'incorporarCatalogo manual en save existente');
    $runtime = ResidenteRuntime::crearDesdeCatalogo($cat->loadPersonaje($target));
    $tok = RetratoResolver::resolver($legacy['residentes'][$target], $target, $packs, $root);
    ok($tok['url'] !== null, 'retrato resoluble en save con $target');
    ok(str_contains((string) $tok['url'], "${packId}_"), "retrato usa pack $packId");
}

echo $failures === 0 ? "OK excluidos_seleccion_duales_test\n" : "FAIL excluidos_seleccion_duales_test ({$failures})\n";
exit($failures > 0 ? 1 : 0);
