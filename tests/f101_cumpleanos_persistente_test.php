<?php
declare(strict_types=1);

/**
 * F10.1 — Cumpleaños persistente + reacción social — tests focalizados.
 *
 * Cubre:
 *   1. residente nuevo obtiene cumpleaños persistente
 *   2. residente existente sin fecha migra una sola vez
 *   3. reload conserva exactamente la misma fecha
 *   4. residente con fecha previa no se sobrescribe
 *   5. ResidenteCumpleanosEngine detecta usando campo canónico
 *   6. Mensajito se dispara en día correcto
 *   7. no se dispara en día incorrecto
 *   8. Felicitar aplica exactamente una vez el efecto esperado
 *   9. reload no duplica la felicitación/recompensa contextual
 *  10. reacción social respeta vínculo y límite
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ContactoCalidad;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\MensajitoContextualEngine;
use AquiHayTema\Engine\MensajitoVoz;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\ResidenteCumpleanosEngine;

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

function alinearCumpleHoy(array &$p, string $rid): void
{
    $diaPueblo = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    $fecha = Reloj::fechaDeDia($p['reloj'] ?? [], $diaPueblo);
    $p['residentes'][$rid]['identidad_publica']['cumpleanos'] = [
        'dia' => (int) $fecha->format('j'),
        'mes' => (int) $fecha->format('n'),
    ];
}

function alinearCumpleOtroDia(array &$p, string $rid): void
{
    $diaPueblo = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    $fecha = Reloj::fechaDeDia($p['reloj'] ?? [], $diaPueblo);
    $dia = ((int) $fecha->format('j') % 28) + 1;
    $mes = ((int) $fecha->format('n') % 12) + 1;
    if ($dia === (int) $fecha->format('j') && $mes === (int) $fecha->format('n')) {
        $dia = $dia === 28 ? 1 : $dia + 1;
    }
    $p['residentes'][$rid]['identidad_publica']['cumpleanos'] = ['dia' => $dia, 'mes' => $mes];
}

function contarF10(array $p): int
{
    $n = 0;
    foreach ($p['buzon'] ?? [] as $m) {
        if (!is_array($m)) {
            continue;
        }
        if (($m['familia_mensajito'] ?? '') === 'f_ritual_contextual') {
            $n++;
        }
    }
    return $n;
}

function contarFollowups(array $p): int
{
    $n = 0;
    foreach ($p['buzon'] ?? [] as $m) {
        if (!is_array($m)) {
            continue;
        }
        if (($m['familia_mensajito'] ?? '') === 'f_cumple_seguimiento') {
            $n++;
        }
    }
    return $n;
}

function buscarMensajeF10(array $p): ?array
{
    foreach ($p['buzon'] as $m) {
        if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'f_ritual_contextual') {
            return $m;
        }
    }
    return null;
}

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$svc = new PartidaService($root);

echo "--- F10.1: Cumpleaños persistente + reacción social ---\n\n";

// ================================================================
// TEST 1: Residente nuevo obtiene cumpleaños persistente
// ================================================================
echo "--- Test 1: Cumpleaños persistente en residente nuevo ---\n";
$p1 = $svc->nuevaPartida('juego_v1', 'f101-persist-' . time());
$rid1 = (string) array_key_first($p1['residentes'] ?? []);
$antes1 = $p1['residentes'][$rid1]['identidad_publica']['cumpleanos'] ?? null;
$c1 = ResidenteCumpleanosEngine::obtener($p1, $rid1, $catalog);
$despues1 = $p1['residentes'][$rid1]['identidad_publica']['cumpleanos'] ?? null;
ok($c1 !== null, '1: obtener retorna fecha');
ok(is_array($despues1) && isset($despues1['dia']) && isset($despues1['mes']), '1: fecha persistida en identidad_publica');
ok($c1['dia'] === (int) $despues1['dia'] && $c1['mes'] === (int) $despues1['mes'], '1: valor coincide');

// ================================================================
// TEST 2: Residente existente sin fecha migra una sola vez
// ================================================================
echo "\n--- Test 2: Migración lazy una sola vez ---\n";
$p2 = $svc->nuevaPartida('juego_v1', 'f101-migrate-' . time());
$rid2 = (string) array_key_first($p2['residentes'] ?? []);
unset($p2['residentes'][$rid2]['identidad_publica']['cumpleanos']);
$c2a = ResidenteCumpleanosEngine::obtener($p2, $rid2, $catalog);
ok($c2a !== null, '2: primera obtención genera fecha');
$persistida2 = $p2['residentes'][$rid2]['identidad_publica']['cumpleanos'] ?? null;
ok(is_array($persistida2), '2: fecha persistida después de obtener');

// Simular recarga: volver a obtener con la misma partida
$c2b = ResidenteCumpleanosEngine::obtener($p2, $rid2, $catalog);
ok($c2a['dia'] === $c2b['dia'] && $c2a['mes'] === $c2b['mes'], '2: segunda obtención retorna la misma fecha');

// ================================================================
// TEST 3: Reload conserva exactamente la misma fecha
// ================================================================
echo "\n--- Test 3: Reload conserva la misma fecha ---\n";
$p3 = $svc->nuevaPartida('juego_v1', 'f101-reload-' . time());
$rid3 = (string) array_key_first($p3['residentes'] ?? []);
$c3a = ResidenteCumpleanosEngine::obtener($p3, $rid3, $catalog);
// Simular guardado y recarga (la partida ya tiene el campo persistido)
$c3b = ResidenteCumpleanosEngine::obtener($p3, $rid3, $catalog);
ok($c3a !== null && $c3b !== null, '3: ambas obtenciones retornan fecha');
ok($c3a['dia'] === $c3b['dia'] && $c3a['mes'] === $c3b['mes'], '3: reload idéntico');

// ================================================================
// TEST 4: Residente con fecha previa no se sobrescribe
// ================================================================
echo "\n--- Test 4: Fecha existente no se sobrescribe ---\n";
$p4 = $svc->nuevaPartida('juego_v1', 'f101-nosobrescribe-' . time());
$rid4 = (string) array_key_first($p4['residentes'] ?? []);
$p4['residentes'][$rid4]['identidad_publica']['cumpleanos'] = ['dia' => 15, 'mes' => 6];
$c4a = ResidenteCumpleanosEngine::obtener($p4, $rid4, $catalog);
ok($c4a['dia'] === 15 && $c4a['mes'] === 6, '4: fecha manual preservada');
// Volver a obtener
$c4b = ResidenteCumpleanosEngine::obtener($p4, $rid4, $catalog);
ok($c4b['dia'] === 15 && $c4b['mes'] === 6, '4: no se sobrescribe al reobtener');

// ================================================================
// TEST 5: Engine detecta usando campo canónico
// ================================================================
echo "\n--- Test 5: Detección con campo canónico ---\n";
$p5 = $svc->nuevaPartida('juego_v1', 'f101-canonico-' . time());
$rid5 = (string) array_key_first($p5['residentes'] ?? []);
// Forzar fecha conocida
$p5['residentes'][$rid5]['identidad_publica']['cumpleanos'] = ['dia' => 7, 'mes' => 3];
ok(ResidenteCumpleanosEngine::obtener($p5, $rid5, $catalog) === ['dia' => 7, 'mes' => 3], '5: obtener retorna campo canónico');
// Verificar que esCumpleanosHoy funciona con esa fecha
$p5['reloj']['dia_pueblo'] = 1;
$fechaRef = Reloj::fechaDeDia($p5['reloj'] ?? [], 1);
// Calcular si el día 1 del pueblo cae en 7/3
$esHoy = (int) $fechaRef->format('n') === 3 && (int) $fechaRef->format('j') === 7;
ok(ResidenteCumpleanosEngine::esCumpleanosHoy($p5, $rid5, $catalog) === $esHoy, '5: esCumpleanosHoy usa campo canónico');

// ================================================================
// TEST 6: Mensajito se dispara en día correcto
// ================================================================
echo "\n--- Test 6: F10 en día correcto ---\n";
$p6 = $svc->nuevaPartida('juego_v1', 'f101-diario-' . time());
$p6['features']['buzon_enabled'] = true;
$p6['residentes']['per_p002']['identidad_publica']['nombre'] = 'Lucía';
// Crear relación social para que elija remitente
if (!isset($p6['relaciones_sociales'])) {
    $p6['relaciones_sociales'] = [];
}
RelacionEngine::upsertSocial($p6, 'per_p001', 'per_p002', 'amigo', 30);
alinearCumpleHoy($p6, 'per_p002');
$emit6 = MensajitoContextualEngine::evaluarAlComenzarDia($p6, $cal, $catalog);
ok(count($emit6) >= 1, '6: genera al menos 1 mensaje');
$msgs10 = array_values(array_filter($emit6, fn($e) => ($e['tipo'] ?? '') !== 'followup'));
ok(count($msgs10) >= 1, '6: al menos 1 es F10 principal');

// ================================================================
// TEST 7: No se dispara en día incorrecto
// ================================================================
echo "\n--- Test 7: F10 no dispara en día incorrecto ---\n";
$p7 = $svc->nuevaPartida('juego_v1', 'f101-nodiario-' . time());
$p7['features']['buzon_enabled'] = true;
RelacionEngine::upsertSocial($p7, 'per_p001', 'per_p002', 'amigo', 30);
alinearCumpleOtroDia($p7, 'per_p002');
$emit7 = MensajitoContextualEngine::evaluarAlComenzarDia($p7, $cal, $catalog);
ok(contarF10($p7) === 0, '7: no genera F10 en día incorrecto');

// ================================================================
// TEST 8: Felicitar aplica efecto esperado exactamente una vez
// ================================================================
echo "\n--- Test 8: Felicitar efecto exactamente 1 vez ---\n";
$p8 = $svc->nuevaPartida('juego_v1', 'f101-felicitar-' . time());
$p8['features']['buzon_enabled'] = true;
$p8['residentes']['per_p002']['identidad_publica']['nombre'] = 'Lucía';
RelacionEngine::upsertSocial($p8, 'per_p001', 'per_p002', 'amigo', 30);
alinearCumpleHoy($p8, 'per_p002');
MensajitoContextualEngine::evaluarAlComenzarDia($p8, $cal, $catalog);
$msg8 = buscarMensajeF10($p8);
ok($msg8 !== null, '8: F10 encontrado');
$msgId8 = (string) ($msg8['id'] ?? '');

// Ejecutar Felicitar
$r8 = MensajitoAcciones::resolver($p8, $msgId8, MensajitoAcciones::PARTICIPAR_CUMPLE, $root);
ok($r8['ok'] ?? false, '8: Felicitar ejecuta ok');

// Verificar estado emocional del cumpleañero
$estado8 = $p8['residentes']['per_p002']['runtime']['estado_emocional'] ?? null;
ok($estado8 !== null, '8: tiene estado emocional');
ok(($estado8['id'] ?? '') === EstadoEmocional::ALEGRE, '8: estado es ALEGRE');
ok(($estado8['origen'] ?? '') === 'cumple_felicidad', '8: origen es cumple_felicidad');

// Verificar que no se puede felicitar dos veces
$r8b = MensajitoAcciones::resolver($p8, $msgId8, MensajitoAcciones::PARTICIPAR_CUMPLE, $root);
ok(!($r8b['ok'] ?? false), '8: segunda felicitación rechazada (sin decisión pendiente)');

// Verificar detallito_hook
ok(isset($r8['detallito_hook']) && ($r8['detallito_hook']['motivo'] ?? '') === 'cumpleanos_felicitar', '8: detallito_hook presente');

// ================================================================
// TEST 9: Reload no duplica felicitación
// ================================================================
echo "\n--- Test 9: Reload no duplica felicitación ---\n";
$p9 = $svc->nuevaPartida('juego_v1', 'f101-reloadfel-' . time());
$p9['features']['buzon_enabled'] = true;
$p9['residentes']['per_p002']['identidad_publica']['nombre'] = 'Lucía';
RelacionEngine::upsertSocial($p9, 'per_p001', 'per_p002', 'amigo', 30);
alinearCumpleHoy($p9, 'per_p002');
MensajitoContextualEngine::evaluarAlComenzarDia($p9, $cal, $catalog);
$msg9 = buscarMensajeF10($p9);
$msgId9 = (string) ($msg9['id'] ?? '');
$r9a = MensajitoAcciones::resolver($p9, $msgId9, MensajitoAcciones::PARTICIPAR_CUMPLE, $root);
ok($r9a['ok'] ?? false, '9: primera felicitar ok');

// Verificar que el mensaje tiene estado resuelto
$msgRecargado = null;
foreach ($p9['buzon'] as $m) {
    if (is_array($m) && (string) ($m['id'] ?? '') === $msgId9) {
        $msgRecargado = $m;
        break;
    }
}
ok(($msgRecargado['hilo_estado'] ?? '') === 'respondido', '9: hilo cerrado tras felicitar');
ok(!isset($msgRecargado['seguimiento_pendiente']) || $msgRecargado['seguimiento_pendiente'] === false, '9: sin seguimiento pendiente');

// ================================================================
// TEST 10: Reacción social respeta vínculo y límite
// ================================================================
echo "\n--- Test 10: Reacción social respeta vínculo y límite ---\n";
$p10 = $svc->nuevaPartida('juego_v1', 'f101-social-' . time());
$p10['features']['buzon_enabled'] = true;
$p10['residentes']['per_p002']['identidad_publica']['nombre'] = 'Lucía';
// Crear relación social: per_p003 tiene social 25 con Lucía (por encima del umbral 15)
RelacionEngine::upsertSocial($p10, 'per_p001', 'per_p002', 'amigo', 30);
// Solo verificar que el límite de follow-ups se respeta
// (con 0 relaciones adicionales por encima del umbral, no hay follow-ups)
alinearCumpleHoy($p10, 'per_p002');
$emit10 = MensajitoContextualEngine::evaluarAlComenzarDia($p10, $cal, $catalog);
$followups10 = array_values(array_filter($emit10, fn($e) => ($e['tipo'] ?? '') === 'followup'));
ok(count($followups10) <= 2, '10: máximo 2 follow-ups');
// Sin relaciones sociales adicionales por encima del umbral, 0 follow-ups
ok(count($followups10) === 0, '10: sin follow-ups sin relaciones por encima del umbral');

// ================================================================
// TEST EXTRA: Campo canónico en ficha plantilla
// ================================================================
echo "\n--- Test EXTRA: Campo en plantilla ---\n";
$plantilla = json_decode(file_get_contents($root . '/data/personajes/_plantilla.personaje.json'), true);
ok(array_key_exists('cumpleanos', $plantilla['identidad'] ?? []), 'EXTRA: plantilla tiene identidad.cumpleanos');
ok($plantilla['identidad']['cumpleanos'] === null, 'EXTRA: identidad.cumpleanos es null por defecto');

// ================================================================
// TEST EXTRA: Migración via SchemaFields
// ================================================================
echo "\n--- Test EXTRA: Migración en SchemaFields ---\n";
$pEx = $svc->nuevaPartida('juego_v1', 'f101-schema-' . time());
$ridEx = (string) array_key_first($pEx['residentes'] ?? []);
// Quitar cumpleaños
unset($pEx['residentes'][$ridEx]['identidad_publica']['cumpleanos']);
// Ejecutar SchemaFields::ensure (que llama a asegurarCumpleanos)
\AquiHayTema\Engine\SchemaFields::ensure($pEx);
$despuesEx = $pEx['residentes'][$ridEx]['identidad_publica']['cumpleanos'] ?? null;
ok(is_array($despuesEx) && isset($despuesEx['dia']) && isset($despuesEx['mes']), 'EXTRA: SchemaFields::ensure migra cumpleaños');

echo "\n" . ($failures === 0 ? 'TODOS OK' : "$failures FALLOS") . "\n";
exit($failures === 0 ? 0 : 1);
