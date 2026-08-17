<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\PartidaHandler;

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

$r = PartidaHandler::nueva($ctx, ['config_id' => 'debug_v0', 'seed' => 'handler-test']);
ok($r['ok'] ?? false, 'PartidaHandler::nueva');
$id = $r['partida_id'] ?? '';
$p = $ctx->service->cargar($id);
$est = PartidaHandler::estado($ctx, [], $p);
ok($est['ok'] ?? false, 'PartidaHandler::estado');

exit($failures > 0 ? 1 : 0);
