<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\HistorialPar;
use AquiHayTema\Engine\MensajitoVoz;
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
// Setup
// ================================================================
$partida = [
    'meta' => ['seed' => 'test-fase2c'],
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

// ================================================================
// 1. contextoNarrativo: par sin historia
// ================================================================
echo "--- Test 1: contextoNarrativo sin historia ---\n";
$ctx = HistorialPar::contextoNarrativo($partida, $pA, $pC);
ok($ctx === 'aún no nos conocemos', "sin historia: '$ctx'");

// ================================================================
// 2. contextoNarrativo: se conocieron + primera cita
// ================================================================
echo "\n--- Test 2: contextoNarrativo con hitos ---\n";
$ctx2 = HistorialPar::contextoNarrativo($partida, $pA, $pB);
ok($ctx2 === 'hemos salido juntos', "con hitos: '$ctx2'");

// ================================================================
// 3. contextoNarrativo: solo se conocieron
// ================================================================
echo "\n--- Test 3: contextoNarrativo solo se conocieron ---\n";
$partida2 = $partida;
$partida2['bitacora_relaciones'] = [
    [
        'tipo' => RelacionBitacora::SE_CONOCIERON,
        'participantes' => [$pA, $pC],
        'fecha' => ['dia' => 1],
        'meta' => [],
    ],
];
$ctx3 = HistorialPar::contextoNarrativo($partida2, $pA, $pC);
ok($ctx3 === 'nos conocimos hace poco', "solo se conocieron: '$ctx3'");

// ================================================================
// 4. contextoNarrativo: pareja
// ================================================================
echo "\n--- Test 4: contextoNarrativo pareja ---\n";
$partida3 = $partida;
$partida3['bitacora_relaciones'] = [
    [
        'tipo' => RelacionBitacora::SE_CONOCIERON,
        'participantes' => [$pA, $pC],
        'fecha' => ['dia' => 1],
        'meta' => [],
    ],
    [
        'tipo' => RelacionBitacora::DECLARACION,
        'participantes' => [$pA, $pC],
        'fecha' => ['dia' => 4],
        'meta' => [],
    ],
];
$ctx4 = HistorialPar::contextoNarrativo($partida3, $pA, $pC);
ok($ctx4 === 'somos pareja', "pareja: '$ctx4'");

// ================================================================
// 5. contextoNarrativo: múltiples encuentros
// ================================================================
echo "\n--- Test 5: contextoNarrativo múltiples encuentros ---\n";
$partida4 = $partida;
$partida4['encuentros'] = [
    ['id' => 'e1', 'participantes' => [$pA, $pC], 'tipo' => 'cita', 'dia' => 2, 'hora' => 19, 'estado' => 'terminado',
     'resultado' => ['por_participante' => [$pA => ['resultado' => 'bien', 'carga' => 0.2], $pC => ['resultado' => 'bien', 'carga' => 0.1]]]],
    ['id' => 'e2', 'participantes' => [$pA, $pC], 'tipo' => 'cita', 'dia' => 4, 'hora' => 19, 'estado' => 'terminado',
     'resultado' => ['por_participante' => [$pA => ['resultado' => 'bien', 'carga' => 0.3], $pC => ['resultado' => 'bien', 'carga' => 0.2]]]],
];
$partida4['bitacora_relaciones'] = [
    ['tipo' => RelacionBitacora::SE_CONOCIERON, 'participantes' => [$pA, $pC], 'fecha' => ['dia' => 1], 'meta' => []],
];
$ctx5 = HistorialPar::contextoNarrativo($partida4, $pA, $pC);
ok($ctx5 === 'nos hemos visto 2 veces', "múltiples: '$ctx5'");

// ================================================================
// 6. Token historial se procesa correctamente
// ================================================================
echo "\n--- Test 6: token historial en MensajitoVoz ---\n";
$varsConHistorial = ['otro' => 'Hugo', 'texto' => 'amigo', 'historial' => 'hemos salido juntos'];
$texto = MensajitoVoz::linea($partida, 'f_opinion', $varsConHistorial, 'test_hist|' . $pA, $pA);
ok($texto !== '', 'token historial: produce texto');
ok(strpos($texto, '{historial}') === false, 'token historial: no queda literal');
ok(strpos($texto, '{otro}') === false, 'token otro: no queda literal');

// ================================================================
// 7. Token historial vacío no rompe variantes existentes
// ================================================================
echo "\n--- Test 7: token historial vacío ---\n";
$varsSinHistorial = ['otro' => 'Hugo', 'texto' => 'amigo'];
$texto2 = MensajitoVoz::linea($partida, 'f_opinion', $varsSinHistorial, 'test_no_hist|' . $pA, $pA);
ok($texto2 !== '', 'sin historial: produce texto');
ok(strpos($texto2, '{historial}') === false, 'sin historial: no queda literal');

// ================================================================
// 8. Variantes con historial existen en el pool
// ================================================================
echo "\n--- Test 8: variantes con historial existen ---\n";
// Test que la familia f_opinion ahora tiene 7 variantes (5 originales + 2 con historial)
$textosConHistorial = [];
$textosSinHistorial = [];
for ($i = 0; $i < 20; $i++) {
    $vars = ['otro' => 'Hugo', 'texto' => 'amigo', 'historial' => 'hemos salido juntos'];
    $t = MensajitoVoz::linea($partida, 'f_opinion', $vars, 'test_pool_' . $i, $pA);
    if (strpos($t, 'hemos salido') !== false || strpos($t, 'historial') !== false) {
        $textosConHistorial[] = $t;
    } else {
        $textosSinHistorial[] = $t;
    }
}
ok(count($textosConHistorial) > 0, 'variantes con historial: al menos una activa');
ok(count($textosSinHistorial) > 0, 'variantes sin historial: siguen apareciendo');

// ================================================================
// 9. f_alerta_vecinal con historial
// ================================================================
echo "\n--- Test 9: f_alerta_vecinal con historial ---\n";
$textoAlerta = MensajitoVoz::linea($partida, 'f_alerta_vecinal', ['otro' => 'Tamara', 'texto' => 'apagado', 'historial' => 'nos conocimos hace poco'], 'test_alerta|' . $pA, $pA);
ok($textoAlerta !== '', 'alerta con historial: produce texto');
ok(strpos($textoAlerta, '{historial}') === false, 'alerta: historial procesado');

// ================================================================
// 10. f_alerta_vecinal sin historial (token vacío)
// ================================================================
echo "\n--- Test 10: f_alerta_vecinal sin historial ---\n";
$textoAlerta2 = MensajitoVoz::linea($partida, 'f_alerta_vecinal', ['otro' => 'Tamara', 'texto' => 'apagado'], 'test_alerta2|' . $pA, $pA);
ok($textoAlerta2 !== '', 'alerta sin historial: produce texto');

// ================================================================
// 11. f_opinion variantes con historial producen texto coherente
// ================================================================
echo "\n--- Test 11: coherencia copy f_opinion ---\n";
for ($i = 0; $i < 10; $i++) {
    $vars = ['otro' => 'Hugo', 'texto' => 'amigo', 'historial' => 'nos hemos visto 3 veces'];
    $t = MensajitoVoz::linea($partida, 'f_opinion', $vars, 'coherencia_' . $i, $pA);
    ok($t !== '', "coherencia f_opinion #$i: no vacío");
    ok(strpos($t, 'Hugo') !== false, "coherencia f_opinion #$i: contiene nombre");
}

// ================================================================
// 12. No se tocan otras familias (f_dilema, f_confidencia)
// ================================================================
echo "\n--- Test 12: otras familias intactas ---\n";
$textoDilema = MensajitoVoz::linea($partida, 'f_dilema', ['nombre_a' => 'Hugo', 'nombre_b' => 'Tamara', 'texto' => 'dos personas'], 'test_dilema|' . $pA, $pA);
ok(strpos($textoDilema, '{historial}') === false, 'f_dilema: sin token historial');

$textoConfidencia = MensajitoVoz::linea($partida, 'f_confidencia', ['texto' => 'nervioso'], 'test_conf|' . $pA, $pA);
ok(strpos($textoConfidencia, '{historial}') === false, 'f_confidencia: sin token historial');

echo $fail === 0 ? "\nfase2c_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
