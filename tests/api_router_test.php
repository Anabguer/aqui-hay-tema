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

$r = PartidaHandler::nueva($ctx, ['config_id' => 'test_fixtures_v0', 'seed' => 'handler-test']);
ok($r['ok'] ?? false, 'PartidaHandler::nueva');
$id = $r['partida_id'] ?? '';
$p = $ctx->service->cargar($id);
$est = PartidaHandler::estado($ctx, [], $p);
ok($est['ok'] ?? false, 'PartidaHandler::estado');
$g = PartidaHandler::guardar($ctx, [], $p);
ok($g['ok'] ?? false, 'PartidaHandler::guardar (use function savePartida)');
ok(is_file($root . '/api/handlers/PartidaHandler.php'), 'PartidaHandler en api/handlers (minúsculas, Hostalia)');
ok(class_exists(\AquiHayTema\Api\Handlers\EncuentrosHandler::class, true), 'autoload EncuentrosHandler');
ok(class_exists(\AquiHayTema\Api\Handlers\RelojHandler::class, true), 'autoload RelojHandler');
ok(class_exists(\AquiHayTema\Api\Handlers\ResidentesHandler::class, true), 'autoload ResidentesHandler');
ok(class_exists(\AquiHayTema\Api\Handlers\PeticionesHandler::class, true), 'autoload PeticionesHandler');

exit($failures > 0 ? 1 : 0);
