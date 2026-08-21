<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\SchemaMigrator;
use AquiHayTema\Engine\TutorialIncorporaciones;

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
$p = $service->nuevaPartida('test_fixtures_v0', 'pool-v3-a');
ok((int) ($p['meta']['schema_version'] ?? 0) === SchemaMigrator::CURRENT_VERSION, 'schema v3 en nueva');
ok(CapacidadViviendas::capacidadTotal($p) === 24, 'capacidad 24');
ok(count($p['viviendas']['slots'] ?? []) === 24, 'pool 24 slots');
ok(CapacidadViviendas::huecos($p) === 23, 'huecos tras 1 residente fixture');

$p2 = $service->nuevaPartida('test_fixtures_v0', 'pool-v3-b');
$id = $service->guardar($p2);
$loaded = $service->cargar($p2['meta']['partida_id']);
ok(count($loaded['viviendas']['slots']) === 24, 'round-trip pool');
ok((int) ($loaded['meta']['schema_version'] ?? 0) === 3, 'round-trip schema v3');

ok(CandidatoLlegadaEngine::gapMin(8) === 2, 'gap_min N=8');
ok(CandidatoLlegadaEngine::gapMin(12) === 7, 'gap_min N=12');
ok(CandidatoLlegadaEngine::gapMin(23) === 20, 'gap_min N=23');
ok(abs(CandidatoLlegadaEngine::pDiaV3(8) - 0.28) < 0.001, 'p_dia N=8');
ok(abs(CandidatoLlegadaEngine::pDiaV3(23) - 0.055) < 0.001, 'p_dia N=23');

echo $failures === 0 ? "OK viviendas_pool_v3\n" : "FAIL viviendas_pool_v3 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
