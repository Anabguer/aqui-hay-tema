<?php
declare(strict_types=1);

/* Test focal: flujo REAL vía onEncuentroCelestine con plantillas reales.
   Demuestra la cadena completa del call site:
     encuentro → MisionPlayBridge → onEncuentroCelestine → cumplirIndice
     → evaluarRegalito3x3 → RegalitoRecompensaService::otorgar

   Escenarios:
   1 - 1ª misión cumplida → 0 regalito
   2 - 2ª misión cumplida → 0 regalito
   3 - 3ª misión cumplida → exactamente 1 regalito
   4 - reevaluar → 0 duplicados
   5 - reload → 0 duplicados
   6 - inventario lleno → pendiente → reload → liberar → listar → entrega
   7 - contexto estable: mismo dia_pueblo siempre genera mismo contexto
   8 - sin cap global: dos contextos distintos, dos regalitos */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatalogStore;
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

function focal_fixture(): array
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
    return $p;
}

/** Misión real de plantilla 'cambio_de_sitio' (fácil de encuadrar) */
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

/** Encuentro que encaja con cambio_de_sitio */
function encCambioSitio(int $dia): array
{
    return [
        'id' => 'enc_camb_' . $dia . '_' . bin2hex(random_bytes(3)),
        'tipo' => 'quedada',
        'participantes' => ['per_a', 'per_b'],
        'lugar' => 'lug_parque',
        'intencion' => 'celeste_organizado',
        'dia' => $dia,
    ];
}

$cal = CalibracionConfig::load(dirname(__DIR__));

// ============================================================
// 1-3: FLUJO REAL: 1ª → 0, 2ª → 0, 3ª → exactamente 1
// ============================================================
$p = focal_fixture();
$p['misiones_diarias']['items'][] = make_mision_real('foc_a');
$p['misiones_diarias']['items'][] = make_mision_real('foc_b');
$p['misiones_diarias']['items'][] = make_mision_real('foc_c');

// 1ª misión
$enc0 = encCambioSitio(1);
$n0 = MisionDiariaEngine::onEncuentroCelestine($p, $enc0, $cal, null);
ok($n0 === 1, '1: encuentra y completa 1ª misión');
$reg0 = $p['regalito_recompensas']['misiones_diarias:1'] ?? null;
ok($reg0 === null, '1: 1ª misión → 0 regalito');

// 2ª misión
$enc1 = encCambioSitio(1);
$n1 = MisionDiariaEngine::onEncuentroCelestine($p, $enc1, $cal, null);
ok($n1 === 1, '2: encuentra y completa 2ª misión');
$reg1 = $p['regalito_recompensas']['misiones_diarias:1'] ?? null;
ok($reg1 === null, '2: 2ª misión → 0 regalito');

// 3ª misión
$antes_inv = InventarioEngine::totalUnidades($p);
$enc2 = encCambioSitio(1);
$n2 = MisionDiariaEngine::onEncuentroCelestine($p, $enc2, $cal, null);
ok($n2 === 1, '3: encuentra y completa 3ª misión');
$despues_inv = InventarioEngine::totalUnidades($p);
ok($despues_inv === $antes_inv + 1, '3: 3ª misión → exactamente 1 regalito');
$reg2 = $p['regalito_recompensas']['misiones_diarias:1'] ?? [];
ok(($reg2['estado'] ?? '') === 'entregado', '3: estado entregado en mapa');

// ============================================================
// 4: reevaluar → 0 duplicados
// ============================================================
$enc3 = encCambioSitio(1);
$n3 = MisionDiariaEngine::onEncuentroCelestine($p, $enc3, $cal, null);
ok($n3 === 0, '4: encuentro adicional no completa otra misión (ya cumplidas todas)');
ok(InventarioEngine::totalUnidades($p) === $despues_inv, '4: inventario sin cambio');

// ============================================================
// 5: reload → 0 duplicados
// ============================================================
$ctx = 'misiones_diarias:1';
$r = RegalitoRecompensaService::otorgar($p, $ctx);
ok($r === null, '5: segundo otorgar mismo contexto = null (simula reload)');

// ============================================================
// 6: INVENTARIO LLENO → PENDIENTE → RELOAD → LIBERAR → LISTAR → ENTREGA
// ============================================================
$p6 = focal_fixture();
InventarioEngine::anadir($p6, 'libro', 200, new CatalogStore(dirname(__DIR__)));
ok(InventarioEngine::totalUnidades($p6) >= 200, '6: inventario lleno');
$p6['misiones_diarias']['items'][] = make_mision_real('fll_a');
$p6['misiones_diarias']['items'][] = make_mision_real('fll_b');
$p6['misiones_diarias']['items'][] = make_mision_real('fll_c');
// Completar las 3 vía onEncuentroCelestine
for ($i = 0; $i < 3; $i++) {
    $enc = encCambioSitio(1);
    MisionDiariaEngine::onEncuentroCelestine($p6, $enc, $cal, null);
}
$entry6 = $p6['regalito_recompensas']['misiones_diarias:1'] ?? [];
ok(($entry6['estado'] ?? '') === 'pendiente', '6: pendiente en mapa (inv lleno)');
ok(InventarioEngine::totalUnidades($p6) >= 200, '6: inventario sigue lleno');

// Simular reload
$p6b = unserialize(serialize($p6));
$entry6b = $p6b['regalito_recompensas']['misiones_diarias:1'] ?? [];
ok(($entry6b['estado'] ?? '') === 'pendiente', '6: tras reload sigue pendiente');

// Liberar espacio
InventarioEngine::consumir($p6b, 'libro', 10);
ok(InventarioEngine::totalUnidades($p6b) < 200, '6: inventario con hueco');

// reclamarPendientes (simula inventario.listar)
$reclamados = RegalitoRecompensaService::reclamarPendientes($p6b);
ok(count($reclamados) === 1, '6: reclamar entrega exactamente 1');
$entry6c = $p6b['regalito_recompensas']['misiones_diarias:1'] ?? [];
ok(($entry6c['estado'] ?? '') === 'entregado', '6: estado ahora entregado');

// ============================================================
// 7: CONTEXTO ESTABLE
// ============================================================
$p7 = focal_fixture();
$p7['reloj']['dia_pueblo'] = 42;
$p7['misiones_diarias']['items'][] = make_mision_real('fctx_a', 42);
$p7['misiones_diarias']['items'][] = make_mision_real('fctx_b', 42);
$p7['misiones_diarias']['items'][] = make_mision_real('fctx_c', 42);
for ($i = 0; $i < 3; $i++) {
    $enc = encCambioSitio(42);
    MisionDiariaEngine::onEncuentroCelestine($p7, $enc, $cal, null);
}
ok(isset($p7['regalito_recompensas']['misiones_diarias:42']), '7: contexto usa dia_pueblo real (42)');
ok(!isset($p7['regalito_recompensas']['misiones_diarias:1']), '7: NO genera contexto día 1');

// ============================================================
// 8: SIN CAP GLOBAL: dos contextos distintos → dos regalitos
// ============================================================
$p8 = focal_fixture();
RegalitoRecompensaService::otorgar($p8, 'misiones_diarias:10');
RegalitoRecompensaService::otorgar($p8, 'reto_semanal:R3');
ok(isset($p8['regalito_recompensas']['misiones_diarias:10']), '8: contexto misiones_diarias:10');
ok(isset($p8['regalito_recompensas']['reto_semanal:R3']), '8: contexto reto_semanal:R3');
$inv8 = InventarioEngine::totalUnidades($p8);
ok($inv8 === 2, '8: 2 regalitos en inventario (sin cap global)');

exit($failures > 0 ? 1 : 0);
