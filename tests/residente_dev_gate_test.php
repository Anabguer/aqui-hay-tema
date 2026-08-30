<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\ResidentesHandler;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\PartidaService;

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

// --- Test 1: Verificar que el código fuente contiene requireDev() ---
$src = file_get_contents(__DIR__ . '/../api/handlers/ResidentesHandler.php');
ok(strpos($src, 'requireDev()') !== false, 'ResidentesHandler::incorporar tiene requireDev()');

// --- Test 2: Verificar que placeholder también tiene requireDev() ---
ok(substr_count($src, 'requireDev()') >= 2, 'ResidentesHandler::placeholder también tiene requireDev()');

// --- Test 3: Sin AHT_DEV, aht_dev_enabled() retorna false ---
putenv('AHT_DEV=');
@unlink($root . '/src/dev.local.php');
require_once $root . '/src/dev_gate.php';
ok(!aht_dev_enabled(), 'aht_dev_enabled() retorna false sin AHT_DEV');

// --- Test 4: Con AHT_DEV=1, aht_dev_enabled() retorna true ---
putenv('AHT_DEV=1');
ok(aht_dev_enabled(), 'aht_dev_enabled() retorna true con AHT_DEV=1');

// --- Test 5: Con AHT_DEV, incorporar funciona correctamente ---
$ctx = new ApiContext($root);
$svc = new PartidaService($root);
$cat = new Catalog($root);
$p = $svc->nuevaPartida('juego_v1', 'dev-gate-ok');
$pool = $cat->listPersonajeIdsJugables();
$candidato = null;
foreach ($pool as $id) {
    if (!isset($p['residentes'][$id]) && CapacidadViviendas::huecos($p) > 0) {
        $candidato = $id;
        break;
    }
}
$r = ResidentesHandler::incorporar($ctx, ['catalog_id' => $candidato], $p);
ok(($r['ok'] ?? false) === true, 'residente.incorporar con AHT_DEV funciona');

// --- Test 6: Con AHT_DEV, placeholder funciona ---
$rPh = ResidentesHandler::placeholder($ctx, [], $p);
ok(($rPh['ok'] ?? false) === true, 'residente.placeholder con AHT_DEV funciona');

// --- Test 7: Flujo normal de candidato NO usa residente.incorporar ---
$srcCand = file_get_contents(__DIR__ . '/../src/Engine/CandidatoLlegadaEngine.php');
ok(strpos($srcCand, 'residente.incorporar') === false, 'CandidatoLlegadaEngine NO llama a residente.incorporar (usa incorporarCatalogo directo)');

// --- Test 8: TutorialIncorporaciones NO usa residente.incorporar ---
$srcTut = file_get_contents(__DIR__ . '/../src/Engine/TutorialIncorporaciones.php');
ok(strpos($srcTut, 'residente.incorporar') === false, 'TutorialIncorporaciones NO llama a residente.incorporar');

putenv('AHT_DEV=');

echo $failures === 0 ? "OK residente_dev_gate_test\n" : "FAIL residente_dev_gate_test ({$failures})\n";
exit($failures > 0 ? 1 : 0);
