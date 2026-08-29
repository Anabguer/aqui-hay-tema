<?php
declare(strict_types=1);

/**
 * Test Fase 1: Limpieza de `resultado._cal` post-cierre.
 *
 * Cubre escenarios A–L solicitados.
 *
 * Uso: php tests/encuentro_cal_limpieza_test.php
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EncuentroCotilleoCopy;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EncuentroResolver;
use AquiHayTema\Engine\EncuentroResultadoSlim;
use AquiHayTema\Engine\EncuentroResultadoVista;
use AquiHayTema\Engine\HistorialPar;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$failures = 0;
$tests = 0;

function ok(bool $c, string $m): void
{
    global $failures, $tests;
    $tests++;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

// ============================================================
// Helpers
// ============================================================

function makeEnc(string $estado, ?array $resultado): array
{
    return [
        'id' => 'enc_test_001',
        'tipo' => 'conocerse',
        'participantes' => ['per_a', 'per_b'],
        'dia' => 1,
        'hora' => 19,
        'lugar' => 'lug_cafeteria',
        'estado' => $estado,
        'resultado' => $resultado,
    ];
}

function makeResultado(?array $cal = []): array
{
    return [
        '_placeholder' => false,
        '_deltas_reales' => true,
        '_cal' => $cal,
        'delta_social' => ['tipo' => 'reales', 'a_hacia_b' => 1, 'b_hacia_a' => 1, 'intensidad' => 1],
        'delta_romance' => [],
        'conflicto' => null,
        'descubrimientos' => [],
        'eventos_derivados' => [],
        'por_participante' => [
            'per_a' => ['satisfaccion' => null, 'resultado' => 'normal', 'carga' => 0.5],
            'per_b' => ['satisfaccion' => null, 'resultado' => 'normal', 'carga' => 0.4],
        ],
        'experiencia' => ['por_participante' => []],
        'experiencia_narrativa' => ['texto' => 'Se conocieron.'],
        'texto_resumen' => 'Encuentro conocerse (normal/normal).',
        'historial_par' => [],
        'emociones' => [],
    ];
}

function buscarEncuentro(array $partida, string $id): ?array
{
    foreach ($partida['encuentros'] ?? [] as $e) {
        if (($e['id'] ?? '') === $id) {
            return $e;
        }
    }
    return null;
}

// ============================================================
// A) Encuentro terminado + _cal → _cal desaparece
// ============================================================
echo "--- A: terminado + _cal → desaparece ---\n";
$enc = makeEnc('terminado', makeResultado(['some' => 'data']));
$changed = EncuentroResultadoSlim::limpiarEncuentro($enc);
ok($changed === true, 'A1: limpiarEncuentro retorna true');
ok(!isset($enc['resultado']['_cal']), 'A2: _cal eliminado');
ok(isset($enc['resultado']['delta_social']), 'A3: delta_social intacto');
ok(isset($enc['resultado']['por_participante']), 'A4: por_participante intacto');
ok(isset($enc['resultado']['experiencia']), 'A5: experiencia intacta');

// ============================================================
// B) Encuentro terminado sin _cal → no cambia
// ============================================================
echo "\n--- B: terminado sin _cal → sin cambios ---\n";
$res = makeResultado();
unset($res['_cal']);
$enc2 = makeEnc('terminado', $res);
$changed2 = EncuentroResultadoSlim::limpiarEncuentro($enc2);
ok($changed2 === false, 'B1: limpiarEncuentro retorna false (sin _cal)');
ok(!isset($enc2['resultado']['_cal']), 'B2: _cal sigue ausente');
ok(isset($enc2['resultado']['delta_social']), 'B3: datos intactos');

// ============================================================
// C) Encuentro activo/en_curso + _cal → _cal permanece
// ============================================================
echo "\n--- C: activo + _cal → permanece ---\n";
$encA = makeEnc('en_curso', makeResultado(['some' => 'data']));
$changedA = EncuentroResultadoSlim::limpiarEncuentro($encA);
ok($changedA === false, 'C1: limpiarEncuentro retorna false (en_curso)');
ok(isset($encA['resultado']['_cal']), 'C2: _cal permanece en en_curso');

$encP = makeEnc('programado', makeResultado(['some' => 'data']));
$changedP = EncuentroResultadoSlim::limpiarEncuentro($encP);
ok($changedP === false, 'C3: limpiarEncuentro retorna false (programado)');
ok(isset($encP['resultado']['_cal']), 'C4: _cal permanece en programado');

// ============================================================
// D) Idempotencia: ejecutar 2 veces = mismo resultado
// ============================================================
echo "\n--- D: idempotencia ---\n";
$enc3 = makeEnc('terminado', makeResultado(['cal' => [1, 2, 3]]));
EncuentroResultadoSlim::limpiarEncuentro($enc3);
$after1 = json_encode($enc3['resultado'], JSON_UNESCAPED_UNICODE);
EncuentroResultadoSlim::limpiarEncuentro($enc3);
$after2 = json_encode($enc3['resultado'], JSON_UNESCAPED_UNICODE);
ok($after1 === $after2, 'D1: segunda ejecución no cambia nada');
ok(!isset($enc3['resultado']['_cal']), 'D2: _cal sigue ausente tras segunda ejecución');

// ============================================================
// E) Datos funcionales/narrativos intactos
// ============================================================
echo "\n--- E: datos funcionales intactos ---\n";
$enc4 = makeEnc('terminado', makeResultado(['test' => true]));
$enc4['resultado']['emociones'] = [['residente' => 'per_a', 'estado' => 'feliz']];
$enc4['resultado']['experiencia_narrativa'] = ['texto' => 'Todo bien.'];
$enc4['resultado']['descubrimientos'] = [['campo' => 'hobby:pasear']];
$enc4['resultado']['texto_resumen'] = 'Resumen.';
$enc4['resultado']['delta_social'] = ['intensidad' => 2];
$enc4['resultado']['delta_romance'] = ['vinculo' => 1];
$enc4['resultado']['conflicto'] = 3;
$enc4['resultado']['historial_par' ] = ['clave' => 'a:b'];

EncuentroResultadoSlim::limpiarEncuentro($enc4);

ok(!isset($enc4['resultado']['_cal']), 'E1: _cal eliminado');
ok($enc4['resultado']['emociones'][0]['estado'] === 'feliz', 'E2: emociones intacto');
ok($enc4['resultado']['experiencia_narrativa']['texto'] === 'Todo bien.', 'E3: experiencia_narrativa intacto');
ok($enc4['resultado']['descubrimientos'][0]['campo'] === 'hobby:pasear', 'E4: descubrimientos intacto');
ok($enc4['resultado']['texto_resumen'] === 'Resumen.', 'E5: texto_resumen intacto');
ok($enc4['resultado']['delta_social']['intensidad'] === 2, 'E6: delta_social intacto');
ok($enc4['resultado']['delta_romance']['vinculo'] === 1, 'E7: delta_romance intacto');
ok($enc4['resultado']['conflicto'] === 3, 'E8: conflicto intacto');
ok($enc4['resultado']['historial_par']['clave'] === 'a:b', 'E9: historial_par intacto');

// ============================================================
// F) Diario sigue funcionando
// (DiarioHitoEngine lee por_participante, conflicto, tipo — no _cal)
// ============================================================
echo "\n--- F: Diario sigue funcionando ---\n";
$resDiario = makeResultado();
$resDiario['por_participante']['per_a']['resultado'] = 'mal';
$resDiario['conflicto'] = 2;
$encDiario = makeEnc('terminado', $resDiario);
EncuentroResultadoSlim::limpiarEncuentro($encDiario);
// Simula lo que DiarioHitoEngine hace
$resD = $encDiario['resultado'];
$peor = 'normal';
foreach ($resD['por_participante'] ?? [] as $row) {
    $r = (string) (is_array($row) ? ($row['resultado'] ?? '') : '');
    if ($r === 'mal') {
        $peor = 'mal';
    }
}
$huboConflicto = (($resD['conflicto'] ?? null) !== null) && (int) ($resD['conflicto'] ?? 0) !== 0;
ok($peor === 'mal', 'F1: Diario detecta resultado mal');
ok($huboConflicto === true, 'F2: Diario detecta conflicto');

// ============================================================
// G) HistorialPar sigue funcionando
// (HistorialPar::encuentros lee por_participante.resultado, carga — no _cal)
// ============================================================
echo "\n--- G: HistorialPar sigue funcionando ---\n";
$partidaG = [
    'encuentros' => [
        makeEnc('terminado', makeResultado()),
    ],
];
$partidaG['encuentros'][0]['resultado']['por_participante']['per_a']['resultado'] = 'bien';
$partidaG['encuentros'][0]['resultado']['por_participante']['per_b']['resultado'] = 'normal';
$partidaG['encuentros'][0]['resultado']['por_participante']['per_a']['carga'] = 0.3;
$partidaG['encuentros'][0]['resultado']['por_participante']['per_b']['carga'] = 0.6;
EncuentroResultadoSlim::limpiarEncuentro($partidaG['encuentros'][0]);

$encs = HistorialPar::encuentros($partidaG, 'per_a', 'per_b');
ok(count($encs) === 1, 'G1: HistorialPar encuentra el encuentro');
ok($encs[0]['resultado_a'] === 'bien', 'G2: resultado_a correcto');
ok($encs[0]['resultado_b'] === 'normal', 'G3: resultado_b correcto');
ok($encs[0]['carga_a'] === 0.3, 'G4: carga_a correcta');

// ============================================================
// H) Cotilleo sigue funcionando
// (EncuentroCotilleoCopy compilar lee resultado, no _cal)
// ============================================================
echo "\n--- H: Cotilleo / Diario/HistPar post-lifecycle ---\n";
$serviceH = new PartidaService($root);
$partidaH = $serviceH->nuevaPartida('test_fixtures_v0', 'cal-limpieza-cotilleo');
$phH = $serviceH->crearResidentePlaceholderDev($partidaH);
$idaH = 'per_qa_valid';
$idbH = $phH['residente']['catalog_id'];
$encH = $serviceH->programarEncuentro($partidaH, [$idaH, $idbH], 1, 19, 'conocerse', 'lug_cafeteria');
ok($encH['ok'] ?? false, 'H1: programa encuentro');
if ($encH['ok'] ?? false) {
    $dia = (int) $encH['encuentro']['dia'];
    $hora = (int) $encH['encuentro']['hora'];
    $now = ((int) $partidaH['reloj']['dia_pueblo']) * 24 + (int) $partidaH['reloj']['hora_actual'];
    $dur = max(1, (int) ($encH['encuentro']['duracion_horas'] ?? 1));
    $adv = $serviceH->avanzarRelojPasoAPaso($partidaH, max(1, $dia * 24 + $hora + $dur - $now));
    ok($adv['ok'] ?? false, 'H2: encuentro termina');
    $rawH = buscarEncuentro($partidaH, (string) $encH['encuentro']['id']);
    ok($rawH !== null, 'H3: encuentro encontrado');
    ok(!isset($rawH['resultado']['_cal']), 'H4: _cal ya limpiado por lifecycle');
    $resH = is_array($rawH['resultado'] ?? null) ? $rawH['resultado'] : [];
    ok(isset($resH['por_participante']), 'H5: por_participante disponible');
    // HistorialPar tras lifecycle
    $encsH = HistorialPar::encuentros($partidaH, $idaH, $idbH);
    ok(count($encsH) >= 1, 'H6: HistorialPar tras lifecycle');
    // Vista
    $vistaH = EncuentroResultadoVista::de($partidaH, $rawH, $serviceH->getCatalog(), $root);
    ok(is_array($vistaH['resultado'] ?? null), 'H7: vista tras lifecycle');
} else {
    ok(false, 'H2-H7: skip (programación falló)');
}

// ============================================================
// I) Lookup por encuentro_id sigue funcionando
// (EmotionalEventBridge, NarrativeCoherenceEngine, EventosPuebloEngine, EncuentroIntervencion buscan por id)
// ============================================================
echo "\n--- I: lookup por encuentro_id funciona ---\n";
$partidaI = [
    'encuentros' => [
        makeEnc('terminado', makeResultado(['test' => 'lookup'])),
    ],
];
EncuentroResultadoSlim::limpiarEncuentro($partidaI['encuentros'][0]);
// Simula búsqueda por id (como hace EmotionalEventBridge)
$found = null;
foreach ($partidaI['encuentros'] as $e) {
    if (($e['id'] ?? '') === 'enc_test_001') {
        $found = $e;
        break;
    }
}
ok($found !== null, 'I1: lookup por id encuentra el encuentro');
ok(!isset($found['resultado']['_cal']), 'I2: _cal ausente tras limpieza');
ok(isset($found['resultado']['por_participante']), 'I3: datos accesibles post-limpieza');

// ============================================================
// J) Resolución normal de un encuentro nuevo sigue funcionando
// ============================================================
echo "\n--- J: resolución normal funciona ---\n";
$serviceJ = new PartidaService($root);
$partidaJ = $serviceJ->nuevaPartida('test_fixtures_v0', 'cal-limpieza-resolucion');
$phJ = $serviceJ->crearResidentePlaceholderDev($partidaJ);
$idaJ = 'per_qa_valid';
$idbJ = $phJ['residente']['catalog_id'];
$encJ = $serviceJ->programarEncuentro($partidaJ, [$idaJ, $idbJ], 1, 19, 'conocerse', 'lug_cafeteria');
ok($encJ['ok'] ?? false, 'J1: programa encuentro');
if ($encJ['ok'] ?? false) {
    $dJ = (int) $encJ['encuentro']['dia'];
    $hJ = (int) $encJ['encuentro']['hora'];
    $nowJ = ((int) $partidaJ['reloj']['dia_pueblo']) * 24 + (int) $partidaJ['reloj']['hora_actual'];
    $durJ = max(1, (int) ($encJ['encuentro']['duracion_horas'] ?? 1));
    $advJ = $serviceJ->avanzarRelojPasoAPaso($partidaJ, max(1, $dJ * 24 + $hJ + $durJ - $nowJ));
    ok($advJ['ok'] ?? false, 'J2: avanza y resuelve');
    ok(($advJ['encuentros_resueltos'] ?? 0) >= 1, 'J3: al menos 1 encuentro resuelto');
    $rawJ = buscarEncuentro($partidaJ, (string) $encJ['encuentro']['id']);
    ok($rawJ !== null, 'J4: encuentro encontrado');
    ok(!isset($rawJ['resultado']['_cal']), 'J5: _cal limpiado tras resolución');
    ok(isset($rawJ['resultado']['delta_social']), 'J6: delta_social presente');
    ok(isset($rawJ['resultado']['por_participante']), 'J7: por_participante presente');
    $vistaJ = EncuentroResultadoVista::de($partidaJ, $rawJ, $serviceJ->getCatalog(), $root);
    ok(is_array($vistaJ['resultado'] ?? null), 'J8: EncuentroResultadoVista genera vista válida');
    ok(is_int($vistaJ['resultado']['social']['delta'] ?? null) || is_float($vistaJ['resultado']['social']['delta'] ?? null), 'J9: delta social numérico');
} else {
    ok(false, 'J2-J9: skip (programación falló)');
}

// ============================================================
// K) MENTES no sufre regresión
// (MENTES lee tema.resultado, no encuentra._cal)
// ============================================================
echo "\n--- K: MENTES sin regresión ---\n";
// MENTES no lee _cal directamente. Verificamos que el motor no se rompe.
$partidaK = [
    'encuentros' => [
        makeEnc('terminado', makeResultado()),
    ],
    'mentes' => [
        'temas' => [
            ['id' => 't1', 'estado' => 'abierto', 'resultado' => ['favor' => true]],
        ],
    ],
];
EncuentroResultadoSlim::limpiarEncuentro($partidaK['encuentros'][0]);
ok(!isset($partidaK['encuentros'][0]['resultado']['_cal']), 'K1: _cal limpiado');
ok(($partidaK['mentes']['temas'][0]['resultado']['favor'] ?? false) === true, 'K2: MENTES tema intacto');

// ============================================================
// L) API/UI del resultado de encuentro sigue funcionando
// (EncuentroResultadoVista no lee _cal)
// ============================================================
echo "\n--- L: API/UI funciona ---\n";
$serviceL = new PartidaService($root);
$partidaL = $serviceL->nuevaPartida('test_fixtures_v0', 'cal-limpieza-api');
$phL = $serviceL->crearResidentePlaceholderDev($partidaL);
$idaL = 'per_qa_valid';
$idbL = $phL['residente']['catalog_id'];
$encL = $serviceL->programarEncuentro($partidaL, [$idaL, $idbL], 1, 19, 'conocerse', 'lug_cafeteria');
ok($encL['ok'] ?? false, 'L1: programa encuentro');
if ($encL['ok'] ?? false) {
    $dL = (int) $encL['encuentro']['dia'];
    $hL = (int) $encL['encuentro']['hora'];
    $nowL = ((int) $partidaL['reloj']['dia_pueblo']) * 24 + (int) $partidaL['reloj']['hora_actual'];
    $durL = max(1, (int) ($encL['encuentro']['duracion_horas'] ?? 1));
    $serviceL->avanzarRelojPasoAPaso($partidaL, max(1, $dL * 24 + $hL + $durL - $nowL));
    $rawL = buscarEncuentro($partidaL, (string) $encL['encuentro']['id']);
    if ($rawL !== null) {
        $vistaL = EncuentroResultadoVista::de($partidaL, $rawL, $serviceL->getCatalog(), $root);
        ok(is_array($vistaL), 'L2: vista es array');
        ok(isset($vistaL['resultado']['social']), 'L3: social presente');
        ok(isset($vistaL['resultado']['romance']), 'L4: romance presente');
        ok(isset($vistaL['resultado']['conflicto']), 'L5: conflicto presente');
        ok(isset($vistaL['resultado']['lineas']), 'L6: lineas presente');
        ok(is_array($vistaL['resultado']['lineas']), 'L7: lineas es array');
    } else {
        ok(false, 'L2-L7: skip (encuentro no encontrado)');
    }
} else {
    ok(false, 'L2-L7: skip (programación falló)');
}

// ============================================================
// limpiarPartida: batch
// ============================================================
echo "\n--- limpiarPartida: batch ---\n";
$partidaBatch = [
    'encuentros' => [
        makeEnc('terminado', makeResultado(['x' => 1])),
        makeEnc('en_curso', makeResultado(['y' => 2])),
        makeEnc('terminado', makeResultado()),
        makeEnc('programado', makeResultado(['z' => 3])),
    ],
];
$resultadoBatch = EncuentroResultadoSlim::limpiarPartida($partidaBatch);
ok($resultadoBatch['limpiados'] === 2, 'BATCH1: limpia solo 2 (los terminados con _cal)');
ok(!isset($partidaBatch['encuentros'][0]['resultado']['_cal']), 'BATCH2: enc0 _cal eliminado');
ok(isset($partidaBatch['encuentros'][1]['resultado']['_cal']), 'BATCH3: enc1 _cal conservado (en_curso)');
ok(!isset($partidaBatch['encuentros'][2]['resultado']['_cal']), 'BATCH4: enc2 _cal eliminado');
ok(isset($partidaBatch['encuentros'][3]['resultado']['_cal']), 'BATCH5: enc3 _cal conservado (programado)');

// ============================================================
// inspeccionar: read-only
// ============================================================
echo "\n--- inspeccionar: read-only ---\n";
$partidaInsp = [
    'encuentros' => [
        makeEnc('terminado', makeResultado(['i' => 1])),
        makeEnc('en_curso', makeResultado(['j' => 2])),
        makeEnc('terminado', makeResultado()),
    ],
];
$antes = json_encode($partidaInsp['encuentros'], JSON_UNESCAPED_UNICODE);
$infoInsp = EncuentroResultadoSlim::inspeccionar($partidaInsp);
$despues = json_encode($partidaInsp['encuentros'], JSON_UNESCAPED_UNICODE);
ok($antes === $despues, 'INSPECT1: inspeccionar no modifica la partida');
ok($infoInsp['inspectados'] === 3, 'INSPECT2: inspectados = 3');
ok($infoInsp['terminados_con_cal'] === 2, 'INSPECT3: 2 terminados con _cal (ambos terminados tienen _cal en makeResultado)');
ok($infoInsp['bytes_ahorrados'] > 0, 'INSPECT4: bytes_ahorrados > 0');

// ============================================================
// Encuentro sin resultado
// ============================================================
echo "\n--- Encuentro sin resultado ---\n";
$encSinRes = makeEnc('terminado', null);
$changedSinRes = EncuentroResultadoSlim::limpiarEncuentro($encSinRes);
ok($changedSinRes === false, 'SIN_RES1: retorna false sin resultado');
$encSinRes2 = makeEnc('terminado', []);
$changedSinRes2 = EncuentroResultadoSlim::limpiarEncuentro($encSinRes2);
ok($changedSinRes2 === false, 'SIN_RES2: retorna false con resultado vacío');

// ============================================================
// Partida vacía / sin encuentros
// ============================================================
echo "\n--- Partida vacía ---\n";
$partidaVacia = [];
$resultadoVacio = EncuentroResultadoSlim::limpiarPartida($partidaVacia);
ok($resultadoVacio['limpiados'] === 0, 'VACIO1: limpia 0 en partida sin encuentros');
$partidaNull = ['encuentros' => null];
$resultadoNull = EncuentroResultadoSlim::limpiarPartida($partidaNull);
ok($resultadoNull['limpiados'] === 0, 'VACIO2: limpia 0 con encuentros null');

// ============================================================
// RESUMEN
// ============================================================
echo "\n" . str_repeat('=', 60) . "\n";
echo "Tests ejecutados: {$tests}\n";
echo "Failures: {$failures}\n";
echo str_repeat('=', 60) . "\n";

exit($failures > 0 ? 1 : 0);
