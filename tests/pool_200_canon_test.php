<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BloqueA;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\HistorialPersonajesPartida;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PoolJugableCanon;
use AquiHayTema\Engine\ResidenteOperations;
use AquiHayTema\Engine\ResidenteRuntime;
use AquiHayTema\Engine\RetratoResolver;
use AquiHayTema\Engine\TutorialIncorporaciones;
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

$cat = new Catalog($root);
$packs = new VisualPackStore($root);
$pool = $cat->listPersonajeIdsJugables();
$canonIds = PoolJugableCanon::ids($root);
$exclSel = PoolJugableCanon::excluidosSeleccion($root);
$totalSel = PoolJugableCanon::totalSeleccionables($root);

ok(count($canonIds) === 200, 'PoolJugableCanon::ids = 200');
ok(count($pool) === $totalSel, 'listPersonajeIdsJugables = total seleccionables');
ok(count(array_unique($pool)) === $totalSel, 'IDs únicos en pool seleccionable');
ok(PoolJugableCanon::TOTAL === 200, 'PoolJugableCanon::TOTAL = 200');
ok(CapacidadViviendas::CAP_PRODUCTO === 46, 'capacidad simultánea = 46');
ok(PoolJugableCanon::TOTAL !== CapacidadViviendas::CAP_PRODUCTO, 'catálogo 200 ≠ capacidad 46');

$prohibidos = ['per_qa_valid', 'per_i02', 'per_i03'];
foreach ($prohibidos as $bad) {
    ok(!in_array($bad, $pool, true), "$bad excluido del pool");
    ok(!PoolJugableCanon::esIdCanonico($bad), "$bad no es id canónico");
}
foreach ($exclSel as $ret) {
    ok(!in_array($ret, $pool, true), "$ret retirado de selección");
    ok(PoolJugableCanon::esIdCanonico($ret), "$ret sigue siendo id canónico");
    ok(!PoolJugableCanon::esSeleccionable($ret, $root), "$ret no es seleccionable");
}

$urlPorId = [];
$packPorId = [];
foreach ($pool as $catalogId) {
    ok(preg_match('/^per_p\d{3}$/', $catalogId) === 1, "id canónico per_pXXX: $catalogId");
    $personaje = $cat->loadPersonaje($catalogId);
    $runtime = ResidenteRuntime::crearDesdeCatalogo($personaje);
    $tok = RetratoResolver::resolver($runtime, $catalogId, $packs, $root);
    ok($tok['url'] !== null, "retrato resoluble: $catalogId");
    ok(empty($tok['lote']), "sin fallback lote: $catalogId");
    ok(isset($urlPorId[$tok['url']]) === false, "retrato único: $catalogId");
    $urlPorId[$tok['url']] = $catalogId;
    $pid = (string) ($tok['pack_id'] ?? '');
    ok($pid !== '', "pack_id presente: $catalogId");
    ok(!isset($packPorId[$pid]), "pack visual único: $pid");
    $packPorId[$pid] = $catalogId;
}

$manifest = PoolJugableCanon::manifest($root);
ok((int) ($manifest['total'] ?? 0) === 200, 'manifest total 200');
ok((int) ($manifest['capacidad_simultanea'] ?? 0) === 46, 'manifest capacidad_simultanea 46');

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'pool200-hist');
$iniciales = count($p['residentes']);
ok($iniciales >= 3 && $iniciales <= 8, 'Primeros pasos: inicio 3–8 residentes');

$aparecidos = HistorialPersonajesPartida::idsAparecidos($p);
ok(count($aparecidos) >= $iniciales, 'ya_aparecieron incluye iniciales');

$disponible = CandidatoLlegadaEngine::poolDisponible($p, $root);
ok(count($disponible) === $totalSel - count($aparecidos), 'poolDisponible = seleccionables − ya_aparecieron');
foreach ($aparecidos as $aid) {
    ok(!in_array($aid, $disponible, true), "aparecido $aid no en candidatos");
}

// Personaje que abandona libera plaza pero queda en historial
$pLeave = $svc->nuevaPartida('test_fixtures_v0', 'pool200-leave');
$ops = new ResidenteOperations($cat);
$primer = null;
foreach (PoolJugableCanon::ids($root) as $cid) {
    if (!isset($pLeave['residentes'][$cid])) {
        $r = $ops->incorporarCatalogo($pLeave, $cid, 'residente');
        if ($r['ok'] ?? false) {
            $primer = $cid;
            break;
        }
    }
}
ok($primer !== null, 'incorporar candidato de prueba');
$vid = (string) ($pLeave['residentes'][$primer]['vivienda_id'] ?? '');
ok($vid !== '', 'tiene vivienda');
$activosAntes = count(TutorialIncorporaciones::residentesActivos($pLeave));
BloqueA::liberar($pLeave, $vid);
$activosDespues = count(TutorialIncorporaciones::residentesActivos($pLeave));
ok($activosDespues === $activosAntes - 1, 'abandono libera plaza activa');
ok(HistorialPersonajesPartida::yaAparecio($pLeave, $primer), 'abandonado sigue en ya_aparecieron');
$dispLeave = CandidatoLlegadaEngine::poolDisponible($pLeave, $root);
ok(!in_array($primer, $dispLeave, true), 'abandonado no vuelve a candidatos');
$siguiente = null;
foreach ($dispLeave as $cand) {
    if (!HistorialPersonajesPartida::yaAparecio($pLeave, $cand)) {
        $siguiente = $cand;
        break;
    }
}
ok($siguiente !== null, 'hay candidato no usado para plaza liberada');
$rNuevo = $ops->incorporarCatalogo($pLeave, $siguiente, 'residente');
ok($rNuevo['ok'] ?? false, 'nuevo personaje ocupa plaza liberada');

// Cap 46 simultáneos
$pCap = $svc->nuevaPartida('test_fixtures_v0', 'pool200-cap46');
while (count(TutorialIncorporaciones::residentesActivos($pCap)) < 46) {
    $disp = CandidatoLlegadaEngine::poolDisponible($pCap, $root);
    if ($disp === []) {
        break;
    }
    $ops->incorporarCatalogo($pCap, $disp[0], 'residente');
}
ok(count(TutorialIncorporaciones::residentesActivos($pCap)) === 46, 'n=46 activos');
ok(CapacidadViviendas::huecos($pCap) === 0, 'sin huecos en n=46');
$r47 = $svc->crearResidentePlaceholderDev($pCap);
ok(($r47['ok'] ?? false) !== true, 'residente 47 simultáneo rechazado');

// Candidato ofrecido queda marcado
$pCand = $svc->nuevaPartida('juego_v1', 'pool200-cand');
$pCand['llegadas']['modo'] = 'normal';
$pCand['llegadas']['cooldown_hasta_dia'] = 0;
$of = CandidatoLlegadaEngine::intentarOfrecer($pCand, $root, null, 24 * 30);
if ($of !== null) {
    $cid = (string) ($of['catalog_id'] ?? '');
    ok(HistorialPersonajesPartida::yaAparecio($pCand, $cid), 'candidato ofrecido en ya_aparecieron');
    $disp2 = CandidatoLlegadaEngine::poolDisponible($pCand, $root);
    ok(!in_array($cid, $disp2, true), 'candidato ofrecido no reaparece en pool');
} else {
    ok(true, 'intentarOfrecer (skip si RNG no ofrece en esta seed)');
}

echo $failures === 0 ? "OK pool_200_canon_test\n" : "FAIL pool_200_canon_test ({$failures})\n";
exit($failures > 0 ? 1 : 0);
