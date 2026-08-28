<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncounterOutcome;
use AquiHayTema\Engine\EncuentroExperiencia;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\MentesTemas;
use AquiHayTema\Engine\RngService;

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

// --- Test 1: Facade lee de resultado existente ---
$resultadoFake = [
    '_placeholder' => false,
    '_deltas_reales' => true,
    'delta_social' => ['tipo' => 'reales', 'a_hacia_b' => 5, 'b_hacia_a' => -4, 'calidad_a' => 'normal', 'calidad_b' => 'normal', 'intensidad' => 1],
    'delta_romance' => ['a_hacia_b' => 3, 'b_hacia_a' => -2],
    'conflicto' => 1,
    'por_participante' => [
        $pA => ['resultado' => 'bien', 'carga' => 0.3, 'carga_tema' => 0.1, 'carga_accion' => 0.04, 'texto' => 'Hugo lo pasó bien', 'compatibilidad_hacia_otro' => 65],
        $pB => ['resultado' => 'mal', 'carga' => -0.2, 'carga_tema' => 0.36, 'carga_accion' => 0.04, 'texto' => 'Tamara no conectó', 'compatibilidad_hacia_otro' => 40],
    ],
    'experiencia_narrativa' => ['causa' => 'quimica', 'texto' => 'No conectaron del todo.'],
    'texto_resumen' => 'Encuentro conocerse (bien/mal).',
];

$encFake = [
    'id' => 'enc_test',
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 1,
    'hora' => 12,
    'participantes' => [$pA, $pB],
];

$outcome = EncounterOutcome::fromRaw($resultadoFake, $encFake);

ok($outcome->esReal(), 'facade: es real');
ok(!$outcome->esPlaceholder(), 'facade: no es placeholder');
ok($outcome->tipo() === 'conocerse', 'facade: tipo');
ok($outcome->lugar() === 'lug_cafeteria', 'facade: lugar');
ok($outcome->dia() === 1, 'facade: dia');
ok($outcome->hora() === 12, 'facade: hora');
ok(count($outcome->participantes()) === 2, 'facade: 2 participantes');
ok($outcome->textoResumen() !== '', 'facade: texto resumen');

// --- Test 2: Perspectivas asimétricas ---
$perspA = $outcome->perspectiva($pA);
$perspB = $outcome->perspectiva($pB);

ok($perspA !== null, 'perspectiva A existe');
ok($perspB !== null, 'perspectiva B existe');

ok($perspA->resultado === 'bien', 'perspectiva A: bien');
ok($perspB->resultado === 'mal', 'perspectiva B: mal');

ok($perspA->esPositivo(), 'A: positivo');
ok(!$perspB->esPositivo(), 'B: no positivo');
ok($perspB->esNegativo(), 'B: negativo');
ok(!$perspA->esNegativo(), 'A: no negativo');

// Deltas direccionales
ok($perspA->socialDelta === 5, 'A: social +5');
ok($perspB->socialDelta === -4, 'B: social -4');
ok($perspA->romanceDelta === 3, 'A: romance +3');
ok($perspB->romanceDelta === -2, 'B: romance -2');

// Copys MENTES
ok($perspA->copyMentes === 'Hugo lo pasó bien', 'A: copy MENTES');
ok($perspB->copyMentes === 'Tamara no conectó', 'B: copy MENTES');

// Efecto MENTES
ok($perspB->efectoMentes() === 'ayudo_notablemente', 'B: efecto MENTES notable (carga_tema 0.36)');
ok($perspA->efectoMentes() === 'ayudo_un_poco', 'A: efecto MENTES pequeño (carga_tema 0.1)');

// --- Test 3: Tono compartido ---
ok($outcome->tonoCompartido() === 'mixto', 'tono: mixto (social +5/-4, intensidad 1)');

$resultadoPositivo = $resultadoFake;
$resultadoPositivo['delta_social']['intensidad'] = 6;
$outcomePos = EncounterOutcome::fromRaw($resultadoPositivo, $encFake);
ok($outcomePos->tonoCompartido() === 'positivo', 'tono: positivo');

$resultadoNegativo = $resultadoFake;
$resultadoNegativo['delta_social']['intensidad'] = -6;
$outcomeNeg = EncounterOutcome::fromRaw($resultadoNegativo, $encFake);
ok($outcomeNeg->tonoCompartido() === 'negativo', 'tono: negativo');

// --- Test 4: Intervención MENTES ---
$encConInterv = $encFake;
$encConInterv['intervencion_celeste'] = [
    'accion' => EncuentroIntervencion::HOBBY,
    'tema_id' => 'baile',
    'afinidad_tema' => 'afin',
    'rompe_hielo' => $pA,
    'beneficiario' => $pB,
    'carga' => 0.12,
    'tema_cargas' => MentesTemas::cargasExperienciaPorParticipante([
        'accion' => 'hobby',
        'afinidad_tema' => 'afin',
        'beneficiario' => $pB,
        'rompe_hielo' => $pA,
    ], [$pA, $pB], $cal),
];

$outcomeConInterv = EncounterOutcome::fromRaw($resultadoFake, $encConInterv, $encConInterv['intervencion_celeste']);
$interv = $outcomeConInterv->intervencion();

ok($interv !== null, 'intervencion: existe');
ok($interv->accion === 'hobby', 'intervencion: accion hobby');
ok($interv->temaId === 'baile', 'intervencion: tema baile');
ok($interv->esAfin(), 'intervencion: afin');
ok(!$interv->esAversion(), 'intervencion: no aversion');
ok($interv->rompeHielo === $pA, 'intervencion: rompe Hugo');
ok($interv->beneficiario === $pB, 'intervencion: beneficiaria Tamara');
ok($interv->cargaPara($pB) > $interv->cargaPara($pA), 'intervencion: carga Tamara > carga Hugo');

// --- Test 5: Sin intervención ---
$outcomeSin = EncounterOutcome::fromRaw($resultadoFake, $encFake);
ok($outcomeSin->intervencion() === null, 'sin_intervencion: null');

// --- Test 6: Perspective para participante desconocido ---
ok($outcome->perspectiva('desconocido') === null, 'desconocido: null');

// --- Test 7: fromEncounter con resultado real ---
$partida = [
    'meta' => ['seed' => 'test-facade'],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12],
    'residentes' => [
        $pA => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
        $pB => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
    ],
    'memoria_eventos' => [],
];

$encReal = [
    'id' => 'enc_real_test',
    'participantes' => [$pA, $pB],
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 1,
    'hora' => 12,
    'intencion' => 'celeste_organizado',
];

$rng = RngService::fromPartida($partida);
$exp = EncuentroExperiencia::resolver($partida, $encReal, $catalog, $rng, $cal);

// Simular resultado completo como lo haría EncuentroResolver
$encReal['resultado'] = [
    '_placeholder' => false,
    '_deltas_reales' => true,
    'por_participante' => $exp['por_participante'],
    'delta_social' => ['tipo' => 'reales', 'a_hacia_b' => 2, 'b_hacia_a' => 2, 'intensidad' => 2],
    'delta_romance' => null,
    'conflicto' => null,
    'experiencia_narrativa' => null,
    'texto_resumen' => 'Encuentro conocerse.',
];

$outcomeReal = EncounterOutcome::fromEncounter($encReal);
ok($outcomeReal->esReal(), 'fromEncounter: es real');
ok($outcomeReal->perspectiva($pA) !== null, 'fromEncounter: perspectiva A');
ok($outcomeReal->perspectiva($pB) !== null, 'fromEncounter: perspectiva B');
ok($outcomeReal->perspectiva($pA)->resultado !== '', 'fromEncounter: A tiene resultado');

echo $fail === 0 ? "\nencounter_outcome_facade_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
