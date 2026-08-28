<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioHitoEngine;
use AquiHayTema\Engine\DiarioNarrativaBridge;
use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EstadoEmocional;
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

$partida = [
    'meta' => ['seed' => 'test-fase4'],
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 18],
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
    ],
    'bitacora_relaciones' => [
        ['tipo' => RelacionBitacora::SE_CONOCIERON, 'participantes' => [$pA, $pB], 'fecha' => ['dia' => 1], 'meta' => []],
    ],
    'encuentros' => [],
    'diario' => [],
    'memoria_eventos' => [],
];

// ================================================================
// 1. DiarioHitoEngine existe y es funcional
// ================================================================
echo "--- 1: DiarioHitoEngine ---\n";
ok(class_exists(DiarioHitoEngine::class), 'DiarioHitoEngine: existe');

$hito = [
    'tipo' => RelacionBitacora::PRIMERA_CITA,
    'participantes' => [$pA, $pB],
    'fecha' => ['dia' => 3],
    'meta' => [],
];
$entry = DiarioHitoEngine::alHito($partida, $hito);
ok($entry !== null, 'alHito: produce entrada');
ok(is_array($entry), 'alHito: es array');
ok(($entry['tipo'] ?? '') !== '', 'alHito: tiene tipo');
ok(($entry['texto'] ?? '') !== '', 'alHito: tiene texto');
ok(in_array($pA, $entry['actores'] ?? []), 'alHito: contiene actor A');
ok(in_array($pB, $entry['actores'] ?? []), 'alHito: contiene actor B');

// ================================================================
// 2. DiarioNarrativaBridge::desdeEmocion — produce entrada
// ================================================================
echo "\n--- 2: desdeEmocion produce entrada ---\n";
$estadoTriste = EstadoEmocional::estructura(
    EstadoEmocional::TRISTE,
    null,
    'encuentro',
    ['dia' => 5],
    null,
    ['encuentro_id' => 'enc_test', 'resultado_experiencia' => 'mal']
);
$partida2 = $partida;
$partida2['encuentros'] = [
    ['id' => 'enc_test', 'participantes' => [$pA, $pB], 'tipo' => 'cita', 'dia' => 5, 'hora' => 18, 'estado' => 'terminado',
     'resultado' => ['por_participante' => [$pA => ['resultado' => 'mal', 'carga' => -0.3], $pB => ['resultado' => 'bien', 'carga' => 0.2]]]],
];

$entrada = DiarioNarrativaBridge::desdeEmocion($partida2, $pA, $estadoTriste);
ok($entrada !== null, 'desdeEmocion: produce entrada');
ok(is_array($entrada), 'desdeEmocion: es array');
ok(($entrada['texto'] ?? '') !== '', 'desdeEmocion: tiene texto');
ok(($entrada['tipo'] ?? '') === 'estado_emocional', 'desdeEmocion: tipo correcto');
ok(in_array($pA, $entrada['actores'] ?? []), 'desdeEmocion: contiene actor');
ok(($entrada['origen']['tipo_evento'] ?? '') === 'estado_emocional', 'desdeEmocion: origen tipo_evento correcto');

// ================================================================
// 3. desdeEmocion: neutro → null
// ================================================================
echo "\n--- 3: desdeEmocion neutro ---\n";
$estadoNeutro = EstadoEmocional::estructura(EstadoEmocional::NEUTRO);
$entradaNeutro = DiarioNarrativaBridge::desdeEmocion($partida2, $pA, $estadoNeutro);
ok($entradaNeutro === null, 'neutro: null');

// ================================================================
// 4. desdeEmocion: deduplicación
// ================================================================
echo "\n--- 4: desdeEmocion deduplicación ---\n";
$entrada2 = DiarioNarrativaBridge::desdeEmocion($partida2, $pA, $estadoTriste);
ok($entrada2 !== null, 'deduplicación: retorna existente');
ok(($entrada2['id'] ?? '') === ($entrada['id'] ?? ''), 'deduplicación: mismo ID');

// ================================================================
// 5. desdeEmocion: diario_enabled=false → null
// ================================================================
echo "\n--- 5: desdeEmocion con flag off ---\n";
$partidaSinFlag = $partida2;
$partidaSinFlag['features']['diario_enabled'] = false;
$entradaOff = DiarioNarrativaBridge::desdeEmocion($partidaSinFlag, $pA, $estadoTriste);
ok($entradaOff === null, 'flag off: null');

// ================================================================
// 6. desdeEmocion: texto usa EmocionalNarrativa
// ================================================================
echo "\n--- 6: texto coherente ---\n";
$entrada3 = DiarioNarrativaBridge::desdeEmocion($partida2, $pA, $estadoTriste);
$texto = $entrada3['texto'] ?? '';
ok(strpos($texto, '{') === false, 'sin tokens sin resolver');
ok(strlen($texto) > 10, 'texto suficientemente largo');

// ================================================================
// 7. desdeEmocion: alegre funciona
// ================================================================
echo "\n--- 7: desdeEmocion alegre ---\n";
$estadoAlegre = EstadoEmocional::estructura(EstadoEmocional::ALEGRE, null, 'cumple_felicidad', ['dia' => 5]);
$entradaAlegre = DiarioNarrativaBridge::desdeEmocion($partida2, $pB, $estadoAlegre);
ok($entradaAlegre !== null, 'alegre: produce entrada');
ok(($entradaAlegre['texto'] ?? '') !== '', 'alegre: tiene texto');

// ================================================================
// 8. DiarioHitoEngine: DECLARACION rechazada (acepta/acepta → null)
// ================================================================
echo "\n--- 8: DiarioHitoEngine DECLARACION ---\n";
$hitoAceptada = [
    'tipo' => RelacionBitacora::DECLARACION,
    'participantes' => [$pA, $pB],
    'fecha' => ['dia' => 4],
    'meta' => [],
];
$entryAceptada = DiarioHitoEngine::alHito($partida, $hitoAceptada);
ok($entryAceptada === null, 'declaración aceptada: null (correcto)');

$hitoRechazada = [
    'tipo' => RelacionBitacora::DECLARACION,
    'participantes' => [$pA, $pB],
    'resultado' => ['acepta_a' => true, 'acepta_b' => false],
    'fecha' => ['dia' => 4],
    'meta' => [],
];
$entryRechazada = DiarioHitoEngine::alHito($partida, $hitoRechazada);
ok($entryRechazada !== null, 'declaración rechazada: produce entrada');
ok(($entryRechazada['texto'] ?? '') !== '', 'declaración rechazada: tiene texto');

// ================================================================
// 9. El diario tiene contenido propio (no es espejo del cotilleo)
// ================================================================
echo "\n--- 9: contenido propio ---\n";
$partidaConDiario = $partida2;
DiarioHitoEngine::alHito($partidaConDiario, $hito);
DiarioNarrativaBridge::desdeEmocion($partidaConDiario, $pA, $estadoTriste);
$entradas = DiarioEngine::listarPorDia($partidaConDiario, 5);
ok(count($entradas) >= 1, 'diario tiene al menos 1 entrada');
$tipos = array_map(fn($e) => $e['tipo'] ?? '', $entradas);
ok(in_array('estado_emocional', $tipos), 'diario tiene entrada emocional propia');

echo $fail === 0 ? "\nfase4_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
