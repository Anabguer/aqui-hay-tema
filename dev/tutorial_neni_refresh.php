<?php
declare(strict_types=1);
$_SERVER['HTTP_HOST'] = 'visual-validate.internal';
require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once __DIR__ . '/VisualApiContext.php';
use AquiHayTema\Api\Handlers\PartidaHandler;
use AquiHayTema\Dev\VisualApiContextFactory;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$mode = $argv[1] ?? 'intro';
$seed = 'neni-tut-' . time();
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', $seed);
if ($mode === 'finale') {
    if (!is_array($p['tutorial'] ?? null)) {
        $p['tutorial'] = [];
    }
    $p['tutorial']['jugable_completado'] = true;
    $p['tutorial']['finale_visto'] = false;
}
$pid = $p['partida_id'] ?? '';
$ctx = VisualApiContextFactory::create($root);
$ctx->partidaCargadaSincronizada = true;
$refresh = PartidaHandler::refrescar($ctx, [], $p);
echo json_encode(['partida_id' => $pid, 'refresh' => $refresh], JSON_UNESCAPED_UNICODE);