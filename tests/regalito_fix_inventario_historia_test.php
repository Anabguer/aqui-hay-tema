<?php
declare(strict_types=1);

/* Test focal: FIX 1 (inventario roto) + FIX 2 (Historia → Regalito).

   Escenarios:
   1 - inventario.listar no da 500 (FIX 1)
   2 - objeto existente en inventario aparece en respuesta
   3 - completar 3/3 misiones → exactamente 1 regalo
   4 - registrar H1 nuevo → exactamente 1 regalo (FIX 2)
   5 - registrar H1 otra vez → 0 adicionales (idempotencia Historia)
   6 - reload + registrar H1 → 0 adicionales (idempotencia RegalitoService)
   7 - simular celebración de H1 → 0 adicionales
   8 - registrar H2 nuevo → exactamente otro regalo
   9 - inventario lleno + H3 nuevo → recompensa pendiente
   10 - liberar hueco + reclamarPendientes → entrega H3 exactamente una vez
   11 - segundo reclamarPendientes → no duplica
   12 - "Aquí empezó el cotarro" → primera creación puede premiar, posteriores NO */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\HistoriaPuebloEngine;
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

function historial_fixture(): array
{
    $p = regalo_fixture_partida([
        'per_a' => regalo_perfil(),
        'per_b' => regalo_perfil(),
    ]);
    $p['features'] = [
        'misiones_diarias_enabled' => true,
        'buzon_enabled' => false,
    ];
    MisionDiariaEngine::ensure($p);
    HistoriaPuebloEngine::ensure($p);
    return $p;
}

function make_mision_real(string $id, int $dia = 1): array
{
    return [
        'id' => $id,
        'plantilla_id' => 'cambio_de_sitio',
        'familia' => 'lugar',
        'dia' => $dia,
        'estado' => 'pendiente',
        'texto' => 'Cambia de sitio',
        'hecho' => 'Ir a otro sitio',
        'params' => ['lugar_id' => 'lug_parque'],
        'exigencia' => 30,
        'cuenta_latido' => false,
    ];
}

function encCambioSitio(int $dia): array
{
    return [
        'id' => 'enc_fix_' . $dia . '_' . bin2hex(random_bytes(3)),
        'tipo' => 'quedada',
        'participantes' => ['per_a', 'per_b'],
        'lugar' => 'lug_parque',
        'intencion' => 'celeste_organizado',
        'dia' => $dia,
    ];
}

$cal = CalibracionConfig::load(dirname(__DIR__));

// ============================================================
// 1: inventario.listar no da 500 (FIX 1 - type mismatch + array > int)
// ============================================================
$p1 = historial_fixture();
InventarioEngine::anadir($p1, 'taza', 1, new CatalogStore(dirname(__DIR__)));
$catalog = new CatalogStore(dirname(__DIR__));
$pendientes = RegalitoRecompensaService::reclamarPendientes($p1, null);
ok(is_array($pendientes), '1: reclamarPendientes no lanza TypeError (FIX 1)');
ok(is_array($pendientes) || $pendientes === null, '1: retorno es array o null');

// ============================================================
// 2: objeto existente en inventario aparece en respuesta
// ============================================================
$p2 = historial_fixture();
InventarioEngine::anadir($p2, 'taza', 2, new CatalogStore(dirname(__DIR__)));
$items = InventarioEngine::listar($p2);
ok(isset($items['taza']) && $items['taza'] === 2, '2: taza x2 aparece en inventario');
ok(InventarioEngine::totalUnidades($p2) === 2, '2: totalUnidades = 2');

// ============================================================
// 3: completar 3/3 misiones → exactamente 1 regalo
// ============================================================
$p3 = historial_fixture();
$p3['misiones_diarias']['items'][] = make_mision_real('fix_a');
$p3['misiones_diarias']['items'][] = make_mision_real('fix_b');
$p3['misiones_diarias']['items'][] = make_mision_real('fix_c');
$antes3 = InventarioEngine::totalUnidades($p3);
for ($i = 0; $i < 3; $i++) {
    $enc = encCambioSitio(1);
    MisionDiariaEngine::onEncuentroCelestine($p3, $enc, $cal, null);
}
$despues3 = InventarioEngine::totalUnidades($p3);
ok($despues3 === $antes3 + 1, '3: 3/3 misiones → exactamente 1 regalo');
$reg3 = $p3['regalito_recompensas']['misiones_diarias:1'] ?? [];
ok(($reg3['estado'] ?? '') === 'entregado', '3: estado entregado en mapa');

// ============================================================
// 4: registrar H1 nuevo → exactamente 1 regalo (FIX 2)
// ============================================================
$p4 = historial_fixture();
$antes4 = InventarioEngine::totalUnidades($p4);
$res4 = HistoriaPuebloEngine::registrar($p4, 'hito_02', ['per_a', 'per_b']);
ok($res4['ya_existia'] === false, '4: hito_02 registrado como nuevo');
ok(!empty($res4['regalito']), '4: regalito entregado en registro nuevo');
$despues4 = InventarioEngine::totalUnidades($p4);
ok($despues4 === $antes4 + 1, '4: inventario creció en 1');
$ctx4 = 'historia:' . HistoriaPuebloEngine::clave('hito_02', ['per_a', 'per_b']);
ok(isset($p4['regalito_recompensas'][$ctx4]), '4: contexto correcto en mapa');

// ============================================================
// 5: registrar H1 otra vez → 0 adicionales (idempotencia Historia)
// ============================================================
$res5 = HistoriaPuebloEngine::registrar($p4, 'hito_02', ['per_a', 'per_b']);
ok($res5['ya_existia'] === true, '5: segundo registrar detecta ya_existia');
ok(!isset($res5['regalito']), '5: no hay campo regalito en respuesta (ya_existia)');
ok(InventarioEngine::totalUnidades($p4) === $despues4, '5: inventario sin cambio');

// ============================================================
// 6: reload + registrar H1 → 0 adicionales (idempotencia RegalitoService)
// ============================================================
$p6 = unserialize(serialize($p4));
$antes6 = InventarioEngine::totalUnidades($p6);
$r6 = RegalitoRecompensaService::otorgar($p6, $ctx4);
ok($r6 === null, '6: otorgar mismo contexto tras reload = null');
ok(InventarioEngine::totalUnidades($p6) === $antes6, '6: inventario sin cambio');

// ============================================================
// 7: simular celebración de H1 → 0 adicionales
// ============================================================
$antes7 = InventarioEngine::totalUnidades($p4);
$res7 = HistoriaPuebloEngine::registrar($p4, 'hito_02', ['per_a', 'per_b']);
ok($res7['ya_existia'] === true, '7: re-registrar = ya_existia');
ok(InventarioEngine::totalUnidades($p4) === $antes7, '7: celebración no genera regalo adicional');

// ============================================================
// 8: registrar H2 nuevo → exactamente otro regalo
// ============================================================
$p8 = historial_fixture();
$antes8 = InventarioEngine::totalUnidades($p8);
$res8a = HistoriaPuebloEngine::registrar($p8, 'hito_02', ['per_a', 'per_b']);
$res8b = HistoriaPuebloEngine::registrar($p8, 'hito_08', ['per_a', 'per_b']);
ok($res8a['ya_existia'] === false, '8a: hito_02 nuevo');
ok($res8b['ya_existia'] === false, '8b: hito_08 nuevo');
$despues8 = InventarioEngine::totalUnidades($p8);
ok($despues8 === $antes8 + 2, '8: dos hitos nuevos → 2 regalos');
$ctx8a = 'historia:' . HistoriaPuebloEngine::clave('hito_02', ['per_a', 'per_b']);
$ctx8b = 'historia:' . HistoriaPuebloEngine::clave('hito_08', ['per_a', 'per_b']);
ok(isset($p8['regalito_recompensas'][$ctx8a]), '8: contexto hito_02');
ok(isset($p8['regalito_recompensas'][$ctx8b]), '8: contexto hito_08');
ok($ctx8a !== $ctx8b, '8: contextos independientes');

// ============================================================
// 9: inventario lleno + H3 nuevo → recompensa pendiente
// ============================================================
$p9 = historial_fixture();
InventarioEngine::anadir($p9, 'libro', 200, new CatalogStore(dirname(__DIR__)));
ok(InventarioEngine::totalUnidades($p9) >= 200, '9: inventario lleno');
$res9 = HistoriaPuebloEngine::registrar($p9, 'hito_03', ['per_a', 'per_b']);
ok($res9['ya_existia'] === false, '9: hito_03 nuevo');
ok(!empty($res9['regalito']), '9: regalito devuelto');
ok($res9['regalito']['pendiente'] === true, '9: estado pendiente (inv lleno)');
$ctx9 = 'historia:' . HistoriaPuebloEngine::clave('hito_03', ['per_a', 'per_b']);
$entry9 = $p9['regalito_recompensas'][$ctx9] ?? [];
ok(($entry9['estado'] ?? '') === 'pendiente', '9: pendiente en mapa');

// ============================================================
// 10: liberar hueco + reclamarPendientes → entrega H3 exactamente una vez
// ============================================================
InventarioEngine::consumir($p9, 'libro', 10);
ok(InventarioEngine::totalUnidades($p9) < 200, '10: inventario con hueco');
$reclamados10 = RegalitoRecompensaService::reclamarPendientes($p9);
ok(count($reclamados10) === 1, '10: reclamar entrega exactamente 1');
ok($reclamados10[0]['contexto'] === $ctx9, '10: contexto entregado es el de hito_03');
$entry10 = $p9['regalito_recompensas'][$ctx9] ?? [];
ok(($entry10['estado'] ?? '') === 'entregado', '10: estado ahora entregado');

// ============================================================
// 11: segundo reclamarPendientes → no duplica
// ============================================================
$antes11 = InventarioEngine::totalUnidades($p9);
$reclamados11 = RegalitoRecompensaService::reclamarPendientes($p9);
ok(count($reclamados11) === 0, '11: segundo reclamar = 0');
ok(InventarioEngine::totalUnidades($p9) === $antes11, '11: inventario sin cambio');

// ============================================================
// 12: "Aquí empezó el cotarro" → primera creación puede premiar, posteriores NO
// ============================================================
$p12 = historial_fixture();
$antes12 = InventarioEngine::totalUnidades($p12);
$res12a = HistoriaPuebloEngine::registrar(
    $p12,
    HistoriaPuebloEngine::HITO_EMPEZO_COTARRO,
    array_keys($p12['residentes']),
);
ok($res12a['ya_existia'] === false, '12a: empezo_el_cotarro registrado como nuevo');
ok(!empty($res12a['regalito']), '12a: regalito entregado en 1ª creación');
$despues12 = InventarioEngine::totalUnidades($p12);
ok($despues12 === $antes12 + 1, '12a: inventario creció en 1');

// Repetir → 0
$res12b = HistoriaPuebloEngine::registrar(
    $p12,
    HistoriaPuebloEngine::HITO_EMPEZO_COTARRO,
    array_keys($p12['residentes']),
);
ok($res12b['ya_existia'] === true, '12b: segundo registrar = ya_existia');
ok(InventarioEngine::totalUnidades($p12) === $despues12, '12b: inventario sin cambio');

// Reload + registrar → 0
$p12c = unserialize(serialize($p12));
$antes12c = InventarioEngine::totalUnidades($p12c);
$res12c = HistoriaPuebloEngine::registrar(
    $p12c,
    HistoriaPuebloEngine::HITO_EMPEZO_COTARRO,
    array_keys($p12c['residentes']),
);
ok($res12c['ya_existia'] === true, '12c: reload + registrar = ya_existia');
ok(InventarioEngine::totalUnidades($p12c) === $antes12c, '12c: inventario sin cambio');

exit($failures > 0 ? 1 : 0);
