<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\HistoriaPuebloVista;
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

function diag(string $m): void
{
    echo "  DIAG: $m\n";
}

$service = new PartidaService($root);

// ====================================================================
// === EXACT RELOAD SIMULATION — Section from spec                  ===
// ====================================================================

echo "\n=== PHASE 1: Create game + ACK ===\n";

$p1 = $service->nuevaPartida('juego_v1', 'hp-test-reload');
$partidaId = $p1['meta']['partida_id'];
$consumedPath = HistoriaPuebloEngine::consumedPath($root, $partidaId);

// Get celebration pending
$celebs = HistoriaPuebloEngine::celebracionesPendientes($p1, $root, $partidaId);
ok(count($celebs) >= 1, 'A. pending before ACK = yes');
$celebHito = $celebs[0]['hito_id'];

// ACK
$ackOk = HistoriaPuebloEngine::ack($p1, $celebHito);
ok($ackOk, 'B. ACK = OK');

// Save consumed file first (like HistoriaPuebloHandler::ack does)
$consumed = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
$consumed[] = $celebHito;
HistoriaPuebloEngine::saveConsumed($root, $partidaId, $consumed);
ok(is_file($consumedPath), 'C. consumed file exists after save');

// Save main file (like savePartida does)
$service->guardar($p1);

// Verify main file has consumida
$pVerify = $service->cargar($partidaId);
$eVerify = null;
foreach ($pVerify['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $celebHito) {
        $eVerify = $e;
        break;
    }
}
ok($eVerify !== null && ($eVerify['celebracion_estado'] ?? '') === 'consumida',
    'D. main file has celebracion_estado=consumida after ACK+save');

// Verify consumed file content
$consumedCheck = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
ok(in_array($celebHito, $consumedCheck, true), 'E. consumed file contains hito_id');

// Verify 0 pendientes
$celebsAfterAck = HistoriaPuebloEngine::celebracionesPendientes($pVerify, $root, $partidaId);
ok(count($celebsAfterAck) === 0, 'F. pendientes after ACK+reload = 0');

// FREE ALL MEMORY — destroy everything
unset($p1, $pVerify, $celebs, $celebsAfterAck, $eVerify, $consumed, $consumedCheck);
gc_collect_cycles();

echo "\n=== PHASE 2: Simulate FULL PAGE RELOAD (two sequential loads) ===\n";

// STEP 1: partida.estado → requirePartida() → cargar()
// This is the EXACT first call on a page reload
diag("STEP 1: requirePartida → cargar() (simulates partida.estado)");
$pEstado = $service->cargar($partidaId);
$eEstado = null;
foreach ($pEstado['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $celebHito) {
        $eEstado = $e;
        break;
    }
}
ok($eEstado !== null, 'G. entry found after cargar()');
diag("  celebracion_estado after cargar() = " . ($eEstado['celebracion_estado'] ?? 'NOT_SET'));

// Check if cargar() saved (fingerprint may have changed → guardar() called)
// After cargar(), the main file might have been re-saved
$pCheckAfterCargar = json_decode(file_get_contents(
    rtrim($root, DIRECTORY_SEPARATOR) . '/data/partidas/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId) . '.json'
), true);
$eCheckAfterCargar = null;
foreach ($pCheckAfterCargar['historia_pueblo'] ?? [] as $e) {
    if (($e['hito_id'] ?? '') === $celebHito) {
        $eCheckAfterCargar = $e;
        break;
    }
}
diag("  main file celebracion_estado after cargar auto-save = " . ($eCheckAfterCargar['celebracion_estado'] ?? 'NOT_SET'));
ok(($eCheckAfterCargar['celebracion_estado'] ?? '') === 'consumida',
    'H. main file still consumida after cargar() auto-save');

// Check consumed file still intact
ok(is_file($consumedPath), 'I. consumed file still exists after cargar()');
$consumedStill = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
ok(in_array($celebHito, $consumedStill, true), 'J. consumed file still contains hito_id after cargar()');

// Check pendientes
$celebsAfterEstado = HistoriaPuebloEngine::celebracionesPendientes($pEstado, $root, $partidaId);
ok(count($celebsAfterEstado) === 0, 'K. pendientes after partida.estado = 0');

// STEP 2: Destroy estado memory, load via cargarParaRefresh
// This simulates requirePartidaRefresh → cargarParaRefresh (partida.refresh)
unset($pEstado);
gc_collect_cycles();

echo "\n=== PHASE 3: Simulate REFRESH (cargarParaRefresh) ===\n";
diag("STEP 2: requirePartidaRefresh → cargarParaRefresh() (simulates partida.refresh)");
$pRefresh = $service->cargarParaRefresh($partidaId);
$eRefresh = null;
foreach ($pRefresh['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $celebHito) {
        $eRefresh = $e;
        break;
    }
}
ok($eRefresh !== null, 'L. entry found after cargarParaRefresh()');
diag("  celebracion_estado after cargarParaRefresh() = " . ($eRefresh['celebracion_estado'] ?? 'NOT_SET'));

// Check pendientes from refrescar flow (exactly like HistoriaPuebloHandler::pendientes)
$celebsRefresh = HistoriaPuebloEngine::celebracionesPendientes($pRefresh, $root, $partidaId);
ok(count($celebsRefresh) === 0, 'M. pendientes after full reload = 0 (THE CRITICAL TEST)');

// STEP 3: Simulate a SECOND reload (double reload scenario)
unset($pRefresh);
gc_collect_cycles();

echo "\n=== PHASE 4: Second reload (double check) ===\n";
$pReload2 = $service->cargarParaRefresh($partidaId);
$celebsReload2 = HistoriaPuebloEngine::celebracionesPendientes($pReload2, $root, $partidaId);
ok(count($celebsReload2) === 0, 'N. pendientes after second reload = 0');

// STEP 4: Simulate "Pasar el rato" (avanzar reloj + refresh)
unset($pReload2);
gc_collect_cycles();

echo "\n=== PHASE 5: Simulate Pasar el rato ===\n";
$pAvanzar = $service->cargar($partidaId);
$service->avanzarReloj($pAvanzar, 4);
$service->guardar($pAvanzar);
$pRefreshAfterAvanzar = $service->cargarParaRefresh($partidaId);
$celebsAfterAvanzar = HistoriaPuebloEngine::celebracionesPendientes($pRefreshAfterAvanzar, $root, $partidaId);
ok(count($celebsAfterAvanzar) === 0, 'O. pendientes after Pasar el rato = 0');

// STEP 5: Future celebrations still work
echo "\n=== PHASE 6: Future celebrations still work ===\n";
$FutureResult = HistoriaPuebloEngine::registrar($pRefreshAfterAvanzar, 'hito_02', ['res_test_1', 'res_test_2']);
ok($FutureResult['ok'], 'P. future celebration can be registered');
$celebsFuture = HistoriaPuebloEngine::celebracionesPendientes($pRefreshAfterAvanzar, $root, $partidaId);
ok(count($celebsFuture) >= 1, 'Q. future celebration appears as pending');

// Cleanup
if (is_file($consumedPath)) {
    unlink($consumedPath);
}
// Remove test game file
$gamePath = rtrim($root, DIRECTORY_SEPARATOR) . '/data/partidas/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId) . '.json';
if (is_file($gamePath)) {
    unlink($gamePath);
}

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
