<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PoblacionV3;

$root = dirname(__DIR__);
$cat = new Catalog($root);
$svc = new PartidaService($root);

$prohibidos = ['per_qa_valid', 'per_i02', 'per_i03'];
$pool = $cat->listPersonajeIdsJugables();
$poolCount = count($pool);

foreach ($prohibidos as $bad) {
    if (in_array($bad, $pool, true)) {
        fwrite(STDERR, "FAIL: $bad en pool jugable\n");
        exit(1);
    }
}

foreach ($pool as $id) {
    if (!preg_match('/^per_p\d+$/', $id)) {
        fwrite(STDERR, "FAIL: id no canónico en pool: $id\n");
        exit(1);
    }
    if (!$cat->esPersonajeJugable($id)) {
        fwrite(STDERR, "FAIL: $id en pool pero esPersonajeJugable=false\n");
        exit(1);
    }
}

for ($i = 0; $i < 100; $i++) {
    $seed = 'pool-excl-' . $i;
    $p = $svc->nuevaPartida('juego_v1', $seed);
    foreach ($prohibidos as $bad) {
        if (isset($p['residentes'][$bad])) {
            fwrite(STDERR, "FAIL: $bad en residentes juego_v1 seed $seed\n");
            exit(1);
        }
    }
    $cola = $p['llegadas']['tutorial_cola'] ?? [];
    foreach ($prohibidos as $bad) {
        if (in_array($bad, $cola, true)) {
            fwrite(STDERR, "FAIL: $bad en tutorial_cola seed $seed\n");
            exit(1);
        }
    }
    foreach ($cola as $cid) {
        if (!in_array($cid, $pool, true)) {
            fwrite(STDERR, "FAIL: cola tutorial con id fuera de pool: $cid seed $seed\n");
            exit(1);
        }
    }
    foreach (array_keys($p['residentes']) as $rid) {
        if (in_array($rid, $prohibidos, true)) {
            fwrite(STDERR, "FAIL: prohibido $rid en residentes seed $seed\n");
            exit(1);
        }
    }
}

$pCand = $svc->nuevaPartida('juego_v1', 'pool-candidato');
$disponible = CandidatoLlegadaEngine::poolDisponible($pCand, $root);
foreach ($prohibidos as $bad) {
    if (in_array($bad, $disponible, true)) {
        fwrite(STDERR, "FAIL: $bad en poolDisponible candidatos\n");
        exit(1);
    }
}
foreach ($disponible as $cid) {
    if (!in_array($cid, $pool, true)) {
        fwrite(STDERR, "FAIL: candidato fuera de pool canónico: $cid\n");
        exit(1);
    }
}

// PoblacionV3 solo puede picar del pool canónico (vía listPersonajeIdsJugables)
$partidaVacia = $svc->nuevaPartida('debug_v0', 'pool-pob-v3');
$partidaVacia['residentes'] = [];
$config = $cat->loadConfigPrevalidada('juego_v1');
$ops = new \AquiHayTema\Engine\ResidenteOperations($cat);
PoblacionV3::incorporarIniciales($partidaVacia, $config, $root, $ops);
foreach ($prohibidos as $bad) {
    if (isset($partidaVacia['residentes'][$bad])) {
        fwrite(STDERR, "FAIL: PoblacionV3 incorporó $bad\n");
        exit(1);
    }
}
foreach (array_keys($partidaVacia['residentes']) as $rid) {
    if (!in_array($rid, $pool, true)) {
        fwrite(STDERR, "FAIL: PoblacionV3 incorporó fuera de pool: $rid\n");
        exit(1);
    }
}

echo "pool_jugable_canonico_test OK (pool=$poolCount)\n";
