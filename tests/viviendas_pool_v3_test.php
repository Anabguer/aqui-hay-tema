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
ok(CapacidadViviendas::capacidadTotal($p) === 46, 'capacidad 46');
ok(count($p['viviendas']['slots'] ?? []) === 46, 'pool 46 slots');
ok(CapacidadViviendas::huecos($p) === 45, 'huecos tras 1 residente fixture');

$p2 = $service->nuevaPartida('test_fixtures_v0', 'pool-v3-b');
$id = $service->guardar($p2);
$loaded = $service->cargar($p2['meta']['partida_id']);
ok(count($loaded['viviendas']['slots']) === 46, 'round-trip pool');
ok((int) ($loaded['meta']['schema_version'] ?? 0) === 3, 'round-trip schema v3');

ok(CandidatoLlegadaEngine::gapMin(8) === 2, 'gap_min N=8');
ok(CandidatoLlegadaEngine::gapMin(12) === 7, 'gap_min N=12');
ok(CandidatoLlegadaEngine::gapMin(23) === 20, 'gap_min N=23');
ok(CandidatoLlegadaEngine::gapMin(45) === 48, 'gap_min N=45');
ok(abs(CandidatoLlegadaEngine::pDiaV3(8) - 0.30) < 0.001, 'p_dia N=8');
ok(abs(CandidatoLlegadaEngine::pDiaV3(23) - 0.30) < 0.001, 'p_dia N=23');
ok(abs(CandidatoLlegadaEngine::pDiaV3(45) - 0.055) < 0.001, 'p_dia N=45');

// Migración aditiva: partida guardada con pool legacy de 24 slots
$pLegacy = $service->nuevaPartida('test_fixtures_v0', 'pool-v3-legacy');
$pLegacy['viviendas']['slots'] = array_slice($pLegacy['viviendas']['slots'], 0, 24);
$pLegacy['viviendas']['cap'] = 24;
$pLegacy['celeste']['vivienda_capacidad_max'] = 24;
$occ = $pLegacy['viviendas']['slots'][0]['ocupante_id'] ?? null;
CapacidadViviendas::ensure($pLegacy);
ok(count($pLegacy['viviendas']['slots']) === 46, 'migración 24→46 slots');
ok(CapacidadViviendas::capacidadTotal($pLegacy) === 46, 'migración actualiza cap');
ok(($pLegacy['viviendas']['slots'][0]['ocupante_id'] ?? null) === $occ, 'migración conserva ocupante A01');

echo $failures === 0 ? "OK viviendas_pool_v3\n" : "FAIL viviendas_pool_v3 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
