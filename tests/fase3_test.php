<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\EncuentroResultadoVista;
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

$partida = [
    'meta' => ['seed' => 'test-fase3'],
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 18],
    'residentes' => [
        $pA => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => 'Hugo', 'genero' => 'hombre'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(EstadoEmocional::TRISTE, null, 'encuentro', ['dia' => 5], null, ['encuentro_id' => 'enc_1', 'resultado_experiencia' => 'mal']),
            ],
        ],
        $pB => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => 'Tamara', 'genero' => 'mujer'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(EstadoEmocional::ALEGRE, null, 'cumple_felicidad', ['dia' => 5]),
            ],
        ],
        $pC => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => 'Lucia', 'genero' => 'mujer'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(EstadoEmocional::ENFADADO, null, 'rechazo_repetido', ['dia' => 5], null, ['hacia' => $pA]),
            ],
        ],
    ],
    'bitacora_relaciones' => [
        ['tipo' => RelacionBitacora::SE_CONOCIERON, 'participantes' => [$pA, $pB], 'fecha' => ['dia' => 1], 'meta' => []],
        ['tipo' => RelacionBitacora::PRIMERA_CITA, 'participantes' => [$pA, $pB], 'fecha' => ['dia' => 3], 'meta' => []],
    ],
    'encuentros' => [
        [
            'id' => 'enc_1',
            'participantes' => [$pA, $pB],
            'tipo' => 'cita',
            'dia' => 5,
            'hora' => 18,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $pA => ['resultado' => 'mal', 'carga' => -0.3],
                    $pB => ['resultado' => 'bien', 'carga' => 0.2],
                ],
            ],
        ],
    ],
    'memoria_eventos' => [],
];

// ================================================================
// 3A: mensajitoParaOrigen — orígenes cubiertos
// ================================================================
echo "--- 3A: mensajitoParaOrigen ---\n";

$msgHobby = EmocionalNarrativa::mensajitoParaOrigen($partida, $pA, 'hobby_recuperacion');
ok($msgHobby !== null, 'hobby_recuperacion: produce texto');
ok(strpos($msgHobby, 'rato') !== false || strpos($msgHobby, 'hobby') !== false || strpos($msgHobby, 'lo mío') !== false, 'hobby_recuperacion: menciona actividad personal');

$msgCumple = EmocionalNarrativa::mensajitoParaOrigen($partida, $pB, 'cumple_felicidad');
ok($msgCumple !== null, 'cumple_felicidad: produce texto');

$msgConsejo = EmocionalNarrativa::mensajitoParaOrigen($partida, $pA, 'consejo_celestine');
ok($msgConsejo !== null, 'consejo_celestine: produce texto');

$msgEnc = EmocionalNarrativa::mensajitoParaOrigen($partida, $pA, 'encuentro', ['resultado_experiencia' => 'mal']);
ok($msgEnc !== null, 'encuentro mal: produce texto');
ok(strpos($msgEnc, 'bajón') !== false || strpos($msgEnc, 'mal') !== false, 'encuentro mal: menciona负面');

$msgEncBien = EmocionalNarrativa::mensajitoParaOrigen($partida, $pA, 'encuentro', ['resultado_experiencia' => 'bien']);
ok($msgEncBien !== null, 'encuentro bien: produce texto');

$msgEncNeutro = EmocionalNarrativa::mensajitoParaOrigen($partida, $pA, 'encuentro', ['resultado_experiencia' => 'neutro']);
ok($msgEncNeutro === null, 'encuentro neutro: null');

$msgTrabajo = EmocionalNarrativa::mensajitoParaOrigen($partida, $pA, 'perder_trabajo');
ok($msgTrabajo !== null, 'perder_trabajo: produce texto');

$msgRechazo = EmocionalNarrativa::mensajitoParaOrigen($partida, $pA, 'rechazo_repetido');
ok($msgRechazo !== null, 'rechazo_repetido: produce texto');

$msgInvent = EmocionalNarrativa::mensajitoParaOrigen($partida, $pA, 'inventado');
ok($msgInvent === null, 'inventado: null');

// ================================================================
// 3B: emocionesPublicas muestra causa real
// ================================================================
echo "\n--- 3B: emocionesPublicas ---\n";

$resConEmociones = [
    'emociones' => [
        ['residente' => $pA, 'estado' => 'triste'],
    ],
];

// Acceder al método privado via reflejo
$ref = new ReflectionClass(EncuentroResultadoVista::class);
$meth = $ref->getMethod('emocionesPublicas');
$meth->setAccessible(true);

$emociones = $meth->invoke(null, $partida, $resConEmociones);
ok(count($emociones) === 1, 'emocionesPublicas: 1 resultado');
ok(strpos($emociones[0]['texto'], 'Hugo') !== false, 'contiene nombre');
ok(strpos($emociones[0]['texto'], 'ha cambiado de humor') === false, 'no es genérico');
ok(strpos($emociones[0]['texto'], 'encuentro') !== false || strpos($emociones[0]['texto'], 'torció') !== false, 'menciona causa real');

// Sin residente en la partida → fallback genérico
$partidaSinRes = $partida;
unset($partidaSinRes['residentes'][$pA]);
$emocionesSinRes = $meth->invoke(null, $partidaSinRes, $resConEmociones);
ok(strpos($emocionesSinRes[0]['texto'], 'ha cambiado de humor') !== false, 'sin residente: fallback genérico');

// ================================================================
// 3C: HistorialPar integrado en explicaciones
// ================================================================
echo "\n--- 3C: HistorialPar en explicaciones ---\n";

$expA = EmocionalNarrativa::explicacionCompleta($partida, $pA, $partida['residentes'][$pA]['runtime']['estado_emocional']);
ok($expA !== null, 'explicacionCompleta: produce resultado');
ok(strpos($expA['explicacion'], 'Hugo') === false || strpos($expA['explicacion'], 'Tamara') !== false, 'menciona al otro');
ok(strpos($expA['explicacion'], 'encuentro') !== false || strpos($expA['explicacion'], 'torció') !== false || strpos($expA['explicacion'], 'salido') !== false, 'menciona encuentro');

// Sin HistorialPar (par sin historia)
$partidaSinHistoria = $partida;
$partidaSinHistoria['bitacora_relaciones'] = [];
$partidaSinHistoria['encuentros'] = [
    [
        'id' => 'enc_2',
        'participantes' => [$pA, $pC],
        'tipo' => 'cafe',
        'dia' => 5,
        'hora' => 10,
        'estado' => 'terminado',
        'resultado' => [
            'por_participante' => [
                $pA => ['resultado' => 'mal', 'carga' => -0.2],
                $pC => ['resultado' => 'bien', 'carga' => 0.1],
            ],
        ],
    ],
];
$estadoSinHistoria = EstadoEmocional::estructura(EstadoEmocional::TRISTE, null, 'encuentro', ['dia' => 5], null, ['encuentro_id' => 'enc_2', 'resultado_experiencia' => 'mal']);
$expB = EmocionalNarrativa::explicacionCompleta($partidaSinHistoria, $pA, $estadoSinHistoria);
ok($expB !== null, 'sin historia: produce resultado');
ok(strpos($expB['explicacion'], 'Lucia') !== false, 'sin historia: menciona otro');

// rechazo_repetido con HistorialPar
$expC = EmocionalNarrativa::explicacionCompleta($partida, $pC, $partida['residentes'][$pC]['runtime']['estado_emocional']);
ok($expC !== null, 'rechazo_repetido: produce resultado');
ok(strpos($expC['explicacion'], 'Hugo') !== false, 'rechazo_repetido: menciona objetivo');

// ================================================================
// 3B:或ígenes cubiertos en pistaFicha
// ================================================================
echo "\n--- 3A: pistaFicha cubre orígenes ---\n";

$pistaHobby = EmocionalNarrativa::pistaFicha(['id' => 'triste', 'origen' => 'hobby_recuperacion']);
ok($pistaHobby !== null, 'pistaFicha hobby_recuperacion: produce texto');

$pistaCumple = EmocionalNarrativa::pistaFicha(['id' => 'alegre', 'origen' => 'cumple_felicidad']);
ok($pistaCumple !== null, 'pistaFicha cumple_felicidad: produce texto');

$pistaConsejo = EmocionalNarrativa::pistaFicha(['id' => 'alegre', 'origen' => 'consejo_celestine']);
ok($pistaConsejo !== null, 'pistaFicha consejo_celestine: produce texto');

$pistaEnc = EmocionalNarrativa::pistaFicha(['id' => 'triste', 'origen' => 'encuentro']);
ok($pistaEnc !== null, 'pistaFicha encuentro: produce texto');

// ================================================================
// 3B: cotilleoParaOrigen cubre orígenes
// ================================================================
echo "\n--- 3A: cotilleoParaOrigen cubre orígenes ---\n";

$cotCumple = EmocionalNarrativa::cotilleoParaOrigen($partida, $pB, 'cumple_felicidad');
ok($cotCumple !== null, 'cotilleo cumple_felicidad: produce texto');

$cotConsejo = EmocionalNarrativa::cotilleoParaOrigen($partida, $pA, 'consejo_celestine');
ok($cotConsejo !== null, 'cotilleo consejo_celestine: produce texto');

$cotEnc = EmocionalNarrativa::cotilleoParaOrigen($partida, $pA, 'encuentro', ['resultado_experiencia' => 'muy_mal']);
ok($cotEnc !== null, 'cotilleo encuentro muy_mal: produce texto');

echo $fail === 0 ? "\nfase3_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
