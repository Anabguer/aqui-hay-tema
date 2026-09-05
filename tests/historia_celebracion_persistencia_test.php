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
    if (!$c) {
        $failures++;
    }
}

function diag(string $m): void
{
    echo "  DIAG: $m\n";
}

$service = new PartidaService($root);

$rc = new ReflectionClass(PartidaLifecycle::class);
$fpMethod = $rc->getMethod('fingerprintEstadoPersistible');
$fpMethod->setAccessible(true);

function fingerprint(array $partida): string
{
    global $fpMethod;
    return $fpMethod->invoke(null, $partida);
}

function cleanup(string $partidaId): void
{
    global $root;
    $consumedPath = HistoriaPuebloEngine::consumedPath($root, $partidaId);
    if (is_file($consumedPath)) {
        @unlink($consumedPath);
    }
    $gamePath = rtrim($root, DIRECTORY_SEPARATOR) . '/data/partidas/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId) . '.json';
    if (is_file($gamePath)) {
        @unlink($gamePath);
    }
}

function getResIds(array $partida, int $n): array
{
    $ids = array_keys($partida['residentes']);
    return array_slice($ids, 0, $n);
}

function partidaConPrimerRecuerdo(PartidaService $service, string $config, string $seed): array
{
    $p = $service->nuevaPartida($config, $seed);
    $p['tutorial']['jugable_completado'] = true;
    TutorialPrimerosPasos::marcarFinaleVisto($p);
    $service->guardar($p);
    return $service->cargar($p['meta']['partida_id']);
}

function pendingIds(array $partida, string $root, string $partidaId): array
{
    return array_column(HistoriaPuebloEngine::celebracionesPendientes($partida, $root, $partidaId), 'hito_id');
}

// ====================================================================
// === A. ACK: consumida + persistencia                               ===
// ====================================================================
echo "\n=== A. ACK: consumida + persistencia ===\n";

$pA = partidaConPrimerRecuerdo($service, 'juego_v1', 'hp-test-celeb-fix-a');
$partidaId = $pA['meta']['partida_id'];
$consumedPath = HistoriaPuebloEngine::consumedPath($root, $partidaId);
$resIds = getResIds($pA, 2);

$celebs = HistoriaPuebloEngine::celebracionesPendientes($pA, $root, $partidaId);
ok(count($celebs) >= 1, 'A1. celebration pending before ACK');
$celebHito = $celebs[0]['hito_id'];

$ackOk = HistoriaPuebloEngine::ack($pA, $celebHito);
ok($ackOk, 'A2. ack() returns true');

$consumed = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
$consumed[] = $celebHito;
HistoriaPuebloEngine::saveConsumed($root, $partidaId, $consumed);
ok(is_file($consumedPath), 'A3. consumed file exists');

$service->guardar($pA);
$pAV = $service->cargar($partidaId);
ok(!in_array($celebHito, pendingIds($pAV, $root, $partidaId), true), 'A4. 0 pendientes after ACK+save+reload');

$eVerify = null;
foreach ($pAV['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $celebHito) {
        $eVerify = $e;
        break;
    }
}
ok($eVerify !== null && ($eVerify['celebracion_estado'] ?? '') === 'consumida',
    'A5. main file has celebracion_estado=consumida');

// ====================================================================
// === B. Idempotencia: ACK dos veces → mismo resultado               ===
// ====================================================================
echo "\n=== B. Idempotencia: ACK dos veces ===\n";

ok(HistoriaPuebloEngine::ack($pAV, $celebHito) === false, 'B1. second ack() returns false');
ok(!in_array($celebHito, pendingIds($pAV, $root, $partidaId), true), 'B2. still 0 pendientes');

// ====================================================================
// === C. consumed: excluye hito consumido incluso con estado stale   ===
// ====================================================================
echo "\n=== C. consumed: excluye hito con estado stale ===\n";

foreach ($pAV['historia_pueblo'] as &$entry) {
    if (($entry['hito_id'] ?? '') === $celebHito) {
        $entry['celebracion_estado'] = 'pendiente';
    }
}
unset($entry);

ok(!in_array($celebHito, pendingIds($pAV, $root, $partidaId), true),
    'C1. consumed file excludes even with stale pendiente');

if (is_file($consumedPath)) {
    unlink($consumedPath);
}
ok(in_array($celebHito, pendingIds($pAV, $root, $partidaId), true),
    'C2. without consumed file, stale pendiente shows again');

HistoriaPuebloEngine::saveConsumed($root, $partidaId, [$celebHito]);

// ====================================================================
// === D. reconcile: stale pendiente + consumed → consumida           ===
// ====================================================================
echo "\n=== D. reconcile: stale pendiente + consumed → consumida ===\n";

foreach ($pAV['historia_pueblo'] as &$entry) {
    if (($entry['hito_id'] ?? '') === $celebHito) {
        $entry['celebracion_estado'] = 'pendiente';
    }
}
unset($entry);

ok(HistoriaPuebloEngine::reconcileConsumedState($pAV, $root, $partidaId) === true, 'D1. reconcile corrects');
$reconciled = null;
foreach ($pAV['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $celebHito) {
        $reconciled = $e;
        break;
    }
}
ok($reconciled['celebracion_estado'] === 'consumida', 'D2. state corrected to consumida');

// ====================================================================
// === E. fingerprint: cambio en historia_pueblo se detecta           ===
// ====================================================================
echo "\n=== E. fingerprint: historia_pueblo participa ===\n";

$pAV['historia_pueblo'][] = [
    'hito_id' => 'hito_test_fp',
    'clave' => 'hito_test_fp:' . implode('|', $resIds),
    'protagonistas' => $resIds,
    'nombres' => [],
    'dia' => 1,
    'hora' => 8,
    'contexto' => [],
    'revelado' => true,
    'celebracion_estado' => 'pendiente',
];
$fpBefore = fingerprint($pAV);
$pAV['historia_pueblo'][] = [
    'hito_id' => 'hito_test_fp_2',
    'clave' => 'hito_test_fp_2:' . implode('|', $resIds),
    'protagonistas' => $resIds,
    'nombres' => [],
    'dia' => 2,
    'hora' => 9,
    'contexto' => [],
    'revelado' => true,
    'celebracion_estado' => 'consumida',
];
ok($fpBefore !== fingerprint($pAV), 'E2. fingerprint changes when historia_pueblo mutates');

// ====================================================================
// === F. reload: ACK → reload → 0 celebración                       ===
// ====================================================================
echo "\n=== F. reload: ACK → reload → 0 ===\n";

$service->guardar($pAV);
unset($pAV);
gc_collect_cycles();

$pF = $service->cargarParaRefresh($partidaId);
ok(!in_array($celebHito, pendingIds($pF, $root, $partidaId), true), 'F1. 0 pendientes after reload');

unset($pF);
gc_collect_cycles();
$pF2 = $service->cargarParaRefresh($partidaId);
ok(!in_array($celebHito, pendingIds($pF2, $root, $partidaId), true), 'F2. 0 after second reload');

// Cleanup game A
cleanup($partidaId);

// ====================================================================
// === G. reloj: ACK → simular reloj.sincronizar save → reload → 0   ===
// ====================================================================
echo "\n=== G. reloj: ACK → sincronizar reloj → reload → 0 ===\n";

$pG = partidaConPrimerRecuerdo($service, 'juego_v1', 'hp-test-celeb-fix-g');
$partidaIdG = $pG['meta']['partida_id'];

// ACK the initial celebration
$celebsG = HistoriaPuebloEngine::celebracionesPendientes($pG, $root, $partidaIdG);
ok(count($celebsG) >= 1, 'G1. pending celebration exists');
$gHito = $celebsG[0]['hito_id'];

HistoriaPuebloEngine::ack($pG, $gHito);
$consumedG = HistoriaPuebloEngine::loadConsumed($root, $partidaIdG);
$consumedG[] = $gHito;
HistoriaPuebloEngine::saveConsumed($root, $partidaIdG, $consumedG);
$service->guardar($pG);

// Simulate reloj.sincronizar: load fresh → unconditional save (no mutations)
$pG2 = $service->cargar($partidaIdG);
$gStateAfterCargar = null;
foreach ($pG2['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === $gHito) {
        $gStateAfterCargar = $e['celebracion_estado'] ?? 'NOT_SET';
        break;
    }
}
diag("  After cargar(), celebracion_estado=" . ($gStateAfterCargar ?? 'NOT_FOUND'));
$service->guardar($pG2);

// Reload and verify
unset($pG2);
gc_collect_cycles();
$pG3 = $service->cargarParaRefresh($partidaIdG);
ok(!in_array($gHito, pendingIds($pG3, $root, $partidaIdG), true),
    'G2. 0 pendientes after ACK+relojSync+reload');

cleanup($partidaIdG);

// ====================================================================
// === H. pasar el rato: ACK → avanzar tiempo → 0                     ===
// ====================================================================
echo "\n=== H. pasar el rato: ACK → avanzar tiempo → 0 ===\n";

$pH = partidaConPrimerRecuerdo($service, 'juego_v1', 'hp-test-celeb-fix-h');
$partidaIdH = $pH['meta']['partida_id'];

$celebsH = HistoriaPuebloEngine::celebracionesPendientes($pH, $root, $partidaIdH);
ok(count($celebsH) >= 1, 'H1. pending celebration exists');
$hHito = $celebsH[0]['hito_id'];

HistoriaPuebloEngine::ack($pH, $hHito);
$consumedH = HistoriaPuebloEngine::loadConsumed($root, $partidaIdH);
$consumedH[] = $hHito;
HistoriaPuebloEngine::saveConsumed($root, $partidaIdH, $consumedH);
$service->guardar($pH);

// Avanzar reloj
$pH2 = $service->cargar($partidaIdH);
$service->avanzarReloj($pH2, 4);
$service->guardar($pH2);

// Reload
$pH3 = $service->cargarParaRefresh($partidaIdH);
ok(!in_array($hHito, pendingIds($pH3, $root, $partidaIdH), true),
    'H2. 0 pendientes after ACK+avanzarTiempo+reload');

cleanup($partidaIdH);

// ====================================================================
// === I. celebración distinta pendiente: NO queda bloqueada          ===
// ====================================================================
echo "\n=== I. celebración distinta pendiente ===\n";

$pI = partidaConPrimerRecuerdo($service, 'juego_v1', 'hp-test-celeb-fix-i');
$partidaIdI = $pI['meta']['partida_id'];
$resIdsI = getResIds($pI, 2);

$celebsI = HistoriaPuebloEngine::celebracionesPendientes($pI, $root, $partidaIdI);
ok(count($celebsI) >= 1, 'I1. at least 1 pending');
$iHito1 = $celebsI[0]['hito_id'];

// Register a second catalog hito to have two distinct pending celebrations
$secondHito = $celebsI[0]['hito_id'] === 'empezo_el_cotarro' ? 'hito_02' : 'empezo_el_cotarro';
$reg2 = HistoriaPuebloEngine::registrar($pI, $secondHito, $resIdsI);
ok($reg2['ok'], 'I2. second hito registered');

$pIds = pendingIds($pI, $root, $partidaIdI);
ok(in_array($iHito1, $pIds, true), 'I3. first hito pending');
ok(in_array($secondHito, $pIds, true), 'I4. second hito pending');

// ACK only first
HistoriaPuebloEngine::ack($pI, $iHito1);
$consumedI = HistoriaPuebloEngine::loadConsumed($root, $partidaIdI);
$consumedI[] = $iHito1;
HistoriaPuebloEngine::saveConsumed($root, $partidaIdI, $consumedI);

// Save and reload
$service->guardar($pI);
unset($pI);
gc_collect_cycles();

$pI2 = $service->cargarParaRefresh($partidaIdI);
$pIdsAfter = pendingIds($pI2, $root, $partidaIdI);
ok(!in_array($iHito1, $pIdsAfter, true), 'I5. first hito consumed (not pending)');
ok(in_array($secondHito, $pIdsAfter, true), 'I6. second hito still pending (not blocked)');

cleanup($partidaIdI);

// ====================================================================
// === J. ACK-fail-then-retry: fail → still pending → retry OK → once ===
// ====================================================================
echo "\n=== J. ACK-fail-then-retry ===\n";

$pJ = partidaConPrimerRecuerdo($service, 'juego_v1', 'hp-test-celeb-fix-j');
$partidaIdJ = $pJ['meta']['partida_id'];

$celebsJ = HistoriaPuebloEngine::celebracionesPendientes($pJ, $root, $partidaIdJ);
ok(count($celebsJ) >= 1, 'J1. pending celebration exists');
$jHito = $celebsJ[0]['hito_id'];

// Simulate ACK failure: don't call ack(), just check state
ok(in_array($jHito, pendingIds($pJ, $root, $partidaIdJ), true),
    'J2. without ACK, celebration stays pending');

// Simulate retry: now call ACK
$retryAck = HistoriaPuebloEngine::ack($pJ, $jHito);
ok($retryAck === true, 'J3. retry ACK returns true (first real ack)');

// Save consumed + main file (like HistoriaPuebloHandler::ack does)
$consumedJ = HistoriaPuebloEngine::loadConsumed($root, $partidaIdJ);
$consumedJ[] = $jHito;
HistoriaPuebloEngine::saveConsumed($root, $partidaIdJ, $consumedJ);
$service->guardar($pJ);

// Verify consumed exactly once
ok(!in_array($jHito, pendingIds($pJ, $root, $partidaIdJ), true),
    'J4. after retry ACK, celebration consumed');

// Second ACK (idempotent)
$secondAck = HistoriaPuebloEngine::ack($pJ, $jHito);
ok($secondAck === false, 'J5. second ACK returns false (already consumed, idempotent)');
ok(!in_array($jHito, pendingIds($pJ, $root, $partidaIdJ), true),
    'J6. still consumed after idempotent ACK');

// Reload/F5
unset($pJ);
gc_collect_cycles();
$pJ2 = $service->cargarParaRefresh($partidaIdJ);
ok(!in_array($jHito, pendingIds($pJ2, $root, $partidaIdJ), true),
    'J7. F5 after ACK OK: celebration does NOT reappear');

cleanup($partidaIdJ);

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
