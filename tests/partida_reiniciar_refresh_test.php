<?php
declare(strict_types=1);

/**
 * Regresión: reiniciar partida conserva partida_id y refresh sigue válido.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialPrimerosPasos;

$root = dirname(__DIR__);
$svc = new PartidaService($root);

$partida = $svc->nuevaPartida('juego_v1', 'reiniciar-refresh-' . time());
$id = (string) ($partida['meta']['partida_id'] ?? '');
if ($id === '') {
    fwrite(STDERR, "FAIL: sin partida_id tras nueva\n");
    exit(1);
}

$reiniciada = $svc->reiniciarPartida($id, 'juego_v1', 'reiniciar-refresh-seed-' . time());
$idDespues = (string) ($reiniciada['meta']['partida_id'] ?? '');
if ($idDespues !== $id) {
    fwrite(STDERR, "FAIL: reiniciar cambió partida_id ($id -> $idDespues)\n");
    exit(1);
}

try {
    $refresh = $svc->cargarParaRefresh($id);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: cargarParaRefresh tras reiniciar: {$e->getMessage()}\n");
    exit(1);
}

if (($refresh['meta']['partida_id'] ?? '') !== $id) {
    fwrite(STDERR, "FAIL: refresh payload con id distinto\n");
    exit(1);
}

if (($refresh['tutorial']['id'] ?? '') !== TutorialPrimerosPasos::ID) {
    fwrite(STDERR, "FAIL: tutorial primeros pasos no arrancó tras reiniciar juego_v1\n");
    exit(1);
}

$pendientes = \AquiHayTema\Engine\HistoriaPuebloEngine::celebracionesPendientes($refresh, $root, $id);
if ($pendientes !== []) {
    fwrite(STDERR, "FAIL: celebraciones pendientes durante tutorial tras reiniciar\n");
    exit(1);
}

echo "OK: reiniciar conserva id y refresh/tutorial válidos ($id)\n";
