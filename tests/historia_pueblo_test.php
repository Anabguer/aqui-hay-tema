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

$service = new PartidaService($root);

// --- 1) Ensure inicializa el array ---
$p1 = $service->nuevaPartida('juego_v1', 'hp-test-1');
ok(isset($p1['historia_pueblo']), '1. historia_pueblo existe en partida nueva');
ok(is_array($p1['historia_pueblo']), '1. es array');

// --- 2) Primer hito registrado al crear partida ---
$entradas = HistoriaPuebloEngine::listar($p1);
ok(count($entradas) >= 1, '2. al menos 1 hito registrado en partida nueva');

// --- 3) Hito empezo_el_cotarro existe ---
$clave = HistoriaPuebloEngine::clave(HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, array_keys($p1['residentes']));
$existe = HistoriaPuebloEngine::existe($p1, $clave);
ok($existe, '3. hito empezo_el_cotarro existe');

// --- 4) El hito tiene los protagonistas correctos ---
$entrada = HistoriaPuebloEngine::obtener($p1, $clave);
ok($entrada !== null, '4. entrada no null');
ok(!empty($entrada['revelado']), '4.1 hito está revelado');
ok(count($entrada['protagonistas']) === count($p1['residentes']), '4.2 todos los residentes son protagonistas');
ok(($entrada['dia'] ?? 0) >= 1, '4.3 día registrado >= 1');

// --- 5) Idempotencia ---
$nAntes = count(HistoriaPuebloEngine::listar($p1));
$resultado = HistoriaPuebloEngine::registrar($p1, HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, array_keys($p1['residentes']));
ok($resultado['ya_existia'] === true, '5. registrar duplicado retorna ya_existia=true');
ok(count(HistoriaPuebloEngine::listar($p1)) === $nAntes, '5.1 no se duplica entrada');

// --- 6) Vista snapshot ---
$snapshot = HistoriaPuebloVista::snapshot($p1);
ok($snapshot['total_revelados'] >= 1, '6. snapshot tiene al menos 1 revelado');
ok($snapshot['total_hitos'] >= 1, '6.1 snapshot tiene al menos 1 hito total');
ok(count($snapshot['hitos']) >= 1, '6.2 snapshot tiene al menos 1 hito en lista');

// --- 7) Vista hito revelado ---
$primero = $snapshot['hitos'][0];
ok($primero['revelado'] === true, '7. primer hito está revelado');
ok(!empty($primero['nombre']), '7.1 tiene nombre');
ok(!empty($primero['protagonistas']), '7.2 tiene protagonistas');
ok(($primero['dia'] ?? 0) >= 1, '7.3 tiene día');

// --- 8) Vista protagonista tiene nombre ---
$protagonista = $primero['protagonistas'][0];
ok(!empty($protagonista['nombre']), '8. protagonista tiene nombre');
ok(!empty($protagonista['id']), '8.1 protagonista tiene id');

// --- 9) Total revelados ---
ok(HistoriaPuebloEngine::totalRevelados($p1) >= 1, '9. totalRevelados >= 1');

// --- 10) Guardar y recargar ---
$service->guardar($p1);
$p2 = $service->cargar($p1['meta']['partida_id']);
$entradas2 = HistoriaPuebloEngine::listar($p2);
ok(count($entradas2) >= 1, '10. persistencia: hitos sobreviven guardar/cargar');

// --- 11) Nombre del hito en catálogo ---
$catalogo = HistoriaPuebloEngine::catalogo($p1);
ok(count($catalogo) >= 1, '11. catálogo tiene al menos 1 hito');
$catPrimero = $catalogo[0];
ok($catPrimero['id'] === HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, '11.1 primer hito del catálogo es empezo_el_cotarro');
ok($catPrimero['nombre'] === 'AQUÍ EMPEZÓ EL COTARRO', '11.2 nombre correcto');
ok($catPrimero['revelado'] === true, '11.3 primer hito revelado en catálogo');

// ====================================================================
// === ACK + consumed file persistence tests                         ===
// ====================================================================

// --- 12) Celebration starts as pendiente ---
$partidaId = $p2['meta']['partida_id'];
$celebsPend = HistoriaPuebloEngine::celebracionesPendientes($p2, $root, $partidaId);
ok(count($celebsPend) >= 1, '12. celebracionesPendientes returns pending after load');
$celebHito = $celebsPend[0]['hito_id'];

// --- 13) ack() marks celebracion_estado as consumida in memory ---
$ackResult = HistoriaPuebloEngine::ack($p2, $celebHito);
ok($ackResult === true, '13. ack() returns true for pending celebration');
$entradaAfterAck = HistoriaPuebloEngine::obtener($p2, HistoriaPuebloEngine::clave($celebHito, array_keys($p2['residentes'])));
ok($entradaAfterAck['celebracion_estado'] === 'consumida', '13.1 celebracion_estado is consumida after ack in-memory');

// --- 14) celebracionesPendientes returns empty after ack in-memory ---
$celebsPendAfterAck = HistoriaPuebloEngine::celebracionesPendientes($p2, $root, $partidaId);
ok(count($celebsPendAfterAck) === 0, '14. celebracionesPendientes empty after ack in-memory');

// --- 15) Save consumed file + reload: celebration stays consumed (main file check) ---
HistoriaPuebloEngine::saveConsumed($root, $partidaId, [$celebHito]);
$service->guardar($p2);
$p3 = $service->cargar($partidaId);
$celebsPendReloaded = HistoriaPuebloEngine::celebracionesPendientes($p3, $root, $partidaId);
ok(count($celebsPendReloaded) === 0, '15. celebracionesPendientes empty after save+reload (main file consumed)');

// --- 16) Consumed file contains the hito_id ---
$consumed = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
ok(in_array($celebHito, $consumed, true), '16. consumed file contains hito_id');

// --- 17) ack() is idempotent (already consumed) ---
$ackResult2 = HistoriaPuebloEngine::ack($p3, $celebHito);
ok($ackResult2 === false, '17. ack() returns false for already-consumed celebration');

// --- 18) Race condition simulation: overwrite celebracion_estado to pendiente directly in array ---
foreach ($p3['historia_pueblo'] as &$entry) {
    if (($entry['hito_id'] ?? '') === $celebHito) {
        $entry['celebracion_estado'] = 'pendiente';
    }
}
unset($entry);
$celebsPendRace = HistoriaPuebloEngine::celebracionesPendientes($p3, $root, $partidaId);
ok(count($celebsPendRace) === 0, '18. consumed file protects against race (celebracion_estado=pendiente but consumed)');

// --- 19) If consumed file is missing AND estado is pendiente, celebration reappears ---
$consumedPath = HistoriaPuebloEngine::consumedPath($root, $partidaId);
if (is_file($consumedPath)) {
    unlink($consumedPath);
}
$celebsPendNoFile = HistoriaPuebloEngine::celebracionesPendientes($p3, $root, $partidaId);
ok(count($celebsPendNoFile) === 1, '19. without consumed file AND pendiente state, celebration reappears');
ok($celebsPendNoFile[0]['hito_id'] === $celebHito, '19.1 reappeared celebration is the correct hito');

// --- 20) SaveConsumed writes atomically (temp+rename) ---
HistoriaPuebloEngine::saveConsumed($root, $partidaId, ['test_hito_atomic']);
$consumedAtomic = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
ok(in_array('test_hito_atomic', $consumedAtomic, true), '20. saveConsumed persists atomically');
$tempFiles = glob($consumedPath . '.tmp.*');
ok($tempFiles === false || count($tempFiles) === 0, '20.1 no temp files left after saveConsumed');

// ====================================================================
// === reconcileConsumedState() tests                                 ===
// ====================================================================

// --- 21) reconcileConsumedState corrects stale pendiente → consumida ---
HistoriaPuebloEngine::saveConsumed($root, $partidaId, [$celebHito]);
foreach ($p3['historia_pueblo'] as &$entry) {
    if (($entry['hito_id'] ?? '') === $celebHito) {
        $entry['celebracion_estado'] = 'pendiente';
    }
}
unset($entry);
$corrected = HistoriaPuebloEngine::reconcileConsumedState($p3, $root, $partidaId);
ok($corrected === true, '21. reconcileConsumedState returns true when correcting stale entry');
$reconciledEntry = HistoriaPuebloEngine::obtener($p3, HistoriaPuebloEngine::clave($celebHito, array_keys($p3['residentes'])));
ok($reconciledEntry['celebracion_estado'] === 'consumida', '21.1 celebracion_estado corrected to consumida');

// --- 22) reconcileConsumedState is no-op when already consumida ---
$corrected2 = HistoriaPuebloEngine::reconcileConsumedState($p3, $root, $partidaId);
ok($corrected2 === false, '22. reconcileConsumedState returns false when already consistent');

// --- 23) reconcileConsumedState is no-op when consumed file is empty ---
if (is_file($consumedPath)) {
    unlink($consumedPath);
}
foreach ($p3['historia_pueblo'] as &$entry) {
    if (($entry['hito_id'] ?? '') === $celebHito) {
        $entry['celebracion_estado'] = 'pendiente';
    }
}
unset($entry);
$corrected3 = HistoriaPuebloEngine::reconcileConsumedState($p3, $root, $partidaId);
ok($corrected3 === false, '23. reconcileConsumedState returns false when no consumed file');
$entryStillPending = HistoriaPuebloEngine::obtener($p3, HistoriaPuebloEngine::clave($celebHito, array_keys($p3['residentes'])));
ok($entryStillPending['celebracion_estado'] === 'pendiente', '23.1 celebracion_estado remains pendiente without consumed file');

// --- 24) Race condition simulation: save with stale pendiente, then reconcile + save ---
HistoriaPuebloEngine::saveConsumed($root, $partidaId, [$celebHito]);
foreach ($p3['historia_pueblo'] as &$entry) {
    if (($entry['hito_id'] ?? '') === $celebHito) {
        $entry['celebracion_estado'] = 'pendiente';
    }
}
unset($entry);
$service->guardar($p3);
$p4 = $service->cargar($partidaId);
$celebsPendAfterRace = HistoriaPuebloEngine::celebracionesPendientes($p4, $root, $partidaId);
ok(count($celebsPendAfterRace) === 0, '24. celebration stays consumed after race+reload (consumed file protects)');
$reconciledEntry2 = HistoriaPuebloEngine::obtener($p4, HistoriaPuebloEngine::clave($celebHito, array_keys($p4['residentes'])));
ok($reconciledEntry2['celebracion_estado'] === 'consumida', '24.1 main file reconciled to consumida after cargar()');

// Cleanup
if (is_file($consumedPath)) {
    unlink($consumedPath);
}

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
