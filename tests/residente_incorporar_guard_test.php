<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\ResidentesHandler;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ResidenteOperations;
use AquiHayTema\Engine\TutorialIncorporaciones;

$root = dirname(__DIR__);
putenv('AHT_DEV=1');
$ctx = new ApiContext($root);
$svc = new PartidaService($root);
$cat = new Catalog($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$partida = $svc->nuevaPartida('juego_v1', 'incorporar-guard');
$pool = $cat->listPersonajeIdsJugables();
$candidato = null;
foreach ($pool as $id) {
    if (!isset($partida['residentes'][$id]) && CapacidadViviendas::huecos($partida) > 0) {
        $candidato = $id;
        break;
    }
}
ok($candidato !== null, 'hay candidato válido para incorporar');

$antes = count(TutorialIncorporaciones::residentesActivos($partida));
$rOk = ResidentesHandler::incorporar($ctx, ['catalog_id' => $candidato], $partida);
ok(($rOk['ok'] ?? false) === true, 'incorporación canónica válida');
ok(isset($partida['residentes'][$candidato]), 'residente incorporado en partida');
ok(count(TutorialIncorporaciones::residentesActivos($partida)) === $antes + 1, 'conteo residentes tras incorporar');

$prohibidos = ['per_qa_valid', 'per_i02', 'per_i03', ''];
foreach ($prohibidos as $bad) {
    $p = $svc->nuevaPartida('juego_v1', 'incorporar-guard-bad-' . ($bad === '' ? 'empty' : $bad));
    $rBad = ResidentesHandler::incorporar($ctx, ['catalog_id' => $bad], $p);
    ok(($rBad['ok'] ?? true) === false, "rechaza incorporar $bad");
    ok(($rBad['resultado']['error'] ?? '') === 'candidato_no_canonico', "error candidato_no_canonico para $bad");
    ok(!isset($p['residentes'][$bad]), "partida intacta tras rechazo $bad");
}

$legacy = $svc->nuevaPartida('test_fixtures_v0', 'incorporar-guard-legacy');
$ops = new ResidenteOperations($cat);
$rLegacy = $ops->incorporarCatalogo($legacy, 'per_p020', 'residente');
ok($rLegacy['ok'] ?? false, 'incorporarCatalogo bajo nivel sigue válido para saves legacy');

echo $failures === 0 ? "OK residente_incorporar_guard_test\n" : "FAIL residente_incorporar_guard_test ({$failures})\n";
exit($failures > 0 ? 1 : 0);
