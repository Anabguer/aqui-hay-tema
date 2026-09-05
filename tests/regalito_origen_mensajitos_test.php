<?php
declare(strict_types=1);

/* Regalitos — origen visible, mensajitos e Historia (A–F del spec).

   A - 1 misión individual → 0 regalo
   B - 3/3 → 1 regalo + mensajito origen misiones + nombre + imagen
   C - nuevo recuerdo Historia → 1 regalo + mensajito origen historia + asociación
   D - reabrir recuerdo → recompensa visible, 0 regalo nuevo
   E - reload/F5 simulado → 0 duplicados
   F - animación pendiente solo la primera vez
*/

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\DetallitoEngine;
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

function fixture(): array
{
    $p = regalo_fixture_partida([
        'per_a' => regalo_perfil(),
        'per_b' => regalo_perfil(),
    ]);
    $p['features'] = [
        'misiones_diarias_enabled' => true,
        'buzon_enabled' => true,
    ];
    MisionDiariaEngine::ensure($p);
    HistoriaPuebloEngine::ensure($p);
    return $p;
}

function make_mision(string $id, int $dia = 1): array
{
    return [
        'id' => $id,
        'plantilla_id' => 'test_plantilla',
        'familia' => 'test',
        'dia' => $dia,
        'estado' => 'pendiente',
        'texto' => 'Test mission',
        'hecho' => 'test',
        'params' => [],
        'exigencia' => 50,
        'cuenta_latido' => false,
    ];
}

function mensajito_regalito(array $partida, string $origenEsperado): ?array
{
    foreach (BuzonEngine::listar($partida) as $m) {
        if (!is_array($m)) {
            continue;
        }
        if (($m['tipo'] ?? '') !== RegalitoRecompensaService::TIPO_MENSAJE) {
            continue;
        }
        $o = $m['origen'] ?? [];
        if (($o['regalito_origen'] ?? '') === $origenEsperado) {
            return $m;
        }
    }
    return null;
}

// --- A: 1 misión individual → 0 regalo ---
$pA = fixture();
$pA['misiones_diarias']['items'][] = make_mision('orig_a1');
$antesA = InventarioEngine::totalUnidades($pA);
$det = DetallitoEngine::alCumplirMision($pA, $pA['misiones_diarias']['items'][0]);
MisionDiariaEngine::cumplir($pA, 'orig_a1', [], null);
$despuesA = InventarioEngine::totalUnidades($pA);
ok($det === null, 'A: DetallitoEngine desactivado (null)');
ok($despuesA === $antesA, 'A: 1 misión individual no concede regalo');
ok(!isset($pA['regalito_recompensas']['misiones_diarias:1']), 'A: sin mapa 3/3 tras 1 misión');

// --- B: 3/3 → 1 regalo + mensajito ---
$pB = fixture();
$pB['misiones_diarias']['items'][] = make_mision('orig_b1');
$pB['misiones_diarias']['items'][] = make_mision('orig_b2');
$pB['misiones_diarias']['items'][] = make_mision('orig_b3');
$antesB = InventarioEngine::totalUnidades($pB);
MisionDiariaEngine::cumplir($pB, 'orig_b1', [], null);
MisionDiariaEngine::cumplir($pB, 'orig_b2', [], null);
MisionDiariaEngine::cumplir($pB, 'orig_b3', [], null);
$despuesB = InventarioEngine::totalUnidades($pB);
ok($despuesB === $antesB + 1, 'B: 3/3 concede exactamente 1 regalo');
$msgB = mensajito_regalito($pB, RegalitoRecompensaService::ORIGEN_MISIONES_3X3);
ok($msgB !== null, 'B: mensajito 3/3 creado');
if ($msgB !== null) {
    $oB = $msgB['origen'] ?? [];
    ok(strpos((string) ($msgB['texto'] ?? ''), 'tres misiones del día') !== false, 'B: copy menciona tres misiones');
    ok(!empty($oB['objeto_id']), 'B: metadata objeto_id');
    ok(!empty($oB['objeto_nombre']), 'B: metadata objeto_nombre');
    ok(!empty($oB['objeto_imagen']), 'B: metadata objeto_imagen');
    ok(strpos((string) $oB['objeto_imagen'], 'assets/play-v3/') === 0, 'B: imagen ruta canónica');
}

// --- C: nuevo recuerdo Historia → 1 regalo + mensajito + asociación ---
$pC = fixture();
$antesC = InventarioEngine::totalUnidades($pC);
$resC = HistoriaPuebloEngine::registrar($pC, 'hito_02', ['per_a', 'per_b']);
ok($resC['ya_existia'] === false, 'C: hito nuevo');
ok(InventarioEngine::totalUnidades($pC) === $antesC + 1, 'C: inventario +1');
$entradaC = $resC['entrada'] ?? [];
$recC = RegalitoRecompensaService::recompensaDeEntradaHistoria($pC, $entradaC);
ok($recC !== null && !empty($recC['objeto_id']), 'C: recuerdo guarda recompensa');
$msgC = mensajito_regalito($pC, RegalitoRecompensaService::ORIGEN_HISTORIA);
ok($msgC !== null, 'C: mensajito Historia creado');
if ($msgC !== null) {
    ok(strpos((string) ($msgC['texto'] ?? ''), 'Historia del Pueblo') !== false, 'C: copy menciona Historia');
    $oC = $msgC['origen'] ?? [];
    ok(!empty($oC['objeto_imagen']), 'C: imagen en metadata');
}

// --- D: reabrir recuerdo → recompensa visible, 0 regalo nuevo ---
$antesD = InventarioEngine::totalUnidades($pC);
$resD = HistoriaPuebloEngine::registrar($pC, 'hito_02', ['per_a', 'per_b']);
$recD = RegalitoRecompensaService::recompensaDeEntradaHistoria($pC, $resD['entrada'] ?? []);
ok($resD['ya_existia'] === true, 'D: re-registro idempotente');
ok(InventarioEngine::totalUnidades($pC) === $antesD, 'D: reabrir no concede otro regalo');
ok($recD !== null && !empty($recD['objeto_id']), 'D: recompensa sigue visible en entrada');

// --- E: reload simulado → 0 duplicados ---
$pE = json_decode(json_encode($pC), true);
$antesE = InventarioEngine::totalUnidades($pE);
HistoriaPuebloEngine::registrar($pE, 'hito_02', ['per_a', 'per_b']);
RegalitoRecompensaService::otorgar($pE, 'historia:' . ($resC['entrada']['clave'] ?? ''), null);
ok(InventarioEngine::totalUnidades($pE) === $antesE, 'E: reload + reintentos no duplican');

// --- F: animación pendiente solo la primera vez ---
$hitoId = (string) ($entradaC['hito_id'] ?? 'hito_02');
ok(HistoriaPuebloEngine::recompensaAnimacionPendiente($pC, $hitoId), 'F: animación pendiente al desbloquear');
ok(HistoriaPuebloEngine::marcarRecompensaAnimada($pC, $hitoId), 'F: marcar animación vista');
ok(!HistoriaPuebloEngine::recompensaAnimacionPendiente($pC, $hitoId), 'F: ya no pendiente tras ack');
ok(!HistoriaPuebloEngine::marcarRecompensaAnimada($pC, $hitoId), 'F: segundo ack no muta');

echo "\n" . ($failures === 0 ? 'ALL OK' : "FAILURES: $failures") . "\n";
exit($failures > 0 ? 1 : 0);
