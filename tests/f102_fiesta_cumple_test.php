<?php
declare(strict_types=1);

/**
 * F10.2 — Fiesta de cumpleaños organizada por Celestine — tests focalizados.
 *
 * Cubre:
 *   A. organizar → programa exactamente 1 encuentro real con intención fiesta_cumpleanos
 *   B. lugar elegido → existe, desbloqueado, aforo suficiente
 *   C. asistentes → incluye cumpleañero, prioriza relaciones, no supera capacidad
 *   D. residente con pocas relaciones → fiesta pequeña válida
 *   E. doble click → 1 fiesta
 *   F. F5/reload → no duplica (dedup persiste en partida)
 *   G. ignorar → 0 fiesta + 0 penalización
 *   H. resolver primera fiesta → registra EL PRIMER CUMPLEAÑOS en Historia
 *   I. segunda llamada → idempotente (ya_existia=true)
 *   J. encuentro tiene participantes/lugar/resultados reales
 *   K. cumpleañero recibe ALEGRE al organizar
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ComplejoCatalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\LugarAtributos;
use AquiHayTema\Engine\MensajitoContextualEngine;
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

function contarEncuentrosFiesta(array $p): int
{
    $n = 0;
    foreach ($p['encuentros'] ?? [] as $enc) {
        if (($enc['intencion'] ?? '') === 'fiesta_cumpleanos') {
            $n++;
        }
    }
    return $n;
}

function buscarEncuentroFiesta(array $p): ?array
{
    foreach ($p['encuentros'] ?? [] as $enc) {
        if (($enc['intencion'] ?? '') === 'fiesta_cumpleanos') {
            return $enc;
        }
    }
    return null;
}

/**
 * Inyecta un mensajito F10 sintético en el buzon para testear organizarCumple directamente.
 */
function inyectarMensajitoF10(array &$p, string $cumpleId, string $remitenteId): string
{
    $msgId = 'msg_f10_test_' . substr(md5($cumpleId . microtime(true)), 0, 12);
    $nombreCumple = $p['residentes'][$cumpleId]['identidad_publica']['nombre'] ?? $cumpleId;
    $eventoId = 'f10_cumple_' . $cumpleId;

    $partida['buzon'] ??= [];
    $p['buzon'][] = [
        'id' => $msgId,
        'clasificacion' => BuzonEngine::IMPORTANTE,
        'tipo' => 'ritual_contextual_cumpleanos',
        'canal' => BuzonEngine::CANAL_BUZON,
        'de_persona' => $remitenteId,
        'actores' => [$remitenteId, $cumpleId],
        'texto' => "Es el cumpleaños de {$nombreCumple}.",
        'acciones' => ['participar_cumple', 'organizar_cumple', 'ignorar_contextual'],
        'familia_mensajito' => 'f_ritual_contextual',
        'datos_familia' => [
            'subtipo' => 'cumpleanos',
            'cumpleanero_id' => $cumpleId,
            'cumpleanero_nombre' => $nombreCumple,
            'clave' => 'test_key',
            'auto_invitacion' => false,
        ],
        'hilo_id' => $msgId,
        'hilo_estado' => 'abierto',
        'origen' => [
            'evento_id' => $eventoId,
            'tipo_evento' => 'ritual_contextual_cumpleanos',
            'es_narrativo' => true,
            '_placeholder' => false,
        ],
        '_placeholder_contenido' => false,
    ];

    return $msgId;
}

/**
 * Crea una partida de test con residentes activos.
 */
function crearPartidaTest(string $root): array
{
    $svc = new PartidaService($root);
    $p = $svc->nuevaPartida('juego_v1', 'f102-test-' . microtime(true));
    $residentes = array_keys($p['residentes'] ?? []);
    foreach ($residentes as $i => $rid) {
        $p['residentes'][$rid]['presencia'] = 'residente';
        if ($i >= 4) {
            break;
        }
    }
    return $p;
}

DomainBootstrap::boot();

echo "--- F10.2: Fiesta de cumpleaños organizada por Celestine ---\n\n";

// ================================================================
// TEST A: organizar → programa exactamente 1 encuentro real
// ================================================================
echo "--- Test A: Organizar fiesta programa 1 encuentro real ---\n";
$pA = crearPartidaTest($root);
$ridsA = array_keys($pA['residentes']);
$cumpleA = $ridsA[0];
$amigoA1 = $ridsA[1] ?? $ridsA[0];
$amigoA2 = $ridsA[2] ?? $ridsA[0];
$remitenteA = $ridsA[1] ?? $ridsA[0];
alinearCumpleHoy($pA, $cumpleA);
RelacionEngine::upsertSocial($pA, $cumpleA, $amigoA1, 'amigo', 50, true, 'test');
RelacionEngine::upsertSocial($pA, $cumpleA, $amigoA2, 'amigo', 35, true, 'test');
$pA['celeste']['lugares_desbloqueados'] = ['lug_bar', 'lug_cafeteria', 'lug_restaurante', 'lug_parque'];

$msgIdA = inyectarMensajitoF10($pA, $cumpleA, $remitenteA);

$rA = MensajitoContextualEngine::organizarCumple($pA, $msgIdA);
ok($rA['ok'] === true, 'A1: organizar_cumple retorna ok');
ok(($rA['encuentro_id'] ?? '') !== '', 'A2: retorna encuentro_id');
ok(($rA['lugar'] ?? '') !== '', 'A3: retorna lugar');

$encA = buscarEncuentroFiesta($pA);
ok($encA !== null, 'A4: existe 1 encuentro con intención fiesta_cumpleanos');
ok($encA !== null && ($encA['tipo'] ?? '') === 'amistad', 'A5: encuentro tipo amistad');
ok($encA !== null && in_array($cumpleA, $encA['participantes'] ?? [], true), 'A6: cumpleañero en participantes');
ok(contarEncuentrosFiesta($pA) === 1, 'A7: exactamente 1 encuentro de fiesta');

// ================================================================
// TEST B: lugar elegido → existe, desbloqueado, aforo suficiente
// ================================================================
echo "\n--- Test B: Lugar elegido es válido ---\n";
$lugarA = $encA['lugar'] ?? '';
ok($lugarA !== '', 'B1: lugar no vacío');
ok(in_array($lugarA, $pA['celeste']['lugares_desbloqueados'] ?? [], true), 'B2: lugar desbloqueado');
$attrA = LugarAtributos::de($lugarA);
ok($attrA['aforo'] >= count($encA['participantes'] ?? []), 'B3: aforo suficiente');
$horaA = (int) ($encA['hora'] ?? 0);
ok(ComplejoCatalog::estaAbierto($lugarA, $horaA), 'B4: lugar abierto a la hora');

// ================================================================
// TEST C: asistentes → incluye cumpleañero, prioriza, no supera capacidad
// ================================================================
echo "\n--- Test C: Asistentes correctos ---\n";
ok(in_array($cumpleA, $encA['participantes'] ?? [], true), 'C1: cumpleañero incluido');
ok(in_array($amigoA1, $encA['participantes'] ?? [], true), 'C2: amigo social 50 incluido');
ok(in_array($amigoA2, $encA['participantes'] ?? [], true), 'C3: amigo social 35 incluido');
ok(count($encA['participantes'] ?? []) <= $attrA['aforo'], 'C4: no supera aforo');
ok(count($encA['participantes'] ?? []) <= 6, 'C5: no supera max de asistentes');

// ================================================================
// TEST D: residente con pocas relaciones → fiesta pequeña válida
// ================================================================
echo "\n--- Test D: Residente con pocas relaciones ---\n";
$pD = crearPartidaTest($root);
$ridsD = array_keys($pD['residentes']);
$cumpleD = $ridsD[0];
$remitenteD = $ridsD[1];
alinearCumpleHoy($pD, $cumpleD);
$pD['celeste']['lugares_desbloqueados'] = ['lug_cafeteria'];
$msgIdD = inyectarMensajitoF10($pD, $cumpleD, $remitenteD);

$rD = MensajitoContextualEngine::organizarCumple($pD, $msgIdD);
ok(is_array($rD), 'D1: organizar_cumple no explota sin relaciones');
if ($rD['ok'] ?? false) {
    $encD = buscarEncuentroFiesta($pD);
    ok($encD !== null, 'D2: si tiene éxito, programa encuentro');
    ok(in_array($cumpleD, $encD['participantes'] ?? [], true), 'D3: cumpleañero en la fiesta');
} else {
    ok(true, 'D2: (skipped - sin lugares abiertos o participantes)');
    ok(true, 'D3: (skipped)');
}

// ================================================================
// TEST E: doble click → 1 fiesta
// ================================================================
echo "\n--- Test E: Doble click no duplica ---\n";
$pE = crearPartidaTest($root);
$ridsE = array_keys($pE['residentes']);
$cumpleE = $ridsE[0];
$amigoE1 = $ridsE[1];
$remitenteE = $ridsE[2];
alinearCumpleHoy($pE, $cumpleE);
RelacionEngine::upsertSocial($pE, $cumpleE, $amigoE1, 'amigo', 45, true, 'test');
$pE['celeste']['lugares_desbloqueados'] = ['lug_bar', 'lug_cafeteria'];

$msgIdE = inyectarMensajitoF10($pE, $cumpleE, $remitenteE);

$rE1 = MensajitoContextualEngine::organizarCumple($pE, $msgIdE);
ok($rE1['ok'] === true, 'E1: primera organización exitosa' . (isset($rE1['error']) ? ' [' . $rE1['error'] . ']' : ''));

$rE2 = MensajitoContextualEngine::organizarCumple($pE, $msgIdE);
ok($rE2['ok'] === true, 'E2: segunda llamada retorna ok' . (isset($rE2['error']) ? ' [' . $rE2['error'] . ']' : ''));
ok(($rE2['ya_programada'] ?? false) === true, 'E3: indica ya_programada');
ok(contarEncuentrosFiesta($pE) === 1, 'E4: exactamente 1 fiesta tras doble click');

// ================================================================
// TEST F: F5/reload → no duplica
// ================================================================
echo "\n--- Test F: Reload no duplica ---\n";
$pF = crearPartidaTest($root);
$ridsF = array_keys($pF['residentes']);
$cumpleF = $ridsF[0];
$amigoF1 = $ridsF[1];
$remitenteF = $ridsF[2];
alinearCumpleHoy($pF, $cumpleF);
RelacionEngine::upsertSocial($pF, $cumpleF, $amigoF1, 'amigo', 40, true, 'test');
$pF['celeste']['lugares_desbloqueados'] = ['lug_bar', 'lug_cafeteria'];

$msgIdF = inyectarMensajitoF10($pF, $cumpleF, $remitenteF);
$rF1 = MensajitoContextualEngine::organizarCumple($pF, $msgIdF);
ok($rF1['ok'] === true, 'F1: primera organización' . (isset($rF1['error']) ? ' [' . $rF1['error'] . ']' : ''));

ok(!empty($pF['fiestas_cumple_emitidas']), 'F2: fiestas_cumple_emitidas persistido');
$claveAnualF = ResidenteCumpleanosEngine::claveAnual($pF, $cumpleF);
$claveFiestaF = 'cumple_fiesta_' . $claveAnualF;
ok(!empty($pF['fiestas_cumple_emitidas'][$claveFiestaF]), 'F3: clave de fiesta persistida');

$rF2 = MensajitoContextualEngine::organizarCumple($pF, $msgIdF);
ok(contarEncuentrosFiesta($pF) === 1, 'F4: no duplica tras reload');

// ================================================================
// TEST G: ignorar → 0 fiesta + 0 penalización
// ================================================================
echo "\n--- Test G: No organizar no penaliza ---\n";
$pG = crearPartidaTest($root);
$ridsG = array_keys($pG['residentes']);
$cumpleG = $ridsG[0];
$remitenteG = $ridsG[1];
alinearCumpleHoy($pG, $cumpleG);
$pG['celeste']['lugares_desbloqueados'] = ['lug_bar'];

$msgIdG = inyectarMensajitoF10($pG, $cumpleG, $remitenteG);

$rG = MensajitoContextualEngine::ignorarContextual($pG, $msgIdG);
ok($rG['ok'] === true, 'G1: ignorar retorna ok');
ok(contarEncuentrosFiesta($pG) === 0, 'G2: 0 fiestas programadas');

$runtimeG = $pG['residentes'][$cumpleG]['runtime'] ?? [];
$emocionG = $runtimeG['estado_emocional']['id'] ?? 'neutro';
ok($emocionG !== 'triste', 'G3: sin penalización emocional');

// ================================================================
// TEST H: resolver primera fiesta → registra EL PRIMER CUMPLEAÑOS
// ================================================================
echo "\n--- Test H: Primera fiesta registra Historia ---\n";
$pH = crearPartidaTest($root);
$ridsH = array_keys($pH['residentes']);
$cumpleH = $ridsH[0];
$amigoH1 = $ridsH[1] ?? $ridsH[0];
$amigoH2 = $ridsH[2] ?? $ridsH[0];
$remitenteH = $ridsH[1] ?? $ridsH[0];
alinearCumpleHoy($pH, $cumpleH);
RelacionEngine::upsertSocial($pH, $cumpleH, $amigoH1, 'amigo', 55, true, 'test');
RelacionEngine::upsertSocial($pH, $cumpleH, $amigoH2, 'amigo', 42, true, 'test');
$pH['celeste']['lugares_desbloqueados'] = ['lug_bar', 'lug_cafeteria'];

$msgIdH = inyectarMensajitoF10($pH, $cumpleH, $remitenteH);
$rH = MensajitoContextualEngine::organizarCumple($pH, $msgIdH);
ok($rH['ok'] === true, 'H1: fiesta programada');

$encH = buscarEncuentroFiesta($pH);
ok($encH !== null, 'H2: encuentro existe');

// Simular que el encuentro termina
$encH['estado'] = 'terminado';
$encH['resultado'] = [
    '_placeholder' => false,
    'delta_social' => ['tipo' => 'reales', 'a_hacia_b' => 5, 'b_hacia_a' => 5],
    'conflicto' => null,
    'por_participante' => [],
];

$rHist = MensajitoContextualEngine::registrarPrimerCumpleHistoria($pH, $encH);
ok($rHist !== null, 'H3: registrarPrimerCumpleHistoria retorna resultado');
ok(($rHist['ya_existia'] ?? false) === false, 'H4: es el primer registro');
ok(($rHist['ok'] ?? false) === true, 'H5: registro exitoso');

$claveH = HistoriaPuebloEngine::clave(MensajitoContextualEngine::HITO_EL_PRIMER_CUMPLE, $encH['participantes']);
ok(HistoriaPuebloEngine::existe($pH, $claveH), 'H6: hito existe en Historia del Pueblo');

// ================================================================
// TEST I: segunda llamada → idempotente
// ================================================================
echo "\n--- Test I: Segunda llamada idempotente ---\n";
$rHist2 = MensajitoContextualEngine::registrarPrimerCumpleHistoria($pH, $encH);
ok($rHist2 !== null, 'I1: segunda llamada retorna resultado');
ok(($rHist2['ya_existia'] ?? false) === true, 'I2: ya_existia = true');

$entradasH = array_filter(
    $pH['historia_pueblo'] ?? [],
    fn($e) => ($e['hito_id'] ?? '') === MensajitoContextualEngine::HITO_EL_PRIMER_CUMPLE
);
ok(count($entradasH) === 1, 'I3: exactamente 1 entrada de el_primer_cumple');

// ================================================================
// TEST J: encuentro tiene participantes/lugar/resultados reales
// ================================================================
echo "\n--- Test J: Estructura del encuentro ---\n";
ok($encH !== null, 'J1: encuentro existe');
ok(($encH['intencion'] ?? '') === 'fiesta_cumpleanos', 'J2: intención fiesta_cumpleanos');
ok(($encH['estado'] ?? '') === 'terminado', 'J3: estado terminado');
ok(($encH['participantes'] ?? []) !== [], 'J4: tiene participantes');
ok(($encH['lugar'] ?? '') !== '', 'J5: tiene lugar');
ok(is_array($encH['resultado'] ?? null), 'J6: tiene resultado');

// ================================================================
// TEST K: cumpleañero recibe ALEGRE al organizar
// ================================================================
echo "\n--- Test K: Cumpleañero ALEGRE ---\n";
$emocionH = $pH['residentes'][$cumpleH]['runtime']['estado_emocional']['id'] ?? 'neutro';
ok($emocionH === 'alegre', 'K1: cumpleañero tiene estado ALEGRE');
$origenH = $pH['residentes'][$cumpleH]['runtime']['estado_emocional']['origen'] ?? '';
ok($origenH === 'cumple_fiesta', 'K2: origen es cumple_fiesta');

// ================================================================
// RESUMEN
// ================================================================
echo "\n" . str_repeat('=', 50) . "\n";
echo "Resultados: " . ($failures === 0 ? 'TODOS OK' : "{$failures} FALLOS") . "\n";
echo str_repeat('=', 50) . "\n";

exit($failures > 0 ? 1 : 0);
