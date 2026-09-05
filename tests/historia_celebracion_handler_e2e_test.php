<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\PartidaLifecycle;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialPrimerosPasos;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $failures++; }
}

function diag(string $m): void { echo "  DIAG: $m\n"; }

$service = new PartidaService($root);

function cleanup(string $partidaId): void
{
    global $root;
    $consumedPath = HistoriaPuebloEngine::consumedPath($root, $partidaId);
    if (is_file($consumedPath)) @unlink($consumedPath);
    $gamePath = rtrim($root, DIRECTORY_SEPARATOR) . '/data/partidas/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId) . '.json';
    if (is_file($gamePath)) @unlink($gamePath);
}

function pendingIds(array $partida, string $root, string $partidaId): array
{
    return array_column(HistoriaPuebloEngine::celebracionesPendientes($partida, $root, $partidaId), 'hito_id');
}

// ================================================================
// ESCENARIO COMPLETO: Simula handler.ack() + savePartida + cargar
// ================================================================
echo "\n=== E2E: handler.ack() → save → cargar → 0 pendientes ===\n";

$p = $service->nuevaPartida('juego_v1', 'hp-e2e-celeb-ack');
$p['tutorial']['jugable_completado'] = true;
TutorialPrimerosPasos::marcarFinaleVisto($p);
$service->guardar($p);
$p = $service->cargar($p['meta']['partida_id']);
$partidaId = $p['meta']['partida_id'];
$consumedPath = HistoriaPuebloEngine::consumedPath($root, $partidaId);

// 1. Celebración inicial pendiente
$celebs = HistoriaPuebloEngine::celebracionesPendientes($p, $root, $partidaId);
ok(count($celebs) >= 1, 'E2E-1. celebración pendiente');
$hitoId = $celebs[0]['hito_id'];
diag("hito_id = $hitoId");

// 2. Simular EXACTAMENTE HistoriaPuebloHandler::ack()
$ackOk = HistoriaPuebloEngine::ack($p, $hitoId);
ok($ackOk, 'E2E-2. ack() returns true');

if ($ackOk) {
    // Exactamente como el handler: loadConsumed + append + saveConsumed
    $consumed = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
    $consumed[] = $hitoId;
    HistoriaPuebloEngine::saveConsumed($root, $partidaId, $consumed);
}
// savePartida (como el handler)
$service->guardar($p);

// 3. Verificar que el consumed file existe y contiene el hito
$consumedNow = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
ok(in_array($hitoId, $consumedNow, true), 'E2E-3. consumed file contains hito_id');
diag("consumed file: " . json_encode($consumedNow));

// 4. Verificar celebracion_estado en memoria
$found = null;
foreach ($p['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $hitoId) { $found = $e; break; }
}
ok($found !== null && ($found['celebracion_estado'] ?? '') === 'consumida',
    'E2E-4. in-memory celebracion_estado=consumida');
diag("in-memory estado = " . ($found['celebracion_estado'] ?? 'NOT_SET'));

// ================================================================
// ESCENARIO CRÍTICO: cargar() desde disco → celebracionesPendientes
// ================================================================
echo "\n=== CRÍTICO: cargar desde disco ===\n";

// Recargar desde disco (como lo hace requirePartida)
unset($p);
gc_collect_cycles();

$pReload = $service->cargar($partidaId);
$pendAfter = pendingIds($pReload, $root, $partidaId);
ok(!in_array($hitoId, $pendAfter, true), 'E2E-5. 0 pendientes after cargar()');
diag("pendientes after cargar: " . json_encode($pendAfter));

// Verificar estado en disco
$foundReload = null;
foreach ($pReload['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $hitoId) { $foundReload = $e; break; }
}
ok($foundReload !== null && ($foundReload['celebracion_estado'] ?? '') === 'consumida',
    'E2E-6. disk celebracion_estado=consumida after cargar()');
diag("disk estado = " . ($foundReload['celebracion_estado'] ?? 'NOT_SET'));

// ================================================================
// ESCENARIO CRÍTICO 2: cargarParaRefresh → celebracionesPendientes
// ================================================================
echo "\n=== CRÍTICO 2: cargarParaRefresh ===\n";

unset($pReload);
gc_collect_cycles();

$pRefresh = $service->cargarParaRefresh($partidaId);
$pendRefresh = pendingIds($pRefresh, $root, $partidaId);
ok(!in_array($hitoId, $pendRefresh, true), 'E2E-7. 0 pendientes after cargarParaRefresh()');
diag("pendientes after refresh: " . json_encode($pendRefresh));

// ================================================================
// ESCENARIO CRÍTICO 3: Simular "pasar el rato" → avanzarReloj → refresh
// ================================================================
echo "\n=== CRÍTICO 3: avanzarReloj + cargarParaRefresh ===\n";

$service->guardar($pRefresh);
unset($pRefresh);
gc_collect_cycles();

$pAvanzar = $service->cargar($partidaId);
$service->avanzarReloj($pAvanzar, 4);
$service->guardar($pAvanzar);

unset($pAvanzar);
gc_collect_cycles();

$pPostAvanzar = $service->cargarParaRefresh($partidaId);
$pendPostAvanzar = pendingIds($pPostAvanzar, $root, $partidaId);
ok(!in_array($hitoId, $pendPostAvanzar, true), 'E2E-8. 0 pendientes after avanzarReloj+cargarParaRefresh');
diag("pendientes after avanzar: " . json_encode($pendPostAvanzar));

// ================================================================
// ESCENARIO CRÍTICO 4: Simular doble refresh (F5)
// ================================================================
echo "\n=== CRÍTICO 4: doble cargarParaRefresh (simula F5) ===\n";

unset($pPostAvanzar);
gc_collect_cycles();

$pF5a = $service->cargarParaRefresh($partidaId);
$pendF5a = pendingIds($pF5a, $root, $partidaId);
ok(!in_array($hitoId, $pendF5a, true), 'E2E-9. 0 pendientes after F5 #1');

unset($pF5a);
gc_collect_cycles();

$pF5b = $service->cargarParaRefresh($partidaId);
$pendF5b = pendingIds($pF5b, $root, $partidaId);
ok(!in_array($hitoId, $pendF5b, true), 'E2E-10. 0 pendientes after F5 #2');

unset($pF5b);
gc_collect_cycles();

$pF5c = $service->cargarParaRefresh($partidaId);
$pendF5c = pendingIds($pF5c, $root, $partidaId);
ok(!in_array($hitoId, $pendF5c, true), 'E2E-11. 0 pendientes after F5 #3');

// ================================================================
// ESCENARIO CRÍTICO 5: ¿Celebración真实性 - buscarPorHito vs ack mismatch?
// ================================================================
echo "\n=== CRÍTICO 5: buscarPorHito vs ack match ===\n";

// Verificar que buscarPorHito y ack buscan por el mismo campo
$entry = null;
foreach ($pF5c['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $hitoId) { $entry = $e; break; }
}
ok($entry !== null, 'E2E-12. entry exists in historia_pueblo');
ok(($entry['celebracion_estado'] ?? '') === 'consumida', 'E2E-13. celebracion_estado=consumida in historia_pueblo');
diag("entry hito_id = " . ($entry['hito_id'] ?? 'NULL'));
diag("entry clave = " . ($entry['clave'] ?? 'NULL'));
diag("entry celebracion_estado = " . ($entry['celebracion_estado'] ?? 'NOT_SET'));

// Intentar ACK de nuevo (idempotente)
$ackAgain = HistoriaPuebloEngine::ack($pF5c, $hitoId);
ok($ackAgain === false, 'E2E-14. second ack() returns false (idempotent)');

// ================================================================
// ESCENARIO CRÍTICO 6: celebrationsPendientes con root=null
// ================================================================
echo "\n=== CRÍTICO 6: celebrationsPendientes(root=null) ===\n";

// Si root es null, no se carga consumed file
$pendNoRoot = HistoriaPuebloEngine::celebracionesPendientes($pF5c, null, null);
$pendNoRootIds = array_column($pendNoRoot, 'hito_id');
ok(!in_array($hitoId, $pendNoRootIds, true), 'E2E-15. 0 pendientes with root=null (celebracion_estado=consumida)');
diag("pendientes with root=null: " . json_encode($pendNoRootIds));

// ================================================================
// ESCENARIO CRÍTICO 7: Si se borra consumed file, ¿qué pasa?
// ================================================================
echo "\n=== CRÍTICO 7: sin consumed file ===\n";

if (is_file($consumedPath)) @unlink($consumedPath);
$pendNoConsumed = HistoriaPuebloEngine::celebracionesPendientes($pF5c, $root, $partidaId);
$pendNoConsumedIds = array_column($pendNoConsumed, 'hito_id');
ok(!in_array($hitoId, $pendNoConsumedIds, true),
    'E2E-16. 0 pendientes sin consumed file (celebracion_estado=consumida en main file)');

// ================================================================
// ESCENARIO CRÍTICO 8: ¿registrar() crea duplicado?
// ================================================================
echo "\n=== CRÍTICO 8: registrar() duplicado ===\n";

// Simular segunda llamada a registrar con mismo hito_id
$resIds = array_keys($pF5c['residentes']);
$regResult = HistoriaPuebloEngine::registrar($pF5c, $hitoId, $resIds, ['origen' => 'test']);
ok($regResult['ya_existia'] === true, 'E2E-17. registrar() returns ya_existia=true');

// Verificar que NO se creó entrada duplicada
$countHito = 0;
foreach ($pF5c['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $hitoId) $countHito++;
}
ok($countHito === 1, 'E2E-18. exactly 1 entry for hito_id (no duplicate)');
diag("entries with hito_id=$hitoId: $countHito");

cleanup($partidaId);

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS E2E PASARON' : "$failures tests E2E FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
