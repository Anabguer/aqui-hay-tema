<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncounterOutcome;
use AquiHayTema\Engine\EncuentroResolver;
use AquiHayTema\Engine\HistorialPar;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\RelacionBitacora;

$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$pA = 'per_hugo';
$pB = 'per_tamara';
$pC = 'per_lucia';

// ================================================================
// Setup: partida con historia previa entre A y B
// ================================================================
$partida = [
    'meta' => ['seed' => 'test-fase2b'],
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 18],
    'residentes' => [
        $pA => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
        $pB => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
        $pC => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
    ],
    'memoria_eventos' => [],
    'bitacora_relaciones' => [
        [
            'tipo' => RelacionBitacora::SE_CONOCIERON,
            'participantes' => [$pA, $pB],
            'fecha' => ['dia' => 1],
            'meta' => [],
        ],
        [
            'tipo' => RelacionBitacora::PRIMERA_CITA,
            'participantes' => [$pA, $pB],
            'fecha' => ['dia' => 3],
            'meta' => [],
        ],
    ],
    'encuentros' => [
        [
            'id' => 'enc_previo',
            'participantes' => [$pA, $pB],
            'tipo' => 'cita',
            'dia' => 3,
            'hora' => 19,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $pA => ['resultado' => 'bien', 'carga' => 0.3],
                    $pB => ['resultado' => 'bien', 'carga' => 0.2],
                ],
            ],
        ],
    ],
];

$catalog = new Catalog($root);
$cal = CalibracionConfig::load($root);

// ================================================================
// Test 1: Resolver adjunta historial_par en resultado real
// ================================================================
echo "--- Test 1: historial_par en resultado real ---\n";

$enc1 = [
    'id' => 'enc_fase2b_1',
    'participantes' => [$pA, $pB],
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 5,
    'hora' => 18,
    'intencion' => 'celeste_organizado',
];

$resultado1 = EncuentroResolver::resolver($partida, $enc1, null, $catalog);

ok(isset($resultado1['historial_par']), 'resultado tiene historial_par');
ok(is_array($resultado1['historial_par']), 'historial_par es array');
ok($resultado1['historial_par']['clave'] === $pA . ':' . $pB || $resultado1['historial_par']['clave'] === $pB . ':' . $pA, 'historial_par: clave correcta');
ok(is_array($resultado1['historial_par']['hitos']), 'historial_par: hitos presentes');
ok(is_array($resultado1['historial_par']['encuentros']), 'historial_par: encuentros presentes');
ok(is_array($resultado1['historial_par']['resumen']), 'historial_par: resumen presente');

// ================================================================
// Test 2: historial_refleja datos reales
// ================================================================
echo "\n--- Test 2: historial refleja datos reales ---\n";

$hist = $resultado1['historial_par'];
ok(count($hist['hitos']) === 2, 'hitos: 2 (se conocieron + primera cita)');
ok($hist['hitos'][0]['tipo'] === RelacionBitacora::SE_CONOCIERON, 'hitos: se conocieron');
ok($hist['hitos'][1]['tipo'] === RelacionBitacora::PRIMERA_CITA, 'hitos: primera cita');
ok(count($hist['encuentros']) === 1, 'encuentros: 1 previo');
ok($hist['encuentros'][0]['id'] === 'enc_previo', 'encuentros: el previo');
ok($hist['resumen']['se_conocen'] === true, 'resumen: se conocen');
ok($hist['resumen']['ha_habido_cita'] === true, 'resumen: ha habido cita');

// ================================================================
// Test 3: Par sin historia → historial vacío
// ================================================================
echo "\n--- Test 3: par sin historia ---\n";

$enc2 = [
    'id' => 'enc_fase2b_2',
    'participantes' => [$pA, $pC],
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 5,
    'hora' => 18,
    'intencion' => 'celeste_organizado',
];

$resultado2 = EncuentroResolver::resolver($partida, $enc2, null, $catalog);

ok(isset($resultado2['historial_par']), 'sin historia: historial_par presente');
ok(count($resultado2['historial_par']['hitos']) === 0, 'sin historia: 0 hitos');
ok(count($resultado2['historial_par']['encuentros']) === 0, 'sin historia: 0 encuentros');
ok($resultado2['historial_par']['resumen']['se_conocen'] === false, 'sin historia: no se conocen');

// ================================================================
// Test 4: EncounterOutcome expone historial_par
// ================================================================
echo "\n--- Test 4: EncounterOutcome historial_par ---\n";

$outcome = EncounterOutcome::fromRaw($resultado1, $enc1);
ok($outcome->historialPar() !== null, 'outcome: historial_par no null');
ok($outcome->historialPar()['resumen']['se_conocen'] === true, 'outcome: se conocen');

$outcomeSin = EncounterOutcome::fromRaw([], []);
ok($outcomeSin->historialPar() === null, 'outcome sin historial: null');

// ================================================================
// Test 5: resultado sin catálogo → placeholder con historial
// ================================================================
echo "\n--- Test 5: resultado sin catálogo incluye historial ---\n";

$resultadoPh = EncuentroResolver::resolver($partida, [
    'id' => 'enc_ph',
    'participantes' => [$pA, $pB],
    'tipo' => 'conocerse',
    'dia' => 5,
    'hora' => 18,
]);
ok(!empty($resultadoPh['_placeholder']), 'sin catálogo: es placeholder');
ok(isset($resultadoPh['historial_par']), 'sin catálogo: tiene historial_par');
ok($resultadoPh['historial_par']['resumen']['se_conocen'] === true, 'sin catálogo: refleja historia');

// ================================================================
// Test 6: historial no contamina cálculos existentes
// ================================================================
echo "\n--- Test 6: sin contaminación ---\n";

ok(isset($resultado1['delta_social']), 'resultado: delta_social presente');
ok(isset($resultado1['por_participante']), 'resultado: por_participante presente');
ok(isset($resultado1['experiencia']), 'resultado: experiencia presente');
ok(!isset($resultado1['experiencia']['historial_par']), 'experiencia: no contaminada con historial');

// ================================================================
// Test 7: 2 encuentros del mismo par → segundo ve historia del primero
// ================================================================
echo "\n--- Test 7: segundo encuentro ve historia ---\n";

$enc4 = [
    'id' => 'enc_fase2b_segundo',
    'participantes' => [$pA, $pB],
    'tipo' => 'cita',
    'lugar' => 'lug_bar',
    'dia' => 5,
    'hora' => 20,
    'intencion' => 'celeste_organizado',
];

$resultado4 = EncuentroResolver::resolver($partida, $enc4, null, $catalog);
$hist4 = $resultado4['historial_par'];

ok(count($hist4['encuentros']) === 1, 'segundo: 1 encuentro previo');
ok($hist4['encuentros'][0]['id'] === 'enc_previo', 'segundo: ve el encuentro previo');
ok($hist4['resumen']['total_encuentros'] === 1, 'segundo: resumen 1 encuentro');
ok($hist4['resumen']['ultima_tendencia'] === 'positiva', 'segundo: tendencia positiva');

echo "\nhistorial_par: " . json_encode($hist4['resumen'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo $fail === 0 ? "\nfase2b_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
