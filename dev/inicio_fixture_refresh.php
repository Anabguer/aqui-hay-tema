<?php
declare(strict_types=1);
/**
 * DEV — payload partida.refresh desde JSON fixture (sin DB).
 * node -e "require('child_process').execSync('php dev/inicio_fixture_refresh.php',{encoding:'utf8'})"
 */
$_SERVER['HTTP_HOST'] = 'visual-validate.internal';
require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once __DIR__ . '/VisualApiContext.php';

use AquiHayTema\Api\Handlers\PartidaHandler;
use AquiHayTema\Dev\VisualApiContextFactory;

$root = dirname(__DIR__);
$partidaId = $argv[1] ?? 'e2erit-part_5af4821';
$ctx = VisualApiContextFactory::create($root);
try {
    $partida = $ctx->service->cargarLigero($partidaId);
    $ctx->partidaCargadaSincronizada = true;
    $refresh = PartidaHandler::refrescar($ctx, [], $partida);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($refresh, JSON_UNESCAPED_UNICODE);
