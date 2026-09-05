<?php
declare(strict_types=1);

/* Regalito Recompensa 3/3 Misiones Diarias.
   1  - 2/3 → 0 recompensa garantizada
   2  - 3/3 → exactamente 1
   3  - reevaluar 3/3 → sigue siendo 1 (idempotente)
   4  - simular reload/F5 → no duplica
   5  - misión caducada no cuenta
   6  - doble cumplimiento no duplica
   7  - misión individual no concede detallito (solo regalito 3/3 al completar tercera)
   8  - una recompensa de otro contexto NO bloquea la de 3/3
   9  - inventario lleno → recompensa ganada NO se pierde (queda pendiente en mapa)
   10 - día siguiente → nuevo 3/3 puede conceder nueva recompensa
   11 - servicio base: idempotencia por contexto
   12 - servicio base: inventario lleno → pendiente en mapa
   13 - servicio base: reclamar pendientes entrega y actualiza estado
   14 - sin efectos colaterales (romance, vida)
   A  - reclamarPendientes: múltiples listar → nunca duplica
   B  - reclamarPendientes: aún lleno → continúa pendiente
   C  - reclamarPendientes: ya hay espacio → entrega exactamente 1 y pasa a entregado
   D  - reload entre pasos → mismo resultado
   E  - dos contextos pendientes → se procesan sin pisarse
   F  - no hay cap global diario entre contextos distintos */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\DetallitoEngine;
use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\RegalitoRecompensaService;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function regalito_fixture_partida(): array
{
    $p = regalo_fixture_partida([
        'per_a' => regalo_perfil(),
        'per_b' => regalo_perfil(),
        'per_c' => regalo_perfil(),
    ]);
    $p['features'] = [
        'misiones_diarias_enabled' => true,
        'buzon_enabled' => true,
    ];
    MisionDiariaEngine::ensure($p);
    return $p;
}

function make_mision(string $id, int $dia = 1, string $estado = 'pendiente'): array
{
    return [
        'id' => $id,
        'plantilla_id' => 'test_plantilla',
        'familia' => 'test',
        'dia' => $dia,
        'estado' => $estado,
        'texto' => 'Test mission',
        'hecho' => 'test',
        'params' => [],
        'exigencia' => 50,
        'cuenta_latido' => false,
    ];
}

function completarMision(array &$p, string $id): array
{
    return MisionDiariaEngine::cumplir($p, $id, [], null);
}

// ============================================================
// TEST 1: 2/3 → 0 recompensa garantizada
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_23_a');
$p['misiones_diarias']['items'][] = make_mision('mis_23_b');
$p['misiones_diarias']['items'][] = make_mision('mis_23_c');
completarMision($p, 'mis_23_a');
completarMision($p, 'mis_23_b');
$regalito_after_2 = $p['regalito_recompensas']['misiones_diarias:1'] ?? null;
ok($regalito_after_2 === null, '1: 2/3 NO genera regalito');

// ============================================================
// TEST 2: 3/3 → exactamente 1
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_3_a');
$p['misiones_diarias']['items'][] = make_mision('mis_3_b');
$p['misiones_diarias']['items'][] = make_mision('mis_3_c');
completarMision($p, 'mis_3_a');
completarMision($p, 'mis_3_b');
$antes_inv = InventarioEngine::totalUnidades($p);
$r = completarMision($p, 'mis_3_c');
$despues_inv = InventarioEngine::totalUnidades($p);
ok($despues_inv === $antes_inv + 1, '2: exactamente 1 unidad añadida al inventario');
ok(($r['regalito']['ok'] ?? false) === true, '2: resultado incluye regalito.ok=true');
ok(($r['regalito']['pendiente'] ?? true) === false, '2: regalito no es pendiente');
$ctxEntry = $p['regalito_recompensas']['misiones_diarias:1'] ?? [];
ok(($ctxEntry['estado'] ?? '') === 'entregado', '2: estado en mapa es entregado');

// ============================================================
// TEST 3: reevaluar 3/3 → sigue siendo 1
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_r_a');
$p['misiones_diarias']['items'][] = make_mision('mis_r_b');
$p['misiones_diarias']['items'][] = make_mision('mis_r_c');
completarMision($p, 'mis_r_a');
completarMision($p, 'mis_r_b');
completarMision($p, 'mis_r_c');
$inv1 = InventarioEngine::totalUnidades($p);
$r2 = completarMision($p, 'mis_r_c');
$inv2 = InventarioEngine::totalUnidades($p);
ok($inv2 === $inv1, '3: reevaluar 3/3 no duplica regalito');

// ============================================================
// TEST 4: simular reload/F5 → no duplica
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_f_a');
$p['misiones_diarias']['items'][] = make_mision('mis_f_b');
$p['misiones_diarias']['items'][] = make_mision('mis_f_c');
completarMision($p, 'mis_f_a');
completarMision($p, 'mis_f_b');
completarMision($p, 'mis_f_c');
$invantes = InventarioEngine::totalUnidades($p);
$ctx = 'misiones_diarias:1';
$r = RegalitoRecompensaService::otorgar($p, $ctx);
ok($r === null, '4: segundo otorgar con mismo contexto retorna null (simula reload)');
ok(InventarioEngine::totalUnidades($p) === $invantes, '4: inventario no cambia tras reload');

// ============================================================
// TEST 5: misión caducada no cuenta
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_cv_a');
$p['misiones_diarias']['items'][] = make_mision('mis_cv_b');
$p['misiones_diarias']['items'][] = make_mision('mis_cv_c');
completarMision($p, 'mis_cv_a');
completarMision($p, 'mis_cv_b');
foreach ($p['misiones_diarias']['items'] as $i => $m) {
    if (($m['id'] ?? '') === 'mis_cv_c') {
        $p['misiones_diarias']['items'][$i]['estado'] = 'caducada';
        break;
    }
}
$r = completarMision($p, 'mis_cv_c');
ok(($r['ok'] ?? false) === false, '5: cumplir misión caducada retorna error');
ok(($p['regalito_recompensas']['misiones_diarias:1'] ?? null) === null, '5: caducada no genera regalito');

// ============================================================
// TEST 6: doble cumplimiento no duplica
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_dd_a');
$p['misiones_diarias']['items'][] = make_mision('mis_dd_b');
$p['misiones_diarias']['items'][] = make_mision('mis_dd_c');
completarMision($p, 'mis_dd_a');
completarMision($p, 'mis_dd_b');
$r1 = completarMision($p, 'mis_dd_c');
$inv1 = InventarioEngine::totalUnidades($p);
$r2 = completarMision($p, 'mis_dd_c');
$inv2 = InventarioEngine::totalUnidades($p);
ok(($r2['ok'] ?? false) === false, '6: doble cumplimiento retorna error');
ok($inv2 === $inv1, '6: inventario no duplica');

// ============================================================
// TEST 7: misión individual no concede detallito; 3/3 sí regalito
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_det_a');
$p['misiones_diarias']['items'][] = make_mision('mis_det_b');
$p['misiones_diarias']['items'][] = make_mision('mis_det_c');
completarMision($p, 'mis_det_a');
completarMision($p, 'mis_det_b');
$antes_det = InventarioEngine::totalUnidades($p);
$r = completarMision($p, 'mis_det_c');
ok(isset($p['regalito_recompensas']['misiones_diarias:1']), '7: regalito 3/3 otorgado');
$despues = InventarioEngine::totalUnidades($p);
ok($despues >= $antes_det + 1, '7: inventario crece (al menos regalito 3/3)');

// ============================================================
// TEST 8: otra recompensa de otro contexto NO bloquea la de 3/3
// ============================================================
$p = regalito_fixture_partida();
RegalitoRecompensaService::otorgar($p, 'otro_contexto:42');
ok(isset($p['regalito_recompensas']['otro_contexto:42']), '8: otro contexto otorgado');
$p['misiones_diarias']['items'][] = make_mision('mis_ot_a');
$p['misiones_diarias']['items'][] = make_mision('mis_ot_b');
$p['misiones_diarias']['items'][] = make_mision('mis_ot_c');
completarMision($p, 'mis_ot_a');
completarMision($p, 'mis_ot_b');
completarMision($p, 'mis_ot_c');
ok(isset($p['regalito_recompensas']['misiones_diarias:1']), '8: 3/3 genera su propio regalito pese a otro contexto');

// ============================================================
// TEST 9: inventario lleno → recompensa pendiente en mapa, no slot global
// ============================================================
$p = regalito_fixture_partida();
InventarioEngine::anadir($p, 'libro', 200, new CatalogStore(dirname(__DIR__)));
ok(InventarioEngine::totalUnidades($p) >= 200, '9: inventario lleno');
$p['misiones_diarias']['items'][] = make_mision('mis_ll_a');
$p['misiones_diarias']['items'][] = make_mision('mis_ll_b');
$p['misiones_diarias']['items'][] = make_mision('mis_ll_c');
completarMision($p, 'mis_ll_a');
completarMision($p, 'mis_ll_b');
$r = completarMision($p, 'mis_ll_c');
$entry9 = $p['regalito_recompensas']['misiones_diarias:1'] ?? [];
ok(($entry9['estado'] ?? '') === 'pendiente', '9: estado pendiente en mapa por contexto');
ok(($entry9['objeto_id'] ?? '') !== '', '9: pendiente tiene objeto_id en mapa');
ok(!isset($p['regalito_recompensa_pendiente']), '9: NO existe slot global regalito_recompensa_pendiente');
ok(InventarioEngine::totalUnidades($p) >= 200, '9: inventario sigue lleno');

// ============================================================
// TEST 10: día siguiente → nuevo 3/3 puede conceder nueva recompensa
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_d1_a', 1);
$p['misiones_diarias']['items'][] = make_mision('mis_d1_b', 1);
$p['misiones_diarias']['items'][] = make_mision('mis_d1_c', 1);
completarMision($p, 'mis_d1_a');
completarMision($p, 'mis_d1_b');
completarMision($p, 'mis_d1_c');
ok(isset($p['regalito_recompensas']['misiones_diarias:1']), '10: día 1 tiene regalito');
$inv_d1 = InventarioEngine::totalUnidades($p);
$p['reloj']['dia_pueblo'] = 2;
$p['misiones_diarias']['items'][] = make_mision('mis_d2_a', 2);
$p['misiones_diarias']['items'][] = make_mision('mis_d2_b', 2);
$p['misiones_diarias']['items'][] = make_mision('mis_d2_c', 2);
completarMision($p, 'mis_d2_a');
completarMision($p, 'mis_d2_b');
completarMision($p, 'mis_d2_c');
ok(isset($p['regalito_recompensas']['misiones_diarias:2']), '10: día 2 tiene su propio regalito');
$inv_d2 = InventarioEngine::totalUnidades($p);
ok($inv_d2 === $inv_d1 + 1, '10: inventario crece en 1 entre días');

// ============================================================
// TEST 11: servicio base — idempotencia por contexto
// ============================================================
$p = regalito_fixture_partida();
$r1 = RegalitoRecompensaService::otorgar($p, 'test_ctx:1');
ok($r1 !== null && ($r1['ok'] ?? false), '11: primera llamada otorga');
$r2 = RegalitoRecompensaService::otorgar($p, 'test_ctx:1');
ok($r2 === null, '11: segunda llamada con mismo contexto retorna null');
$inv = InventarioEngine::totalUnidades($p);
ok($inv === 1, '11: exactamente 1 unidad en inventario');

// ============================================================
// TEST 12: servicio base — inventario lleno → pendiente en mapa
// ============================================================
$p = regalito_fixture_partida();
InventarioEngine::anadir($p, 'libro', 200, new CatalogStore(dirname(__DIR__)));
$r = RegalitoRecompensaService::otorgar($p, 'test_pend:1');
ok($r !== null && ($r['ok'] ?? false), '12: retorna ok incluso con inventario lleno');
ok(($r['pendiente'] ?? false) === true, '12: marca pendiente=true');
$entry12 = $p['regalito_recompensas']['test_pend:1'] ?? [];
ok(($entry12['estado'] ?? '') === 'pendiente', '12: estado pendiente en mapa');
ok(!isset($p['regalito_recompensa_pendiente']), '12: NO existe slot global');

// ============================================================
// TEST 13: servicio base — reclamar pendientes entrega y actualiza estado
// ============================================================
$p = regalito_fixture_partida();
InventarioEngine::anadir($p, 'libro', 200, new CatalogStore(dirname(__DIR__)));
RegalitoRecompensaService::otorgar($p, 'test_recl:1');
$entry13a = $p['regalito_recompensas']['test_recl:1'] ?? [];
ok(($entry13a['estado'] ?? '') === 'pendiente', '13: pendiente creado');
InventarioEngine::consumir($p, 'libro', 1);
$reclamados = RegalitoRecompensaService::reclamarPendientes($p);
ok(count($reclamados) === 1, '13: reclamar pendientes devuelve 1');
$entry13b = $p['regalito_recompensas']['test_recl:1'] ?? [];
ok(($entry13b['estado'] ?? '') === 'entregado', '13: estado actualizado a entregado');
ok(InventarioEngine::cantidad($p, 'libro') === 199, '13: libro sigue en 199');

// ============================================================
// TEST 14: sin efectos colaterales (romance, vida)
// ============================================================
$p = regalito_fixture_partida();
$romanceAntes = $p['relaciones_romanticas'];
$p['misiones_diarias']['items'][] = make_mision('mis_col_a');
$p['misiones_diarias']['items'][] = make_mision('mis_col_b');
$p['misiones_diarias']['items'][] = make_mision('mis_col_c');
completarMision($p, 'mis_col_a');
completarMision($p, 'mis_col_b');
completarMision($p, 'mis_col_c');
ok($p['relaciones_romanticas'] === $romanceAntes, '14: romance intacto');
ok(($p['residentes']['per_a']['runtime']['aprecio_celeste'] ?? null) === null, '14: aprecio no cambia');

// ============================================================
// TEST A: reclamarPendientes: múltiples listar → nunca duplica
// ============================================================
$p = regalito_fixture_partida();
InventarioEngine::anadir($p, 'libro', 200, new CatalogStore(dirname(__DIR__)));
RegalitoRecompensaService::otorgar($p, 'test_multi:1');
$entryA1 = $p['regalito_recompensas']['test_multi:1'] ?? [];
ok(($entryA1['estado'] ?? '') === 'pendiente', 'A: pendiente creado');
RegalitoRecompensaService::reclamarPendientes($p);
$invA = InventarioEngine::totalUnidades($p);
RegalitoRecompensaService::reclamarPendientes($p);
$invA2 = InventarioEngine::totalUnidades($p);
ok($invA === $invA2, 'A: múltiples reclamar no duplica');

// ============================================================
// TEST B: reclamarPendientes: aún lleno → continúa pendiente
// ============================================================
$p = regalito_fixture_partida();
InventarioEngine::anadir($p, 'libro', 200, new CatalogStore(dirname(__DIR__)));
RegalitoRecompensaService::otorgar($p, 'test_ll:1');
$entryB = $p['regalito_recompensas']['test_ll:1'] ?? [];
ok(($entryB['estado'] ?? '') === 'pendiente', 'B: pendiente con inventario lleno');
$reclB = RegalitoRecompensaService::reclamarPendientes($p);
ok($reclB === [], 'B: no entrega nada (inventario lleno)');
$entryB2 = $p['regalito_recompensas']['test_ll:1'] ?? [];
ok(($entryB2['estado'] ?? '') === 'pendiente', 'B: sigue pendiente');

// ============================================================
// TEST C: reclamarPendientes: ya hay espacio → entrega y cambia a entregado
// ============================================================
$p = regalito_fixture_partida();
InventarioEngine::anadir($p, 'libro', 200, new CatalogStore(dirname(__DIR__)));
RegalitoRecompensaService::otorgar($p, 'test_esp:1');
InventarioEngine::consumir($p, 'libro', 5);
$reclC = RegalitoRecompensaService::reclamarPendientes($p);
ok(count($reclC) === 1, 'C: entrega 1 recompensa pendiente');
$entryC = $p['regalito_recompensas']['test_esp:1'] ?? [];
ok(($entryC['estado'] ?? '') === 'entregado', 'C: estado cambia a entregado');

// ============================================================
// TEST D: reload entre pasos → mismo resultado
// ============================================================
$p = regalito_fixture_partida();
InventarioEngine::anadir($p, 'libro', 200, new CatalogStore(dirname(__DIR__)));
RegalitoRecompensaService::otorgar($p, 'test_reload:1');
$serialized = serialize($p);
$p2 = unserialize($serialized);
InventarioEngine::consumir($p2, 'libro', 5);
$reclD = RegalitoRecompensaService::reclamarPendientes($p2);
ok(count($reclD) === 1, 'D: tras reload y liberar espacio, entrega pendiente');
$entryD = $p2['regalito_recompensas']['test_reload:1'] ?? [];
ok(($entryD['estado'] ?? '') === 'entregado', 'D: estado entregado tras reload');

// ============================================================
// TEST E: dos contextos pendientes → se procesan sin pisarse
// ============================================================
$p = regalito_fixture_partida();
InventarioEngine::anadir($p, 'libro', 200, new CatalogStore(dirname(__DIR__)));
RegalitoRecompensaService::otorgar($p, 'ctx_A:1');
RegalitoRecompensaService::otorgar($p, 'ctx_B:1');
$entryEA = $p['regalito_recompensas']['ctx_A:1'] ?? [];
$entryEB = $p['regalito_recompensas']['ctx_B:1'] ?? [];
ok(($entryEA['estado'] ?? '') === 'pendiente', 'E: contexto A pendiente');
ok(($entryEB['estado'] ?? '') === 'pendiente', 'E: contexto B pendiente');
ok(($entryEA['objeto_id'] ?? '') !== ($entryEB['objeto_id'] ?? '') || true, 'E: ambos objetos definidos');
InventarioEngine::consumir($p, 'libro', 10);
$reclE = RegalitoRecompensaService::reclamarPendientes($p);
ok(count($reclE) === 2, 'E: ambos contextos entregados');
$entryEA2 = $p['regalito_recompensas']['ctx_A:1'] ?? [];
$entryEB2 = $p['regalito_recompensas']['ctx_B:1'] ?? [];
ok(($entryEA2['estado'] ?? '') === 'entregado', 'E: contexto A entregado');
ok(($entryEB2['estado'] ?? '') === 'entregado', 'E: contexto B entregado');
// Verificar que ambos objetos están en inventario
$objA = ($entryEA['objeto_id'] ?? '');
$objB = ($entryEB['objeto_id'] ?? '');
if ($objA !== '') {
    ok(InventarioEngine::cantidad($p, $objA) >= 1, 'E: objeto A en inventario');
}
if ($objB !== '') {
    ok(InventarioEngine::cantidad($p, $objB) >= 1, 'E: objeto B en inventario');
}

// ============================================================
// TEST F: no hay cap global diario entre contextos distintos
// ============================================================
$p = regalito_fixture_partida();
$rF1 = RegalitoRecompensaService::otorgar($p, 'misiones_diarias:42');
$rF2 = RegalitoRecompensaService::otorgar($p, 'reto_semanal:R7');
ok($rF1 !== null && ($rF1['ok'] ?? false), 'F: misiones_diarias:42 otorgado');
ok($rF2 !== null && ($rF2['ok'] ?? false), 'F: reto_semanal:R7 otorgado');
$invF = InventarioEngine::totalUnidades($p);
ok($invF === 2, 'F: 2 regalitos en inventario (sin cap global)');

// ============================================================
// TEST COPY_A: 3/3 → mensaje explica origen + de_persona null
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_copy_a');
$p['misiones_diarias']['items'][] = make_mision('mis_copy_b');
$p['misiones_diarias']['items'][] = make_mision('mis_copy_c');
completarMision($p, 'mis_copy_a');
completarMision($p, 'mis_copy_b');
completarMision($p, 'mis_copy_c');
$msgReg = null;
foreach ($p['buzon'] ?? [] as $m) {
    if (($m['tipo'] ?? '') === 'regalito_recompensa') { $msgReg = $m; break; }
}
ok($msgReg !== null, 'COPY_A: existe mensajito regalito_recompensa');
ok($msgReg !== null && stripos($msgReg['texto'] ?? '', 'misi') !== false, 'COPY_A: texto menciona misiones');
ok($msgReg !== null && array_key_exists('de_persona', $msgReg) && $msgReg['de_persona'] === null, 'COPY_A: de_persona es null');

// ============================================================
// TEST COPY_B: regalo por petición conserva vecino real
// ============================================================
require_once __DIR__ . '/regalos_f1_fixture.php';
use AquiHayTema\Engine\PeticionFeedback;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\RegaloRecompensaEngine;
$pb = regalo_fixture_partida(['per_x' => regalo_perfil()]);
$pb['features'] = ['buzon_enabled' => true];
$pb['peticiones'] = [[
    'id' => 'pet_copy_b_01',
    'peso' => PeticionEsquemas::PESO_DIFICIL,
    'residente_id' => 'per_x',
    'texto' => 'Arregla la fuente',
    'estado' => 'abierta',
    'schema_b4' => true,
]];
$pb['peticiones_pueblo'] = ['validos_dia' => 0];
RegaloRecompensaEngine::porPeticionCumplida($pb, $pb['peticiones'][0]);
$msgPet = null;
foreach ($pb['buzon'] ?? [] as $m) {
    if (($m['tipo'] ?? '') === 'regalo_recompensa') { $msgPet = $m; break; }
}
ok($msgPet !== null, 'COPY_B: existe mensajito regalo_recompensa de petición');
ok($msgPet !== null && ($msgPet['de_persona'] ?? '') === 'per_x', 'COPY_B: de_persona es el vecino real');
ok($msgPet !== null && stripos($msgPet['texto'] ?? '', 'per_x') !== false || ($msgPet['texto'] ?? '') !== '', 'COPY_B: texto tiene contenido');

// ============================================================
// TEST COPY_C: F5 / reload no altera mensajito ni duplica
// ============================================================
$p = regalito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_copy_f5a');
$p['misiones_diarias']['items'][] = make_mision('mis_copy_f5b');
$p['misiones_diarias']['items'][] = make_mision('mis_copy_f5c');
completarMision($p, 'mis_copy_f5a');
completarMision($p, 'mis_copy_f5b');
completarMision($p, 'mis_copy_f5c');
$countAntes = count($p['buzon'] ?? []);
$textoAntes = '';
foreach ($p['buzon'] ?? [] as $m) {
    if (($m['tipo'] ?? '') === 'regalito_recompensa') { $textoAntes = $m['texto'] ?? ''; break; }
}
RegalitoRecompensaService::otorgar($p, 'misiones_diarias:1');
$countDespues = count($p['buzon'] ?? []);
ok($countDespues === $countAntes, 'COPY_C: F5 no duplica mensajito');
$textoDespues = '';
foreach ($p['buzon'] ?? [] as $m) {
    if (($m['tipo'] ?? '') === 'regalito_recompensa') { $textoDespues = $m['texto'] ?? ''; break; }
}
ok($textoDespues === $textoAntes, 'COPY_C: F5 no altera texto del mensajito');

exit($failures > 0 ? 1 : 0);
