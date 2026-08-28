<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CotilleoCategoria;
use AquiHayTema\Engine\CotilleoNarrativo;
use AquiHayTema\Engine\CotilleoPatronCadencia;
use AquiHayTema\Engine\CopyCoincidenciaPatron;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\RelacionBitacora;

$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

function sortedPar(array $ids): array
{
    sort($ids);
    return $ids;
}

function hito(string $tipo, array $par, int $dia): array
{
    return ['tipo' => $tipo, 'par' => sortedPar($par), 'fecha' => ['dia' => $dia]];
}

$pA = 'per_hugo';
$pB = 'per_tamara';
$pC = 'per_celestine';

$basePartida = [
    'meta' => ['seed' => 'test-fase5'],
    'reloj' => ['dia_pueblo' => 10, 'hora_actual' => 20],
    'features' => ['diario_enabled' => true],
    'residentes' => [
        $pA => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => 'Hugo', 'genero' => 'hombre'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(EstadoEmocional::NEUTRO),
            ],
        ],
        $pB => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => 'Tamara', 'genero' => 'mujer'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(EstadoEmocional::NEUTRO),
            ],
        ],
        $pC => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => 'Celestine', 'genero' => 'mujer'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(EstadoEmocional::NEUTRO),
            ],
        ],
    ],
    'bitacora_relaciones' => [],
    'encuentros' => [],
    'buzon' => [],
    'historial_coincidencias' => [],
];

// ================================================================
// 1. esInteresNarrativo — par sin historia = bajo interés
// ================================================================
echo "--- 1: esInteresNarrativo sin historia ---\n";
$partida1 = $basePartida;
$interes1 = CotilleoNarrativo::esInteresNarrativo($partida1, [$pA, $pC]);
ok(is_array($interes1), 'es array');
ok($interes1['interes'] === false, 'sin historia: sin interés');
ok($interes1['score'] <= 2, 'sin historia: score bajo');
ok($interes1['familia'] === 'desconocidos', 'familia desconocidos');
ok($interes1['se_conocen'] === false, 'no se conocen');
ok($interes1['romance'] === false, 'sin romance');
ok($interes1['hito_reciente'] === false, 'sin hito reciente');
ok($interes1['emocion_reciente'] === false, 'sin emoción');

// ================================================================
// 2. esInteresNarrativo — par que se conoce = interés medio
// ================================================================
echo "\n--- 2: esInteresNarrativo conociéndose ---\n";
$partida2 = $basePartida;
$partida2['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
];
$interes2 = CotilleoNarrativo::esInteresNarrativo($partida2, [$pA, $pB]);
ok($interes2['interes'] === true, 'conocidos: sí interés');
ok($interes2['familia'] === 'conocidos', 'familia conocidos');
ok($interes2['se_conocen'] === true, 'se conocen');

// ================================================================
// 3. esInteresNarrativo — pareja = interés alto
// ================================================================
echo "\n--- 3: esInteresNarrativo pareja ---\n";
$partida3 = $basePartida;
$partida3['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
    hito(RelacionBitacora::INICIO_PAREJA, [$pA, $pB], 5),
];
$interes3 = CotilleoNarrativo::esInteresNarrativo($partida3, [$pA, $pB]);
ok($interes3['interes'] === true, 'pareja: sí interés');
ok($interes3['score'] >= 5, 'pareja: score alto');
ok($interes3['familia'] === 'pareja', 'familia pareja');

// ================================================================
// 4. esInteresNarrativo — hito reciente = interés alto
// ================================================================
echo "\n--- 4: esInteresNarrativo hito reciente ---\n";
$partida4 = $basePartida;
$partida4['reloj']['dia_pueblo'] = 10;
$partida4['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
    hito(RelacionBitacora::PRIMERA_CITA, [$pA, $pB], 9),
];
$interes4 = CotilleoNarrativo::esInteresNarrativo($partida4, [$pA, $pB]);
ok($interes4['interes'] === true, 'hito reciente: sí interés');
ok($interes4['hito_reciente'] === true, 'hito reciente detectado');

// ================================================================
// 5. esInteresNarrativo — emoción activa con alguien conocido = interés
// ================================================================
echo "\n--- 5: esInteresNarrativo con emoción + conocidos ---\n";
$partida5 = $basePartida;
$partida5['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pC], 1),
];
$partida5['residentes'][$pA]['runtime']['estado_emocional'] = EstadoEmocional::estructura(EstadoEmocional::TRISTE);
$interes5 = CotilleoNarrativo::esInteresNarrativo($partida5, [$pA, $pC]);
ok($interes5['emocion_reciente'] === true, 'emoción detectada');
ok($interes5['interes'] === true, 'conocidos + emoción: sí interés');

// ================================================================
// 6. esInteresNarrativo — hito viejo (>5 días) no cuenta
// ================================================================
echo "\n--- 6: esInteresNarrativo hito viejo ---\n";
$partida6 = $basePartida;
$partida6['reloj']['dia_pueblo'] = 15;
$partida6['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
    hito(RelacionBitacora::PRIMERA_CITA, [$pA, $pB], 5),
];
$interes6 = CotilleoNarrativo::esInteresNarrativo($partida6, [$pA, $pB]);
ok($interes6['hito_reciente'] === false, 'hito viejo: no reciente');

// ================================================================
// 7. esInteresNarrativo — ex_reconexion = interés alto
// ================================================================
echo "\n--- 7: esInteresNarrativo ex_reconexion ---\n";
$partida7 = $basePartida;
$partida7['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
    hito(RelacionBitacora::INICIO_PAREJA, [$pA, $pB], 3),
    hito(RelacionBitacora::RUPTURA, [$pA, $pB], 6),
    hito(RelacionBitacora::RECONCILIACION, [$pA, $pB], 8),
];
$interes7 = CotilleoNarrativo::esInteresNarrativo($partida7, [$pA, $pB]);
ok($interes7['interes'] === true, 'ex_reconexion: sí interés');
ok($interes7['familia'] === 'ex_reconexion', 'familia ex_reconexion');

// ================================================================
// 8. metaPublicacion incluye interes_narrativo
// ================================================================
echo "\n--- 8: metaPublicacion con interes_narrativo ---\n";
$partida8 = $basePartida;
$partida8['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
];
$partida8['historial_coincidencias'] = [
    ['dia' => 8, 'lugar_id' => 'lug_bar', 'residentes' => [$pA, $pB]],
    ['dia' => 9, 'lugar_id' => 'lug_bar', 'residentes' => [$pA, $pB]],
    ['dia' => 10, 'lugar_id' => 'lug_bar', 'residentes' => [$pA, $pB]],
];
$meta = CotilleoPatronCadencia::metaPublicacion($partida8, [$pA, $pB], 'lug_bar', 10);
ok(isset($meta['interes_narrativo']), 'metaPublicacion tiene interes_narrativo');
ok($meta['interes_narrativo']['interes'] === true, 'interes_narrativo: true para conocidos con patrón');
ok(is_int($meta['interes_narrativo']['score']), 'score es int');

// ================================================================
// 9. CopyCoincidenciaPatron: contexto_hint para conocidos
// ================================================================
echo "\n--- 9: copy contexto_hint conocidos ---\n";
$partida9 = $basePartida;
$partida9['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
];
$copy9 = CopyCoincidenciaPatron::vista($partida9, [$pA, $pB], 'lug_bar');
ok(is_string($copy9['contexto_hint']), 'contexto_hint es string');
ok($copy9['contexto_hint'] !== '', 'conocidos: contexto_hint no vacío');
ok($copy9['contexto_hint'] === 'conocidos', 'conocidos: hint correcto');
ok($copy9['texto'] !== '', 'texto no vacío');
ok(!str_contains($copy9['texto'], '{'), 'sin tokens sin resolver');

// ================================================================
// 10. CopyCoincidenciaPatron: contexto_hint para pareja
// ================================================================
echo "\n--- 10: copy contexto_hint pareja ---\n";
$partida10 = $basePartida;
$partida10['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
    hito(RelacionBitacora::INICIO_PAREJA, [$pA, $pB], 5),
];
$copy10 = CopyCoincidenciaPatron::vista($partida10, [$pA, $pB], 'lug_bar');
ok($copy10['contexto_hint'] === 'pareja', 'pareja: hint correcto');
ok($copy10['destacado'] === true, 'pareja: destacado');

// ================================================================
// 11. CopyCoincidenciaPatron: contexto_hint ex_reconexion
// ================================================================
echo "\n--- 11: copy contexto_hint ex_reconexion ---\n";
$partida11 = $basePartida;
$partida11['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
    hito(RelacionBitacora::INICIO_PAREJA, [$pA, $pB], 3),
    hito(RelacionBitacora::RUPTURA, [$pA, $pB], 6),
    hito(RelacionBitacora::RECONCILIACION, [$pA, $pB], 8),
];
$copy11 = CopyCoincidenciaPatron::vista($partida11, [$pA, $pB], 'lug_bar');
ok($copy11['contexto_hint'] === 'ex', 'ex_reconexion: hint ex');
ok($copy11['destacado'] === true, 'ex_reconexion: destacado');
ok(strlen($copy11['texto']) > 10, 'ex_reconexion: tiene texto');

// ================================================================
// 12. CopyCoincidenciaPatron: tensión
// ================================================================
echo "\n--- 12: copy con tensión ---\n";
$partida12 = $basePartida;
$partida12['reloj']['dia_pueblo'] = 10;
$partida12['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
    hito(RelacionBitacora::DISCUSION_FUERTE, [$pA, $pB], 9),
];
$copy12 = CopyCoincidenciaPatron::vista($partida12, [$pA, $pB], 'lug_bar');
ok($copy12['contexto_hint'] === 'tension', 'tensión: hint tension');

// ================================================================
// 13. CopyCoincidenciaPatron: persona desconocida sin interés
// ================================================================
echo "\n--- 13: copy desconocidos ---\n";
$partida13 = $basePartida;
$copy13 = CopyCoincidenciaPatron::vista($partida13, [$pA, $pC], 'lug_bar');
ok($copy13['contexto_hint'] === '' || $copy13['contexto_hint'] === 'conocidos', 'desconocidos: hint vacío o conocidos');
ok($copy13['texto'] !== '', 'desconocidos: tiene texto');

// ================================================================
// 14. VistaPatron incluye interes_narrativo
// ================================================================
echo "\n--- 14: vistaPatron con interes_narrativo ---\n";
$partida14 = $basePartida;
$partida14['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
];
$vista14 = CotilleoNarrativo::vistaPatron($partida14, [$pA, $pB], 'lug_bar');
ok(isset($vista14['interes_narrativo']), 'vistaPatron: tiene interes_narrativo');
ok($vista14['interes_narrativo']['interes'] === true, 'vistaPatron: interes true para conocidos');
ok(isset($vista14['contexto_hint']), 'vistaPatron: tiene contexto_hint');

// ================================================================
// 15. coincidenciaDigna: sin interés narrativo sigue pasando (filtro en mensajeCoincidencia)
// ================================================================
echo "\n--- 15: coincidenciaDigna sin interés ---\n";
$partida15 = $basePartida;
for ($d = 1; $d <= 5; $d++) {
    $partida15['historial_coincidencias'][] = [
        'dia' => $d, 'lugar_id' => 'lug_bar', 'residentes' => [$pA, $pC],
    ];
}
$partida15['reloj']['dia_pueblo'] = 5;
$env15 = ['dia' => 5, 'lugar_id' => 'lug_bar', 'residentes' => [$pA, $pC]];
$ok15 = CotilleoNarrativo::coincidenciaDigna($partida15, $env15);
ok($ok15 === true, 'desconocidos con patrón: coincidenciaDigna pasa (cadencia OK)');

// ================================================================
// 15b. mensajeCoincidencia: sin interés narrativo = null
// ================================================================
echo "\n--- 15b: mensajeCoincidencia sin interés ---\n";
$msg15 = CotilleoNarrativo::mensajeCoincidencia($partida15, $env15);
ok($msg15 === null, 'desconocidos sin interés: mensajeCoincidencia null');

// ================================================================
// 16. coincidenciaDigna + mensajeCoincidencia: con interés = produce mensaje
// ================================================================
echo "\n--- 16: coincidenciaDigna + mensajeCoincidencia con interés ---\n";
$partida16 = $basePartida;
$partida16['bitacora_relaciones'] = [
    hito(RelacionBitacora::SE_CONOCIERON, [$pA, $pB], 1),
];
for ($d = 1; $d <= 5; $d++) {
    $partida16['historial_coincidencias'][] = [
        'dia' => $d, 'lugar_id' => 'lug_bar', 'residentes' => [$pA, $pB],
    ];
}
$partida16['reloj']['dia_pueblo'] = 5;
$env16 = ['dia' => 5, 'lugar_id' => 'lug_bar', 'residentes' => [$pA, $pB]];
$ok16 = CotilleoNarrativo::coincidenciaDigna($partida16, $env16);
ok($ok16 === true, 'conocidos con patrón: coincidenciaDigna pasa');
$msg16 = CotilleoNarrativo::mensajeCoincidencia($partida16, $env16);
ok($msg16 !== null, 'conocidos con patrón: mensajeCoincidencia produce mensaje');
ok(is_string($msg16['texto'] ?? ''), 'conocidos: tiene texto');

// ================================================================
// 17. vilina variable eliminated (no paralysis)
// ================================================================
echo "\n--- 17: esInteresNarrativo sin parálisis ---\n";
$interes17a = CotilleoNarrativo::esInteresNarrativo($partida1, [$pA, $pC]);
$interes17b = CotilleoNarrativo::esInteresNarrativo($partida1, [$pA, $pC]);
ok($interes17a === $interes17b, 'misma llamada: mismo resultado (determinista)');

echo $fail === 0 ? "\nfase5_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
