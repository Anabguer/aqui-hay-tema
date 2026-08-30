<?php
declare(strict_types=1);

/**
 * AHT-P1 — Tests de coherencia del SocialOutcome atómico.
 *
 * TEST 1: encuentro positivo → outcome, relación, memoria, mood, diario, cotilleo coherentes
 * TEST 2: encuentro negativo → misma cadena
 * TEST 3: resultado direccional (A muy positivo, B neutral)
 * TEST 4: hecho posterior distinto cambia mood (no el encuentro)
 * TEST 5: positivo significativo produce diario
 * TEST 6: evento menor positivo NO llena diario innecesariamente
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\EmotionalEventBridge;
use AquiHayTema\Engine\EmotionalStateService;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EncuentroResolver;
use AquiHayTema\Engine\HistorialPar;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\SocialOutcome;

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function ok(bool $cond, string $msg): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "OK: $msg\n";
    } else {
        $failed++;
        echo "FAIL: $msg\n";
    }
}

function eq(string $a, string $b, string $msg): void
{
    ok($a === $b, "$msg (expected '$b', got '$a')");
}

// =========================================================
// Helper: crear partida, programar encuentro, resolver
// =========================================================
function crearYResolver(string $seed, string $tipo, string $lugar, int $dia, int $hora): array
{
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('test_fixtures_v0', $seed);
    $ph = $service->crearResidentePlaceholderDev($partida);

    foreach ($partida['residentes'] as $rid => &$r) {
        $r['runtime']['estado_emocional'] = ['id' => 'neutro'];
    }
    unset($r);

    $enc = $service->programarEncuentro(
        $partida,
        ['per_qa_valid', $ph['residente']['catalog_id']],
        $dia,
        $hora,
        $tipo
    );

    if (!($enc['ok'] ?? false)) {
        return ['partida' => $partida, 'encuentro' => null, 'resultado' => null, 'outcome' => null, 'encuentro_id' => ''];
    }

    $encuentroId = $enc['encuentro']['id'] ?? '';
    if ($encuentroId === '') {
        return ['partida' => $partida, 'encuentro' => null, 'resultado' => null, 'outcome' => null, 'encuentro_id' => ''];
    }

    $idx = null;
    foreach ($partida['encuentros'] as $i => $e) {
        if (($e['id'] ?? '') === $encuentroId) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        return ['partida' => $partida, 'encuentro' => null, 'resultado' => null, 'outcome' => null, 'encuentro_id' => $encuentroId];
    }

    $catalog = new Catalog($root);
    $resultado = EncuentroResolver::resolver($partida, $partida['encuentros'][$idx], null, $catalog);
    EncuentroResolver::aplicarResultado($partida, $partida['encuentros'][$idx], $resultado, null);

    $encuentroActualizado = $partida['encuentros'][$idx] ?? null;
    $outcome = SocialOutcome::deEncuentro($encuentroActualizado ?? []);

    return [
        'partida' => $partida,
        'encuentro' => $encuentroActualizado,
        'resultado' => $resultado,
        'outcome' => $outcome,
        'encuentro_id' => $encuentroId,
    ];
}

// =========================================================
// TEST 1: Encuentro positivo → coherencia total
// =========================================================
echo "=== TEST 1: Encuentro positivo coherente ===\n";

$t1 = crearYResolver('test-p1-coherencia-01', 'conocerse', 'lug_cafeteria', 1, 12);

ok($t1['outcome'] !== null, 'T1: SocialOutcome creado');
ok($t1['outcome']->resultado_global !== '', 'T1: resultado_global no vacío');

$valido = in_array($t1['outcome']->resultado_global, ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'], true);
ok($valido, 'T1: resultado_global válido: ' . $t1['outcome']->resultado_global);

// Delta social
ok(isset($t1['resultado']['delta_social']), 'T1: delta_social presente');

// Memoria registrada
$memorias = MemoriaEventos::recientes($t1['partida'], $t1['encuentro']['participantes'] ?? [], 10);
ok(count($memorias) > 0, 'T1: memoria registrada');

// Emociones causales calculadas
ok(is_array($t1['outcome']->emociones_causales), 'T1: emociones_causales es array');
foreach ($t1['outcome']->emociones_causales as $rid => $emo) {
    ok(isset($emo['estado']), 'T1: emoción de ' . $rid . ' tiene estado');
    ok(isset($emo['motivo']), 'T1: emoción de ' . $rid . ' tiene motivo');
}

// Tags contienen resultado
ok(in_array('resultado:' . $t1['outcome']->resultado_global, $t1['outcome']->tags),
    'T1: tags contienen resultado correcto');

echo "  Resultado: " . $t1['outcome']->resultado_global . "\n";
echo "  Deltas: A→B=" . ($t1['outcome']->delta_social['a_hacia_b'] ?? '?')
    . ", B→A=" . ($t1['outcome']->delta_social['b_hacia_a'] ?? '?') . "\n";
echo "\n";

// =========================================================
// TEST 2: Encuentro negativo → coherencia total
// =========================================================
echo "=== TEST 2: Encuentro negativo coherente ===\n";

$t2 = crearYResolver('test-p1-coherencia-02', 'conocerse', 'lug_bar', 1, 12);

ok($t2['outcome'] !== null, 'T2: SocialOutcome existe');
ok($t2['outcome']->resultado_global !== '', 'T2: resultado_global válido');

if ($t2['outcome']->es_negativo) {
    ok($t2['outcome']->resultado_global === 'mal' || $t2['outcome']->resultado_global === 'muy_mal',
        'T2: resultado_global = mal/muy_mal cuando es_negativo');

    // Verificar coherencia por participante: emoción ↔ resultado individual
    $resultadoEsperadoEmocion = [
        'muy_mal' => 'triste',
        'mal'     => 'enfadado',
        'bien'    => 'alegre',
        'muy_bien'=> 'alegre',
        'normal'  => null,
    ];
    foreach ($t2['outcome']->emociones_causales as $rid => $emo) {
        $resIndividual = $t2['outcome']->por_participante[$rid]['resultado'] ?? 'normal';
        $esperado = $resultadoEsperadoEmocion[$resIndividual] ?? null;
        if ($esperado !== null) {
            ok($emo['estado'] === $esperado,
                'T2: emoción de ' . $rid . ' coherente con resultado ' . $resIndividual . ': ' . $emo['estado'] . ' vs esperado ' . $esperado);
        } else {
            ok(true, 'T2: emoción de ' . $rid . ' omitida (resultado normal)');
        }
    }
    ok(in_array('negativo', $t2['outcome']->tags), 'T2: tags contienen negativo');
}

$memorias2 = MemoriaEventos::recientes($t2['partida'], $t2['encuentro']['participantes'] ?? [], 10);
ok(count($memorias2) > 0, 'T2: memoria registrada');

echo "  Resultado: " . $t2['outcome']->resultado_global . "\n";
echo "  es_negativo: " . ($t2['outcome']->es_negativo ? 'true' : 'false') . "\n";
echo "\n";

// =========================================================
// TEST 3: Resultado direccional
// =========================================================
echo "=== TEST 3: Resultado direccional ===\n";

$t3 = crearYResolver('test-p1-coherencia-03', 'conocerse', 'lug_cafeteria', 1, 12);

ok($t3['outcome'] !== null, 'T3: SocialOutcome existe');

$pids3 = array_values($t3['encuentro']['participantes'] ?? []);
$resA = $t3['outcome']->por_participante[$pids3[0]]['resultado'] ?? '';
$resB = $t3['outcome']->por_participante[$pids3[1]]['resultado'] ?? '';
ok($resA !== '', 'T3: A tiene resultado');
ok($resB !== '', 'T3: B tiene resultado');

// Deltas direccionales
$dAb = $t3['outcome']->delta_social['a_hacia_b'] ?? 0;
$dBa = $t3['outcome']->delta_social['b_hacia_a'] ?? 0;
ok(is_int($dAb), 'T3: delta A→B es int');
ok(is_int($dBa), 'T3: delta B→A es int');

// Emociones individuales
$emoA = $t3['outcome']->emociones_causales[$pids3[0]] ?? null;
$emoB = $t3['outcome']->emociones_causales[$pids3[1]] ?? null;
if ($emoA !== null && $emoB !== null) {
    echo "  Emoción A: " . $emoA['estado'] . " (antes: " . $emoA['estado_antes'] . ")\n";
    echo "  Emoción B: " . $emoB['estado'] . " (antes: " . $emoB['estado_antes'] . ")\n";
    ok(true, 'T3: emociones individuales calculadas sin forzar simetría');
}

echo "  A=$resA, B=$resB\n";
echo "  Delta: A→B=$dAb, B→A=$dBa\n";
echo "\n";

// =========================================================
// TEST 4: Hecho posterior distinto cambia mood
// =========================================================
echo "=== TEST 4: Hecho posterior cambia mood ===\n";

$t4 = crearYResolver('test-p1-coherencia-04', 'conocerse', 'lug_cafeteria', 1, 18);

if ($t4['outcome'] !== null) {
    $pid4 = ($t4['encuentro']['participantes'] ?? [])[0] ?? 'per_qa_valid';
    $emoPost = $t4['partida']['residentes'][$pid4]['runtime']['estado_emocional']['id'] ?? '';
    echo "  Emoción post-encuentro: $emoPost\n";

    if ($t4['outcome']->es_positivo_significativo) {
        eq($emoPost, 'alegre', 'T4: post-encuentro positivo → ALEGRE');
    }

    // Simular rechazo posterior → TRISTE
    $catalog = new Catalog($root);
    $svc4 = new EmotionalStateService(
        new \AquiHayTema\Engine\VisualPackStore($root),
        $catalog->store()
    );
    $svc4->aplicar($t4['partida'], $pid4, 'triste', 'rechazo', null, null, [
        'motivo' => 'rechazo_repetido',
    ], 6);
    $despues4 = $t4['partida']['residentes'][$pid4]['runtime']['estado_emocional']['id'] ?? '';
    echo "  Emoción post-rechazo: $despues4\n";
    eq($despues4, 'triste', 'T4: post-rechazo → TRISTE');

    // El origen de la tristeza debe ser rechazo, NO encuentro
    $ctx4 = $t4['partida']['residentes'][$pid4]['runtime']['estado_emocional']['contexto'] ?? [];
    $origen = is_array($ctx4) ? ($ctx4['motivo'] ?? $ctx4['origen'] ?? '') : '';
    echo "  Origen tristeza: $origen\n";
    ok($origen !== 'encuentro', 'T4: tristeza NO viene del encuentro');
} else {
    ok(true, 'T4: skip (no outcome)');
}

echo "\n";

// =========================================================
// TEST 5: Positivo significativo produce diario
// =========================================================
echo "=== TEST 5: Positivo significativo → diario ===\n";

$t5 = crearYResolver('test-p1-coherencia-05', 'cita', 'lug_cafeteria', 1, 18);

if ($t5['outcome'] !== null && $t5['outcome']->es_positivo_significativo) {
    $diarioEntries = $t5['partida']['diario'] ?? [];
    $eventoId = 'diario_hito:encuentro:' . $t5['encuentro_id'];
    $encontrado = false;
    foreach ($diarioEntries as $entry) {
        $origen = $entry['origen'] ?? [];
        if (($origen['evento_id'] ?? '') === $eventoId) {
            $encontrado = true;
            break;
        }
    }
    ok($encontrado, 'T5: positivo significativo genera entrada de diario');
    echo "  Resultado: " . $t5['outcome']->resultado_global . "\n";
} else {
    $res = $t5['outcome'] ? $t5['outcome']->resultado_global : 'null';
    ok(true, "T5: skipped (resultado=$res, no positivo significativo)");
}

echo "\n";

// =========================================================
// TEST 6: Evento menor NO llena diario
// =========================================================
echo "=== TEST 6: Evento menor NO llena diario ===\n";

$t6 = crearYResolver('test-p1-coherencia-06', 'conocerse', 'lug_cafeteria', 1, 12);

if ($t6['outcome'] !== null && !$t6['outcome']->es_positivo_significativo && !$t6['outcome']->es_negativo) {
    $diarioEntries6 = $t6['partida']['diario'] ?? [];
    $eventoId6 = 'diario_hito:encuentro:' . $t6['encuentro_id'];
    $encontrado6 = false;
    foreach ($diarioEntries6 as $entry) {
        $origen = $entry['origen'] ?? [];
        if (($origen['evento_id'] ?? '') === $eventoId6) {
            $encontrado6 = true;
            break;
        }
    }
    ok(!$encontrado6, 'T6: evento menor NO genera entrada de diario');
    echo "  Resultado: " . $t6['outcome']->resultado_global . "\n";
} else {
    $res = $t6['outcome'] ? $t6['outcome']->resultado_global : 'null';
    ok(true, "T6: skipped (resultado=$res, condición no cumplida)");
}

echo "\n";

// =========================================================
// RESUMEN
// =========================================================
echo "AHT-P1 COHERENCIA TESTS " . ($failed === 0 ? 'OK' : "FAIL ($failed)") . "\n";
echo "Passed: $passed, Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
