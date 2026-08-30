<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EncuentroResultadoVista;
use AquiHayTema\Engine\EstadoEmocional;

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

function contains(string $haystack, string $needle): bool
{
    return stripos($haystack, $needle) !== false;
}

$david = 'per_p192';
$paco  = 'per_p149';
$otro  = 'per_oter';

$nombreDavid = 'David';
$nombrePaco  = 'Paco';

// ================================================================
// CASO 1: David alegre por encuentro A, encuentro B normal con Paco.
//   emocionesPublicas de B NO debe incluir a David.
//   Si David aparece en emociones de B, la explicación debe referenciar A, NO B.
// ================================================================
echo "--- CASO 1: David alegre por A, B normal → no aparece en emociones de B ---\n";

$partida1 = [
    'meta' => ['seed' => 'coherencia-causal-1'],
    'reloj' => ['dia_pueblo' => 3, 'hora_actual' => 18],
    'residentes' => [
        $david => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => $nombreDavid, 'genero' => 'hombre'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(
                    EstadoEmocional::ALEGRE,
                    null,
                    'encuentro',
                    ['dia' => 2, 'hora' => 12],
                    null,
                    ['encuentro_id' => 'enc_A', 'resultado_experiencia' => 'muy_bien']
                ),
            ],
        ],
        $paco => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => $nombrePaco, 'genero' => 'hombre'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(
                    EstadoEmocional::TRISTE,
                    null,
                    'encuentro',
                    ['dia' => 3, 'hora' => 18],
                    null,
                    ['encuentro_id' => 'enc_B', 'resultado_experiencia' => 'muy_mal']
                ),
            ],
        ],
    ],
    'encuentros' => [
        [
            'id' => 'enc_A',
            'participantes' => [$david, $otro],
            'tipo' => 'cita',
            'dia' => 2,
            'hora' => 12,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $david => ['resultado' => 'muy_bien'],
                    $otro  => ['resultado' => 'bien'],
                ],
                'emociones' => [
                    ['residente_id' => $david, 'estado' => 'alegre', 'antes' => 'neutro',
                     'resultado_experiencia' => 'muy_bien', 'motivo' => 'encuentro', 'encuentro_id' => 'enc_A'],
                ],
            ],
        ],
        [
            'id' => 'enc_B',
            'participantes' => [$david, $paco],
            'tipo' => 'quedar',
            'dia' => 3,
            'hora' => 18,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $david => ['resultado' => 'normal'],
                    $paco  => ['resultado' => 'muy_mal'],
                ],
                'emociones' => [
                    ['residente_id' => $paco, 'estado' => 'triste', 'antes' => 'neutro',
                     'resultado_experiencia' => 'muy_mal', 'motivo' => 'encuentro', 'encuentro_id' => 'enc_B'],
                ],
            ],
        ],
    ],
    'bitacora_relaciones' => [],
    'memoria_eventos' => [],
];

// Encuentro B emotions: solo Paco, no David
$resB1 = $partida1['encuentros'][1]['resultado'];
$emoB1 = EncuentroResultadoVista::de($partida1, $partida1['encuentros'][1]);
$emocionesB1 = $emoB1['resultado']['emociones'] ?? [];

$emoDavidB1 = array_filter($emocionesB1, fn($e) => ($e['residente'] ?? '') === $david);
$emoPacoB1  = array_filter($emocionesB1, fn($e) => ($e['residente'] ?? '') === $paco);

ok(count($emoDavidB1) === 0,
    'CASO1: David NO aparece en emocionesPublicas de B (su emoción no cambió)');
ok(count($emoPacoB1) === 1,
    'CASO1: Paco SÍ aparece en emocionesPublicas de B');

// La explicación de Paco en B debe referenciar enc_B
$pacoExpB1 = reset($emoPacoB1)['texto'] ?? '';
ok(contains($pacoExpB1, $nombrePaco),
    'CASO1: explicación de Paco menciona su nombre');
ok(contains($pacoExpB1, 'mal') || contains($pacoExpB1, 'fatal') || contains($pacoExpB1, 'polvo') || contains($pacoExpB1, 'sentado'),
    'CASO1: explicación de Paco refleja resultado negativo');

// ================================================================
// CASO 2: David alegre por A, B genera cambio emotional para David.
//   emocionesPublicas de B SÍ debe incluir a David.
//   Explicación debe referenciar B, no A.
// ================================================================
echo "\n--- CASO 2: David alegre por A, B le pone triste → explicación refencia B ---\n";

$partida2 = [
    'meta' => ['seed' => 'coherencia-causal-2'],
    'reloj' => ['dia_pueblo' => 3, 'hora_actual' => 18],
    'residentes' => [
        $david => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => $nombreDavid, 'genero' => 'hombre'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(
                    EstadoEmocional::TRISTE,
                    null,
                    'encuentro',
                    ['dia' => 3, 'hora' => 18],
                    null,
                    ['encuentro_id' => 'enc_B2', 'resultado_experiencia' => 'muy_mal']
                ),
            ],
        ],
        $paco => [
            'presencia' => 'residente',
            'identidad_publica' => ['nombre' => $nombrePaco, 'genero' => 'hombre'],
            'runtime' => [
                'estado_emocional' => EstadoEmocional::estructura(
                    EstadoEmocional::NEUTRO,
                    null,
                    'inicial',
                    ['dia' => 1, 'hora' => 8],
                    null,
                    []
                ),
            ],
        ],
    ],
    'encuentros' => [
        [
            'id' => 'enc_A2',
            'participantes' => [$david, $otro],
            'tipo' => 'cita',
            'dia' => 2,
            'hora' => 12,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $david => ['resultado' => 'muy_bien'],
                    $otro  => ['resultado' => 'bien'],
                ],
                'emociones' => [
                    ['residente_id' => $david, 'estado' => 'alegre', 'antes' => 'neutro',
                     'resultado_experiencia' => 'muy_bien', 'motivo' => 'encuentro', 'encuentro_id' => 'enc_A2'],
                ],
            ],
        ],
        [
            'id' => 'enc_B2',
            'participantes' => [$david, $paco],
            'tipo' => 'quedar',
            'dia' => 3,
            'hora' => 18,
            'estado' => 'terminado',
            'resultado' => [
                'por_participante' => [
                    $david => ['resultado' => 'muy_mal'],
                    $paco  => ['resultado' => 'bien'],
                ],
                'emociones' => [
                    ['residente_id' => $david, 'estado' => 'triste', 'antes' => 'alegre',
                     'resultado_experiencia' => 'muy_mal', 'motivo' => 'encuentro', 'encuentro_id' => 'enc_B2'],
                    ['residente_id' => $paco, 'estado' => 'alegre', 'antes' => 'neutro',
                     'resultado_experiencia' => 'bien', 'motivo' => 'encuentro', 'encuentro_id' => 'enc_B2'],
                ],
            ],
        ],
    ],
    'bitacora_relaciones' => [],
    'memoria_eventos' => [],
];

$emoB2 = EncuentroResultadoVista::de($partida2, $partida2['encuentros'][1]);
$emocionesB2 = $emoB2['resultado']['emociones'] ?? [];

$emoDavidB2 = array_values(array_filter($emocionesB2, fn($e) => ($e['residente'] ?? '') === $david));
ok(count($emoDavidB2) === 1,
    'CASO2: David SÍ aparece en emocionesPublicas de B (su emoción cambió a triste)');

$davidExpB2 = $emoDavidB2[0]['texto'] ?? '';
echo "  David texto en B: $davidExpB2\n";
ok(contains($davidExpB2, $nombrePaco),
    'CASO2: explicación de David en B menciona a Paco (encuentro causal correcto)');
ok(contains($davidExpB2, 'mal') || contains($davidExpB2, 'fatal') || contains($davidExpB2, 'polvo') || contains($davidExpB2, 'sentado'),
    'CASO2: explicación de David refleja resultado negativo de B');

// ================================================================
// CASO 3: David alegre por A, ficha modal referencia A (no B).
// ================================================================
echo "\n--- CASO 3: ficha modal David referencia A, no B ---\n";

$partida3 = $partida1; // Reutiliza caso 1
$cal = CalibracionConfig::load($root);

$estadoDavid = $partida3['residentes'][$david]['runtime']['estado_emocional'];
$modal = EmocionalNarrativa::vistaModalAnimo($partida3, $david, $estadoDavid, $cal);

ok(is_array($modal), 'CASO3: vistaModalAnimo devuelve payload');
ok(($modal['estado_id'] ?? '') === EstadoEmocional::ALEGRE, 'CASO3: estado_id es alegre');

$explicacionModal = $modal['explicacion'] ?? '';
echo "  Ficha modal explicación: $explicacionModal\n";

// La explicación NO debe mencionar a Paco (enc_B)
ok(!contains($explicacionModal, $nombrePaco),
    'CASO3: ficha modal NO menciona a Paco (encuentro B no es la causa)');

// ================================================================
// CASO 4: Contexto completo — emoción + narrativa + cotilleo coherentes.
// ================================================================
echo "\n--- CASO 4: Cotilleo de B coherente con resultado global ---\n";

// Paco triste por B → cotilleo de B debe reflejar resultado negativo global
$emoPacoB1 = array_values(array_filter($emocionesB1, fn($e) => ($e['residente'] ?? '') === $paco));
$pacoExpB1 = $emoPacoB1[0]['texto'] ?? '';
ok(contains($pacoExpB1, $nombrePaco),
    'CASO4: cotilleo emociones de B menciona Paco');

// Verificar que emociones de A solo tiene David
$emoA1 = EncuentroResultadoVista::de($partida1, $partida1['encuentros'][0]);
$emocionesA1 = $emoA1['resultado']['emociones'] ?? [];
$emoDavidA1 = array_values(array_filter($emocionesA1, fn($e) => ($e['residente'] ?? '') === $david));
ok(count($emoDavidA1) === 1,
    'CASO4: emocionesPublicas de A incluye David');

$davidExpA1 = $emoDavidA1[0]['texto'] ?? '';
echo "  David texto en A: $davidExpA1\n";
ok(contains($davidExpA1, 'animado') || contains($davidExpA1, 'anim') || contains($davidExpA1, 'bien'),
    'CASO4: explicación de David en A refleja resultado positivo');

echo "\ncoherencia_causal_encuentro_test: " . ($fail === 0 ? 'TODO OK' : "FAIL ($fail)") . "\n";
exit($fail > 0 ? 1 : 0);
