<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CopyRechazoPropuesta;
use AquiHayTema\Engine\HistorialPar;
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

function containsNo(string $haystack, string $needle, string $label): void
{
    ok(strpos($haystack, $needle) === false, $label);
}

$pA = 'per_hugo';
$pB = 'per_tamara';
$pC = 'per_lucia';

// ================================================================
// Setup: partida con historia entre A y B
// ================================================================
$partida = [
    'meta' => ['seed' => 'test-fase2d'],
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 18],
    'rng' => ['cursor' => 0],
    'residentes' => [
        $pA => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']], 'identidad_publica' => ['nombre' => 'Hugo']],
        $pB => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']], 'identidad_publica' => ['nombre' => 'Tamara']],
        $pC => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']], 'identidad_publica' => ['nombre' => 'Lucia']],
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
    'rechazos_propuesta' => [],
];

// ================================================================
// 1. historialContexto: sin historia
// ================================================================
echo "--- Test 1: historialContexto sin historia ---\n";
$ctx = CopyRechazoPropuesta::historialContexto($partida, $pA, $pC);
ok($ctx === '', 'sin historia: vacío');

// ================================================================
// 2. historialContexto: se conocieron + primera cita
// ================================================================
echo "\n--- Test 2: historialContexto con cita previa ---\n";
$ctx2 = CopyRechazoPropuesta::historialContexto($partida, $pA, $pB);
ok($ctx2 === 'Aunque hemos salido juntos', "cita previa: '$ctx2'");

// ================================================================
// 3. historialContexto: pareja
// ================================================================
echo "\n--- Test 3: historialContexto pareja ---\n";
$partida2 = $partida;
$partida2['bitacora_relaciones'] = [
    ['tipo' => RelacionBitacora::SE_CONOCIERON, 'participantes' => [$pA, $pC], 'fecha' => ['dia' => 1], 'meta' => []],
    ['tipo' => RelacionBitacora::DECLARACION, 'participantes' => [$pA, $pC], 'fecha' => ['dia' => 4], 'meta' => []],
];
$ctx3 = CopyRechazoPropuesta::historialContexto($partida2, $pA, $pC);
ok($ctx3 === 'A pesar de que son pareja', "pareja: '$ctx3'");

// ================================================================
// 4. historialContexto: solo se conocen
// ================================================================
echo "\n--- Test 4: historialContexto solo se conocen ---\n";
$partida3 = $partida;
$partida3['bitacora_relaciones'] = [
    ['tipo' => RelacionBitacora::SE_CONOCIERON, 'participantes' => [$pA, $pC], 'fecha' => ['dia' => 1], 'meta' => []],
];
$ctx4 = CopyRechazoPropuesta::historialContexto($partida3, $pA, $pC);
ok($ctx4 === '', "solo conocidos: '$ctx4'");

// ================================================================
// 5. historialContexto: múltiples encuentros
// ================================================================
echo "\n--- Test 5: historialContexto varios encuentros ---\n";
$partida4 = $partida;
$partida4['bitacora_relaciones'] = [
    ['tipo' => RelacionBitacora::SE_CONOCIERON, 'participantes' => [$pA, $pC], 'fecha' => ['dia' => 1], 'meta' => []],
];
$partida4['encuentros'] = [
    ['id' => 'e1', 'participantes' => [$pA, $pC], 'tipo' => 'cafe', 'dia' => 2, 'hora' => 10, 'estado' => 'terminado',
     'resultado' => ['por_participante' => [$pA => ['resultado' => 'bien', 'carga' => 0.1], $pC => ['resultado' => 'bien', 'carga' => 0.1]]]],
    ['id' => 'e2', 'participantes' => [$pA, $pC], 'tipo' => 'cafe', 'dia' => 3, 'hora' => 10, 'estado' => 'terminado',
     'resultado' => ['por_participante' => [$pA => ['resultado' => 'bien', 'carga' => 0.2], $pC => ['resultado' => 'bien', 'carga' => 0.1]]]],
    ['id' => 'e3', 'participantes' => [$pA, $pC], 'tipo' => 'cafe', 'dia' => 4, 'hora' => 10, 'estado' => 'terminado',
     'resultado' => ['por_participante' => [$pA => ['resultado' => 'bien', 'carga' => 0.1], $pC => ['resultado' => 'bien', 'carga' => 0.1]]]],
];
$ctx5 = CopyRechazoPropuesta::historialContexto($partida4, $pA, $pC);
ok($ctx5 === 'Aunque nos hemos visto varias veces', "varios: '$ctx5'");

// ================================================================
// 6. historialContexto: IDs vacíos o iguales
// ================================================================
echo "\n--- Test 6: historialContexto edge cases ---\n";
ok(CopyRechazoPropuesta::historialContexto($partida, '', $pB) === '', 'ID vacío rechazador');
ok(CopyRechazoPropuesta::historialContexto($partida, $pA, '') === '', 'ID vacío proponente');
ok(CopyRechazoPropuesta::historialContexto($partida, $pA, $pA) === '', 'mismo ID');

// ================================================================
// 7. fraseCausaHumana con historia: con copy_id
// ================================================================
echo "\n--- Test 7: fraseCausaHumana con historia + copy_id ---\n";
$hablante = ['residente_id' => $pB, 'copy_id' => 'lavadora'];
$frase = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante, $pA);
ok(strpos($frase, 'Tamara') !== false, 'contiene nombre rechazador');
ok(strpos($frase, 'lavadora') !== false, 'contiene copy Voluntad');
ok(strpos($frase, 'Aunque hemos salido juntos') !== false, 'contiene contexto histórico');
ok(substr($frase, -1) === '.', 'termina en punto');

// ================================================================
// 8. fraseCausaHumana sin historia: con copy_id
// ================================================================
echo "\n--- Test 8: fraseCausaHumana sin historia + copy_id ---\n";
$hablante2 = ['residente_id' => $pC, 'copy_id' => 'lavadora'];
$frase2 = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante2, $pA);
ok(strpos($frase2, 'Lucia') !== false, 'contiene nombre');
ok(strpos($frase2, 'lavadora') !== false, 'contiene copy');
ok(strpos($frase2, 'Aunque') === false, 'sin contexto histórico');

// ================================================================
// 9. fraseCausaHumana con historia: sin copy_id (usa fallback)
// ================================================================
echo "\n--- Test 9: fraseCausaHumana con historia + fallback ---\n";
$hablante3 = [
    'residente_id' => $pB,
    'copy_id' => '',
    'clase' => 'voluntad',
    'motivo_tipo' => 'emocional',
];
$frase3 = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante3, $pA);
ok(strpos($frase3, 'Tamara') !== false, 'contiene nombre');
ok(strpos($frase3, 'Aunque hemos salido juntos') !== false, 'contiene contexto');
ok(substr($frase3, -1) === '.', 'termina en punto');

// ================================================================
// 10. fraseCausaHumana sin historial: fallback genérico
// ================================================================
echo "\n--- Test 10: fraseCausaHumana sin historia + fallback ---\n";
$hablante4 = [
    'residente_id' => $pC,
    'copy_id' => '',
    'clase' => 'voluntad',
    'motivo_tipo' => 'emocional',
];
$frase4 = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante4, $pA);
ok(strpos($frase4, 'Lucia') !== false, 'contiene nombre');
ok(strpos($frase4, 'Aunque') === false, 'sin contexto');
ok(strpos($frase4, 'A pesar') === false, 'sin contexto');

// ================================================================
// 11. fraseCausaHumana: ID inexistente → usa ID como nombre
// ================================================================
echo "\n--- Test 11: fraseCausaHumana sin nombre identificado ---\n";
$hablante5 = ['residente_id' => 'per_inexistente', 'copy_id' => 'lavadora'];
$frase5 = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante5, $pA);
ok(strpos($frase5, 'per_inexistente') !== false, 'usa ID como nombre fallback');

// ================================================================
// 12. No dobles puntos ni comas
// ================================================================
echo "\n--- Test 12: sin doble puntuación ---\n";
$hablante6 = ['residente_id' => $pB, 'copy_id' => 'lavadora'];
$frase6 = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante6, $pA);
containsNo($frase6, '..', 'sin doble punto');
containsNo($frase6, ',,', 'sin doble coma');
containsNo($frase6, '. ,', 'sin punto-coma');

// ================================================================
// 13. Copias correctas: mayúscula tras punto
// ================================================================
echo "\n--- Test 13: capitalización correcta ---\n";
// El contexto histórico empieza con mayúscula
$hablante7 = ['residente_id' => $pB, 'copy_id' => 'lavadora'];
$frase7 = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante7, $pA);
$partes = explode('. ', $frase7);
ok(count($partes) >= 2, 'frase tiene dos partes');
if (count($partes) >= 2) {
    $segundaParte = $partes[1];
    ok($segundaParte !== '' && ctype_upper($segundaParte[0]), 'segunda parte empieza con mayúscula');
}

// ================================================================
// 14. lineaOtroDispuesto con historia
// ================================================================
echo "\n--- Test 14: lineaOtroDispuesto ---\n";
$propuesta = [
    'id' => 'prop_1',
    'proponente' => $pA,
    'participantes' => [$pA, $pB, $pC],
    'lugar' => 'lug_cafeteria',
    'hora' => 18,
    'dia' => 5,
    'reacciones' => [
        $pA => ['residente_id' => $pA, 'decision' => 'acepta', 'clase' => 'voluntad'],
        $pB => [
            'residente_id' => $pB,
            'decision' => 'rechaza',
            'clase' => 'voluntad',
            'motivo_tipo' => 'ocupado',
        ],
        $pC => [
            'residente_id' => $pC,
            'decision' => 'acepta',
            'clase' => 'voluntad',
            'motivo_tecnico' => 'voluntad_ok_pero_plan_rechazado',
        ],
    ],
    'estado' => 'rechazada',
];
$linea = CopyRechazoPropuesta::lineaOtroDispuesto($partida, $propuesta);
ok(strpos($linea, 'Lucia') !== false, 'contiene nombre habria_aceptado');

// ================================================================
// 15. lineaOtroDispuesto sin habria_aceptado
// ================================================================
echo "\n--- Test 15: lineaOtroDispuesto sin habria_aceptado ---\n";
$propuesta2 = [
    'id' => 'prop_2',
    'proponente' => $pA,
    'participantes' => [$pA, $pB, $pC],
    'lugar' => 'lug_cafeteria',
    'hora' => 18,
    'dia' => 5,
    'reacciones' => [
        $pA => ['residente_id' => $pA, 'decision' => 'acepta', 'clase' => 'voluntad'],
        $pC => [
            'residente_id' => $pC,
            'decision' => 'rechaza',
            'clase' => 'voluntad',
            'motivo_tipo' => 'ocupado',
        ],
        $pB => [
            'residente_id' => $pB,
            'decision' => 'acepta',
            'clase' => 'voluntad',
            'motivo_tecnico' => 'voluntad_ok_pero_plan_rechazado',
        ],
    ],
    'estado' => 'rechazada',
];
$linea2 = CopyRechazoPropuesta::lineaOtroDispuesto($partida, $propuesta2);
ok(strpos($linea2, 'Tamara') !== false, 'contiene nombre habria_aceptado');

// ================================================================
// 16. No contradictorios: copy_id "lavadora" + historia → coherente
// ================================================================
echo "\n--- Test 16: sin contradicciones ---\n";
// "Tengo que poner la lavadora" + "Aunque hemos salido juntos" = coherente
$hablante8 = ['residente_id' => $pB, 'copy_id' => 'lavadora'];
$frase8 = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante8, $pA);
ok(strpos($frase8, 'lavadora') !== false, 'copy presente');
ok(strpos($frase8, 'Aunque hemos salido juntos') !== false, 'historia presente');
ok(strpos($frase8, 'A pesar de que son pareja') === false, 'no contradice con pareja');

// ================================================================
// 17. fraseCausaHumana: siempre termina en punto
// ================================================================
echo "\n--- Test 17: siempre termina en punto ---\n";
foreach (['lavadora', 'pintar_unias', 'hoy_no_me_da_la_vida', 'el_gato_tiene_que_comer', 'se_me_hace_tarde', 'ducha', 'serie'] as $cid) {
    $h = ['residente_id' => $pB, 'copy_id' => $cid];
    $f = CopyRechazoPropuesta::fraseCausaHumana($partida, $h, $pA);
    ok($f === '' || substr($f, -1) === '.', "copy_id $cid: termina en punto");
}

echo $fail === 0 ? "\nfase2d_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
