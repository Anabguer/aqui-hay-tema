<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\PartidaHandler;
use AquiHayTema\Api\Handlers\ResidentesHandler;
use AquiHayTema\Engine\DiarioEngine;

$root = dirname(__DIR__);
$ctx = new ApiContext($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$r = PartidaHandler::nueva($ctx, ['config_id' => 'test_fixtures_v0', 'seed' => 'residente-diario-test']);
ok($r['ok'] ?? false, 'partida nueva');
$partida = $ctx->service->cargar((string) ($r['partida_id'] ?? ''));
$rid = array_key_first($partida['residentes'] ?? []) ?: '';
ok($rid !== '', 'residente en partida');

DiarioEngine::crear($partida, [
    'titulo' => 'Prueba diario',
    'texto' => 'Algo pasó.',
    'actores' => [$rid],
    'origen' => ['evento_id' => 'test_diario_' . $rid, 'tipo_evento' => 'test'],
]);

$api = ResidentesHandler::diario($ctx, ['residente_id' => $rid], $partida);
ok($api['ok'] ?? false, 'residente.diario ok');
ok(count($api['entradas'] ?? []) >= 1, 'entradas del vecino');

$bad = ResidentesHandler::diario($ctx, [], $partida);
ok(!($bad['ok'] ?? true), 'sin residente_id falla');

exit($failures > 0 ? 1 : 0);
