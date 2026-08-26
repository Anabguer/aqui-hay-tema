<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once __DIR__ . '/VisualApiContext.php';
use AquiHayTema\Dev\VisualApiContextFactory;
use AquiHayTema\Api\Handlers\PartidaHandler;

$id = $argv[1] ?? 'e2erit-part_5af4821';
$ctx = VisualApiContextFactory::create(dirname(__DIR__));
$p = $ctx->repo->cargar($id);
$ctx->partidaCargadaSincronizada = true;
$r = PartidaHandler::refrescar($ctx, [], $p);
$msgs = $r['buzon']['mensajes'] ?? [];
$acc = 0;
foreach ($msgs as $m) {
    if (!empty($m['acciones']) || !empty($m['botones']) || ($m['tipo'] ?? '') === 'accion' || !empty($m['requiere_respuesta'])) {
        $acc++;
    }
}
echo "msgs=" . count($msgs) . " accionables={$acc}\n";
foreach ($r['estado']['misiones_hoy'] ?? [] as $mi) {
    echo 'mision ' . ($mi['estado'] ?? '?') . ' ' . substr((string)($mi['titulo'] ?? $mi['texto'] ?? ''), 0, 50) . "\n";
}
$enc = array_filter($r['partida']['encuentros'] ?? [], static fn($e) => in_array($e['estado'] ?? '', ['programado', 'futuro', 'confirmado'], true));
echo 'agenda_plans=' . count($enc) . "\n";
