<?php
/**
 * P11.2 — Consecuencia emocional de formación de pareja
 *
 * Ejecutar: php tests/p11_2_formacion_pareja_test.php
 * Requiere: PHP 8.1+, sin dependencias externas.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EmotionalStateService;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\EventBus;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\GeneradorResidente;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\VisualPackStore;

$root = dirname(__DIR__);
$failures = 0;
$tests = 0;

function ok(bool $c, string $m): void
{
    global $failures, $tests;
    $tests++;
    echo ($c ? 'OK' : 'FAIL') . " [$tests]: $m\n";
    if (!$c) {
        $failures++;
    }
}

DomainBootstrap::boot();
$catalog = new Catalog($root);
$store = $catalog->store();
$cal = CalibracionConfig::load($root);

// ====================================================================
// Helper: crear partida con 2 residentes
// ====================================================================
function crearPartidaCon2Residentes(Catalog $catalog, array $cal): array
{
    global $root;
    $svc = new PartidaService($root);
    $p = $svc->nuevaPartida('playtest_01', 'p11-2-test');
    $rids = array_keys($p['residentes'] ?? []);
    if (count($rids) < 2) {
        throw new \RuntimeException('Need at least 2 residents');
    }
    return [$p, $rids[0], $rids[1]];
}

// ====================================================================
// A — FORMACIÓN NORMAL
// ====================================================================
echo "\n=== A: Formación normal ===\n";

[$pA, $aA, $bA] = crearPartidaCon2Residentes($catalog, $cal);

$antesA = $pA['residentes'][$aA]['runtime']['estado_emocional']['id'] ?? 'neutro';
$antesB = $pA['residentes'][$bA]['runtime']['estado_emocional']['id'] ?? 'neutro';
ok($antesA === EstadoEmocional::NEUTRO, 'A1: estado A antes = neutro');
ok($antesB === EstadoEmocional::NEUTRO, 'A2: estado B antes = neutro');

$r = ParejaEngine::formar($pA, $aA, $bA, true, true, RelacionBitacora::DECLARACION, $cal);
ok($r['ok'] === true, 'A3: formar() retorna ok');

$emoA = $pA['residentes'][$aA]['runtime']['estado_emocional'];
$emoB = $pA['residentes'][$bA]['runtime']['estado_emocional'];

ok($emoA['id'] === EstadoEmocional::ALEGRE, 'A4: A tiene estado alegre');
ok($emoB['id'] === EstadoEmocional::ALEGRE, 'A5: B tiene estado alegre');
ok(($emoA['origen'] ?? '') === 'formacion_pareja', 'A6: origen de A = formacion_pareja');
ok(($emoB['origen'] ?? '') === 'formacion_pareja', 'A7: origen de B = formacion_pareja');

$ctxA = is_array($emoA['contexto'] ?? null) ? $emoA['contexto'] : [];
$ctxB = is_array($emoB['contexto'] ?? null) ? $emoB['contexto'] : [];
ok(($ctxA['pareja_id'] ?? '') === $bA, 'A8: contexto de A contiene pareja_id = B');
ok(($ctxB['pareja_id'] ?? '') === $aA, 'A9: contexto de B contiene pareja_id = A');

$durA = (int) ($emoA['duracion_horas'] ?? 0);
$durB = (int) ($emoB['duracion_horas'] ?? 0);
ok($durA === 6, "A10: duración de A = 6h (real: $durA)");
ok($durB === 6, "A11: duración de B = 6h (real: $durB)");

$hastaA = $emoA['hasta'] ?? null;
$hastaB = $emoB['hasta'] ?? null;
ok(is_array($hastaA) && ($hastaA['dia'] ?? 0) > 0, 'A12: A tiene hasta válido');
ok(is_array($hastaB) && ($hastaB['dia'] ?? 0) > 0, 'A13: B tiene hasta válido');

// ====================================================================
// B — CAUSA VISIBLE (EmocionalNarrativa)
// ====================================================================
echo "\n=== B: Causa visible ===\n";

$expA = EmocionalNarrativa::explicacionCompleta($pA, $aA, $emoA);
$expB = EmocionalNarrativa::explicacionCompleta($pA, $bA, $emoB);

ok($expA !== null, 'B1: explicacionCompleta retorna resultado para A');
ok($expB !== null, 'B2: explicacionCompleta retorna resultado para B');

if ($expA !== null) {
    $nombreB = \AquiHayTema\Engine\IdentidadPublica::nombre($pA, $bA);
    ok(
        $nombreB !== '' && str_contains($expA['explicacion'], $nombreB),
        "B3: explicación de A menciona a B ($nombreB)"
    );
    ok(str_contains($expA['explicacion'], 'content') || str_contains($expA['explicacion'], 'relación'),
        'B4: explicación de A es comprensible');
}

if ($expB !== null) {
    $nombreA = \AquiHayTema\Engine\IdentidadPublica::nombre($pA, $aA);
    ok(
        $nombreA !== '' && str_contains($expB['explicacion'], $nombreA),
        "B5: explicación de B menciona a A ($nombreA)"
    );
}

$pistaA = EmocionalNarrativa::pistaFicha($emoA);
$pistaB = EmocionalNarrativa::pistaFicha($emoB);
ok($pistaA !== null && $pistaA !== '', 'B6: pistaFicha retorna string no vacío para A');
ok($pistaB !== null && $pistaB !== '', 'B7: pistaFicha retorna string no vacío para B');

// ====================================================================
// C — EXPIRACIÓN
// ====================================================================
echo "\n=== C: Expiración ===\n";

[$pC, $aC, $bC] = crearPartidaCon2Residentes($catalog, $cal);

ParejaEngine::formar($pC, $aC, $bC, true, true, RelacionBitacora::DECLARACION, $cal);

$antesC = $pC['residentes'][$aC]['runtime']['estado_emocional']['id'] ?? 'neutro';
ok($antesC === EstadoEmocional::ALEGRE, 'C1: A está alegre justo después de formar pareja');

$hastaC = $pC['residentes'][$aC]['runtime']['estado_emocional']['hasta'] ?? null;
ok(is_array($hastaC), 'C2: A tiene hasta definido');
$hastaDia = (int) ($hastaC['dia'] ?? 0);
$hastaHora = (int) ($hastaC['hora'] ?? 0);
ok($hastaDia > 0, "C3: hasta.dia > 0 (real: $hastaDia)");

$pC['reloj']['dia_pueblo'] = $hastaDia;
$pC['reloj']['hora_actual'] = $hastaHora;
$emoSvc = new EmotionalStateService(new VisualPackStore($root), $store, null);
$expirados = $emoSvc->expirarVencidos($pC);
ok($expirados >= 1, "C4: expirarVencidos expira al menos 1 (real: $expirados)");

$despuesC = $pC['residentes'][$aC]['runtime']['estado_emocional']['id'] ?? 'neutro';
ok($despuesC === EstadoEmocional::NEUTRO, "C5: tras expirar, A vuelve a neutro (real: $despuesC)");

// ====================================================================
// D — RECONCILIACIÓN
// ====================================================================
echo "\n=== D: Reconciliación NO confunde con inicio ===\n";

[$pD, $aD, $bD] = crearPartidaCon2Residentes($catalog, $cal);

$r1 = ParejaEngine::formar($pD, $aD, $bD, true, true, RelacionBitacora::DECLARACION, $cal);
ok($r1['ok'] === true, 'D1: formar() inicial ok');
$emoInicio = $pD['residentes'][$aD]['runtime']['estado_emocional']['origen'] ?? '';
ok($emoInicio === 'formacion_pareja', 'D2: formación inicial tiene origen formacion_pareja');

ParejaEngine::romper($pD, $aD, $bD, 'test');

$estadoAntesReconciliar = $pD['residentes'][$aD]['runtime']['estado_emocional']['id'] ?? 'neutro';

$reloj = $pD['reloj'] ?? [];
$durExp = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.alegre', 6);
$hastaExp = EstadoEmocional::hastaDesdeDuracion($reloj, $durExp);
$emoSvc = new EmotionalStateService(new VisualPackStore($root), $store, null);
$emoSvc->aplicar($pD, $aD, EstadoEmocional::NEUTRO, 'expiracion', null, null);
$emoSvc->aplicar($pD, $bD, EstadoEmocional::NEUTRO, 'expiracion', null, null);

$r2 = ParejaEngine::reconciliar($pD, $aD, $bD, true, true, $cal);
ok($r2['ok'] === true, 'D3: reconciliar() ok');

$emoReconciliar = $pD['residentes'][$aD]['runtime']['estado_emocional'];
$origenReconciliar = $emoReconciliar['origen'] ?? '';
ok($origenReconciliar !== 'formacion_pareja', "D4: reconciliación NO genera formacion_pareja (origen: $origenReconciliar)");
ok($r2['vuelta'] ?? false, 'D5: reconciliar() retorna vuelta=true');

// ====================================================================
// E — NO DOBLE EMOCIÓN
// ====================================================================
echo "\n=== E: No doble emoción ===\n";

[$pE, $aE, $bE] = crearPartidaCon2Residentes($catalog, $cal);

ParejaEngine::formar($pE, $aE, $bE, true, true, RelacionBitacora::DECLARACION, $cal);

$emoA_final = $pE['residentes'][$aE]['runtime']['estado_emocional'];
$origenA = $emoA_final['origen'] ?? '';
ok($origenA === 'formacion_pareja', "E1: origen de A = formacion_pareja (real: $origenA)");

$rel = RelacionEngine::obtenerEntre($pE, $aE, $bE);
$romance = $rel['romance'] ?? [];
$estadoPareja = $romance['estado_pareja'] ?? 'ninguna';
ok($estadoPareja === ParejaEngine::PAREJA, "E2: estado de pareja = pareja (real: $estadoPareja)");

DomainBootstrap::boot();
$eventosEmocion = [];
EventBus::on(\AquiHayTema\Engine\DomainEvents::ESTADO_EMOCIONAL_CAMBIADO, function (array &$p, array $env) use (&$eventosEmocion) {
    $eventosEmocion[] = $env['payload'] ?? [];
});
EventBus::on(\AquiHayTema\Engine\DomainEvents::RELACION_MODIFICADA, function (array &$p, array $env) use (&$eventosEmocion) {
    // solo contar, no actuar
});

[$pE2, $aE2, $bE2] = crearPartidaCon2Residentes($catalog, $cal);
$eventosEmocion = [];
ParejaEngine::formar($pE2, $aE2, $bE2, true, true, RelacionBitacora::DECLARACION, $cal);

$eventsA = array_filter($eventosEmocion, fn($e) => ($e['residente_id'] ?? '') === $aE2);
$eventsB = array_filter($eventosEmocion, fn($e) => ($e['residente_id'] ?? '') === $bE2);
ok(count($eventsA) === 1, "E3: A recibe exactamente 1 cambio emocional (real: " . count($eventsA) . ")");
ok(count($eventsB) === 1, "E4: B recibe exactamente 1 cambio emocional (real: " . count($eventsB) . ")");

// ====================================================================
// F — FEATURE / CONFIG
// ====================================================================
echo "\n=== F: Feature / Config ===\n";

[$pF, $aF, $bF] = crearPartidaCon2Residentes($catalog, $cal);

$flagVal = FeatureConfig::isEnabled($pF, 'emotional_state_from_events_enabled');
ok(is_bool($flagVal), 'F1: emotional_state_from_events_enabled es booleano');

$estadoAntesF = $pF['residentes'][$aF]['runtime']['estado_emocional']['id'] ?? 'neutro';
ok($estadoAntesF === EstadoEmocional::NEUTRO, 'F2: estado antes de formar = neutro');

ParejaEngine::formar($pF, $aF, $bF, true, true, RelacionBitacora::DECLARACION, $cal);
$emoF = $pF['residentes'][$aF]['runtime']['estado_emocional']['id'] ?? 'neutro';
ok($emoF === EstadoEmocional::ALEGRE, "F3: emoción se aplica correctamente (real: $emoF)");

$pF2 = $pF;
$pF2['features']['emotional_state_from_events_enabled'] = false;
[$pF3, $aF3, $bF3] = crearPartidaCon2Residentes($catalog, $cal);
$pF3['features']['emotional_state_from_events_enabled'] = false;
ParejaEngine::formar($pF3, $aF3, $bF3, true, true, RelacionBitacora::DECLARACION, $cal);
$emoF3 = $pF3['residentes'][$aF3]['runtime']['estado_emocional']['id'] ?? 'neutro';
ok($emoF3 === EstadoEmocional::ALEGRE, "F4: emoción se aplica independientemente del flag (real: $emoF3)");

// ====================================================================
// RESUMEN
// ====================================================================
echo "\n═══════════════════════════════════════════\n";
echo "  P11.2 — RESUMEN\n";
echo "═══════════════════════════════════════════\n\n";

echo "Tests ejecutados: $tests\n";
echo "Fallos: $failures\n";

if ($failures === 0) {
    echo "\n✓ TODOS LOS TESTS PASAN\n";
} else {
    echo "\n✗ $failures TEST(S) FALLARON\n";
}

echo "\n## FIN P11.2 TESTS\n";

exit($failures > 0 ? 1 : 0);
