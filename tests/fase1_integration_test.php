<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncounterOutcome;
use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EncuentroCotilleoCopy;
use AquiHayTema\Engine\EncuentroResultadoVista;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\MentesTemas;
use AquiHayTema\Engine\PartidaService;

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

$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$pA = 'per_hugo';
$pB = 'per_tamara';

// Partida de prueba con los residentes correctos
$partida = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12],
    'residentes' => [
        $pA => ['identidad_publica' => ['nombre' => 'Hugo', 'genero' => 'hombre'], 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
        $pB => ['identidad_publica' => ['nombre' => 'Tamara', 'genero' => 'mujer'], 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
    ],
    'encuentros' => [],
];

// ================================================================
// FASE 1A: EncounterOutcome facade integrado
// ================================================================
echo "--- FASE 1A: Facade integrado ---\n";

$resultadoFake = [
    '_placeholder' => false,
    '_deltas_reales' => true,
    'delta_social' => ['tipo' => 'reales', 'a_hacia_b' => 5, 'b_hacia_a' => -4, 'calidad_a' => 'normal', 'calidad_b' => 'normal', 'intensidad' => 1],
    'delta_romance' => ['a_hacia_b' => 3, 'b_hacia_a' => -2],
    'conflicto' => null,
    'por_participante' => [
        $pA => ['resultado' => 'bien', 'carga' => 0.3, 'carga_tema' => 0.1, 'carga_accion' => 0.04, 'texto' => 'Hugo lo pasó bien', 'compatibilidad_hacia_otro' => 65],
        $pB => ['resultado' => 'mal', 'carga' => -0.2, 'carga_tema' => 0.36, 'carga_accion' => 0.04, 'texto' => 'Tamara no conectó', 'compatibilidad_hacia_otro' => 40],
    ],
    'experiencia_narrativa' => ['causa' => 'quimica', 'texto' => 'No conectaron del todo.'],
    'texto_resumen' => 'Encuentro conocerse (bien/mal).',
];

$temaCargas = MentesTemas::cargasExperienciaPorParticipante([
    'accion' => 'hobby',
    'afinidad_tema' => 'afin',
    'beneficiario' => $pB,
    'rompe_hielo' => $pA,
], [$pA, $pB], $cal);

$encFake = [
    'id' => 'enc_fase1',
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 1,
    'hora' => 12,
    'participantes' => [$pA, $pB],
    'intervencion_celeste' => [
        'accion' => 'hobby',
        'tema_id' => 'baile',
        'afinidad_tema' => 'afin',
        'rompe_hielo' => $pA,
        'beneficiario' => $pB,
        'carga' => 0.12,
        'tema_cargas' => $temaCargas,
    ],
];

$outcome = EncounterOutcome::fromRaw($resultadoFake, $encFake, $encFake['intervencion_celeste']);
ok($outcome->esReal(), '1A: es real');
ok($outcome->perspectiva($pA)->resultado === 'bien', '1A: Hugo bien');
ok($outcome->perspectiva($pB)->resultado === 'mal', '1A: Tamara mal');
ok($outcome->perspectiva($pA)->socialDelta === 5, '1A: Hugo social +5');
ok($outcome->perspectiva($pB)->socialDelta === -4, '1A: Tamara social -4');
ok($outcome->intervencion() !== null, '1A: tiene intervención');
ok($outcome->intervencion()->temaId === 'baile', '1A: tema baile');
ok($outcome->intervencion()->esAfin(), '1A: afin');
ok($outcome->intervencion()->cargaPara($pB) > $outcome->intervencion()->cargaPara($pA), '1A: carga Tamara > Hugo');

// ================================================================
// FASE 1B: Cotilleo asimétrico
// ================================================================
echo "\n--- FASE 1B: Cotilleo asimétrico ---\n";

$partida['encuentros'][] = [
    'id' => 'enc_fase1b',
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 1,
    'hora' => 19,
    'participantes' => [$pA, $pB],
    'estado' => 'terminado',
    'resultado' => $resultadoFake,
];

$cot = EncuentroCotilleoCopy::compilar($partida, $partida['encuentros'][0], $resultadoFake, $catalog, $root);
ok($cot !== null, '1B: cotilleo compilado');
ok(str_contains($cot['texto'], 'bien') || str_contains($cot['texto'], 'conectó'), '1B: refleja asimetría');
ok(!str_contains($cot['texto'], 'buenas migas'), '1B: no dice "buenas migas" con mixto');
ok(!str_contains($cot['texto'], 'algo tensa'), '1B: no dice "algo tensa" (solo negativo)');
echo "  Texto: " . $cot['texto'] . "\n";

// ================================================================
// FASE 1C: Emoción causal completa
// ================================================================
echo "\n--- FASE 1C: Emoción causal completa ---\n";

// Simular estado emocional con origen cumple_felicidad
$estadoCumple = [
    'id' => EstadoEmocional::ALEGRE,
    'origen' => 'cumple_felicidad',
    'desde' => ['dia' => 1],
    'hasta' => ['dia' => 2],
    'contexto' => ['fuente' => 'f10_cumpleanos'],
];
$expCumple = EmocionalNarrativa::explicacionCompleta($partida, $pA, $estadoCumple);
ok($expCumple !== null, '1C: cumple_felicidad tiene explicación');
ok(str_contains($expCumple['explicacion'] ?? '', 'enhorabuena') || str_contains($expCumple['explicacion'] ?? '', 'vecinos'), '1C: copy cumple relevante');

// Simular estado emocional con origen consejo_celestine
$estadoConsejo = [
    'id' => EstadoEmocional::ALEGRE,
    'origen' => 'consejo_celestine',
    'desde' => ['dia' => 1],
    'hasta' => ['dia' => 2],
    'contexto' => ['fuente' => 'mensajito_consejo'],
];
$expConsejo = EmocionalNarrativa::explicacionCompleta($partida, $pA, $estadoConsejo);
ok($expConsejo !== null, '1C: consejo_celestine tiene explicación');
ok(str_contains($expConsejo['explicacion'] ?? '', 'consejo'), '1C: copy consejo relevante');

// Pista ficha
$pistaCumple = EmocionalNarrativa::pistaFicha($estadoCumple);
ok($pistaCumple !== null, '1C: pista ficha cumple');
$pistaConsejo = EmocionalNarrativa::pistaFicha($estadoConsejo);
ok($pistaConsejo !== null, '1C: pista ficha consejo');

// Cotilleo buzón
$cotCumple = EmocionalNarrativa::cotilleoParaOrigen($partida, $pA, 'cumple_felicidad');
ok($cotCumple !== null, '1C: cotilleo buzón cumple');
$cotConsejo = EmocionalNarrativa::cotilleoParaOrigen($partida, $pA, 'consejo_celestine');
ok($cotConsejo !== null, '1C: cotilleo buzón consejo');

// Orígenes existentes siguen funcionando
$estadoEncuentro = [
    'id' => EstadoEmocional::TRISTE,
    'origen' => 'encuentro',
    'desde' => ['dia' => 1],
    'hasta' => ['dia' => 2],
    'contexto' => ['encuentro_id' => 'enc_test', 'resultado_experiencia' => 'muy_mal'],
];
$expEnc = EmocionalNarrativa::explicacionCompleta($partida, $pA, $estadoEncuentro);
ok($expEnc !== null, '1C: origen encuentro sigue funcionando');

$estadoTrabajo = [
    'id' => EstadoEmocional::TRISTE,
    'origen' => 'perder_trabajo',
    'desde' => ['dia' => 1],
    'hasta' => ['dia' => 2],
    'contexto' => [],
];
$expTrabajo = EmocionalNarrativa::explicacionCompleta($partida, $pA, $estadoTrabajo);
ok($expTrabajo !== null, '1C: origen perder_trabajo sigue funcionando');

// ================================================================
// FASE 1D: MENTES visible
// ================================================================
echo "\n--- FASE 1D: MENTES visible ---\n";

$encConInterv = $encFake;
$encConInterv['estado'] = 'terminado';
$encConInterv['resultado'] = $resultadoFake;
$vista = EncuentroResultadoVista::de($partida, $encConInterv, $catalog, $root);
$mentes = $vista['resultado']['mentes'] ?? [];
ok($mentes !== [], '1D: hay línea MENTES');
ok(is_array($mentes), '1D: mentes es array');
$mentesTexto = implode(' ', $mentes);
ok(str_contains($mentesTexto, 'Celestine') || str_contains($mentesTexto, 'intervención'), '1D: menciona Celestine o intervención');
ok(in_array('mentes', array_keys($vista['resultado'])), '1D: campo mentes en resultado');
echo "  MENTES: " . $mentesTexto . "\n";

// Sin intervención
$encSinInterv = [
    'id' => 'enc_sin',
    'tipo' => 'conocerse',
    'participantes' => [$pA, $pB],
    'estado' => 'terminado',
    'resultado' => $resultadoFake,
];
$vistaSin = EncuentroResultadoVista::de($partida, $encSinInterv, $catalog, $root);
$mentesSin = $vistaSin['resultado']['mentes'] ?? [];
ok($mentesSin === [], '1D: sin intervención no hay línea MENTES');

echo $fail === 0 ? "\nfase1_integration_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
