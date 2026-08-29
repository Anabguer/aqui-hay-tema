<?php
declare(strict_types=1);

/**
 * TUTORIAL ACTION GUARANTEE — tests de la política unificada.
 *
 * 1. M1 con voluntad hostil → aceptada (garantía pedagógica)
 * 2. M3 cine con voluntad hostil (p bajísimo) → aceptada (garantía pedagógica)
 * 3. Acción que NO cumple misión tutorial → sin garantía
 * 4. Post-misión: misma acción → vuelve a pasar por Voluntad normal
 * 5. Post-tutorial: Voluntad completamente normal
 * 6. Intento tutorial válido → no deja cooldown residual
 * 7. Planes normales fuera del tutorial mantienen rechazos reales
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RechazoMemoria;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\TutorialPrimerosPasos;
use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ': ' . $m . PHP_EOL;
    if (!$c) {
        $fail++;
    }
}

/**
 * Voluntad hostil con _joint_plan (media_geometrica): p bajísimo, el RNG lo rechazaría.
 * Replica el comportamiento real del juego cuando "no le apetece".
 */
final class VoluntadHostilTest implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'residente_id' => $residenteId,
            'nombre' => \AquiHayTema\Engine\IdentidadPublica::nombre($partida, $residenteId),
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'voluntad_hostil_test',
            'motivo_tipo' => null,
            'copy_id' => null,
            'p' => 0.01,
            '_joint_plan' => true,
            'factores' => ['p_hostil_test' => 0.01],
        ];
    }
}

$svc = new PartidaService($root);
$cal = \AquiHayTema\Engine\CalibracionConfig::load($root);
$hostil = new VoluntadHostilTest();

// ─── Helpers ────────────────────────────────────────────────────────────────

function missionState(array $partida, string $missionId): string
{
    foreach ($partida['misiones_diarias']['items'] ?? [] as $m) {
        if (($m['id'] ?? '') === $missionId) {
            return (string) ($m['estado'] ?? '');
        }
    }
    return '';
}

function findEncounterByParticipants(array $partida, array $pair): ?array
{
    $sorted = $pair;
    sort($sorted);
    foreach ($partida['encuentros'] ?? [] as $enc) {
        $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        sort($parts);
        if ($parts === $sorted) {
            return $enc;
        }
    }
    return null;
}

function findEncounterBySolo(array $partida, string $rid): ?array
{
    foreach ($partida['encuentros'] ?? [] as $enc) {
        $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        if (count($parts) === 1 && (string) $parts[0] === $rid) {
            return $enc;
        }
    }
    return null;
}

function findPropuestaWithGarantia(array $partida, ?string $misionId = null): ?array
{
    foreach ($partida['propuestas_encuentro'] ?? [] as $p) {
        if (!empty($p['garantia_tutorial'])) {
            if ($misionId === null || ($p['garantia_tutorial_mision'] ?? '') === $misionId) {
                return $p;
            }
        }
    }
    return null;
}

function proponerPresentar(array &$p, string $a, string $b, ?VoluntadEvaluator $vol = null): array
{
    $dia = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    for ($h = 8; $h < 22; $h++) {
        if (!Reloj::esFuturo($p['reloj'] ?? [], $dia, $h)) {
            continue;
        }
        return PropuestaEncuentroEngine::proponer(
            $p, [$a, $b], $dia, $h,
            PropuestaNivel::PRESENTAR, 'lug_cafeteria', null, $vol
        );
    }
    return ['ok' => false];
}

function proponerIndividual(array &$p, string $tercero, string $lugar, ?VoluntadEvaluator $vol = null): array
{
    $dia = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    for ($h = 8; $h < 22; $h++) {
        if (!Reloj::esFuturo($p['reloj'] ?? [], $dia, $h)) {
            continue;
        }
        return PropuestaEncuentroEngine::proponer(
            $p, [$tercero], $dia, $h,
            'individual', $lugar, null, $vol
        );
    }
    return ['ok' => false];
}

function avanzarHastaEncuentro(PartidaService $svc, array &$p, array $enc): void
{
    $target = (int) ($enc['dia'] ?? 1) * 24 + (int) ($enc['hora'] ?? 0);
    while (((int) ($p['reloj']['dia_pueblo'] ?? 1) * 24 + (int) ($p['reloj']['hora_actual'] ?? 0)) < $target) {
        $svc->avanzarReloj($p, 1);
    }
    EncuentroLifecycle::sincronizarConReloj($p, null, $svc->getCatalog());
}

function avanzarMasAllaEncuentro(PartidaService $svc, array &$p, array $enc): void
{
    $dur = max(1, (int) ceil(((int) ($enc['duracion_minutos'] ?? 90)) / 60));
    $fin = (int) ($enc['dia'] ?? 1) * 24 + (int) ($enc['hora'] ?? 0) + $dur;
    while (((int) ($p['reloj']['dia_pueblo'] ?? 1) * 24 + (int) ($p['reloj']['hora_actual'] ?? 0)) < $fin) {
        $svc->avanzarReloj($p, 1);
    }
    EncuentroLifecycle::sincronizarConReloj($p, null, $svc->getCatalog());
}

function completarM1(PartidaService $svc, array &$p, VoluntadHostilTest $hostil): void
{
    $a = (string) ($p['tutorial']['pareja_mision1']['a'] ?? '');
    $b = (string) ($p['tutorial']['pareja_mision1']['b'] ?? '');
    proponerPresentar($p, $a, $b, $hostil);
    $enc = findEncounterByParticipants($p, [$a, $b]);
    avanzarHastaEncuentro($svc, $p, $enc);
    avanzarMasAllaEncuentro($svc, $p, $enc);
}

function completarM2(array &$p, string $root): void
{
    $msgId = (string) ($p['tutorial']['mensajito_id'] ?? '');
    if ($msgId !== '') {
        BuzonEngine::marcarLeido($p, $msgId);
        TutorialPrimerosPasos::alLeerMensaje($p, $msgId, new Catalog($root));
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 1: M1 con voluntad hostil → aceptada (garantía pedagógica)
// ═══════════════════════════════════════════════════════════════════════════
echo "--- TEST 1: M1 hostil → acepta ---\n";
$p1 = $svc->nuevaPartida('juego_v1', 'tut-guar-m1-hostil');
$a = (string) ($p1['tutorial']['pareja_mision1']['a'] ?? '');
$b = (string) ($p1['tutorial']['pareja_mision1']['b'] ?? '');

ok(missionState($p1, TutorialPrimerosPasos::M1) === MisionDiariaEngine::EST_PENDIENTE, '1. M1 pendiente');
ok(TutorialPrimerosPasos::esPropuestaPedagogicaTutorial($p1, [$a, $b], PropuestaNivel::PRESENTAR) === TutorialPrimerosPasos::M1, '1b. detecta M1');

$r1 = proponerPresentar($p1, $a, $b, $hostil);
ok(!empty($r1['ok']) && !empty($r1['programado']), '1c. M1 hostil programada');

$prop1 = findPropuestaWithGarantia($p1, TutorialPrimerosPasos::M1);
ok($prop1 !== null, '1d. marca garantia_tutorial');
ok($prop1 !== null && ($prop1['garantia_tutorial_mision'] ?? '') === TutorialPrimerosPasos::M1, '1e. garantia_tutorial_mision = M1');

$enc1 = findEncounterByParticipants($p1, [$a, $b]);
ok($enc1 !== null, '1f. encuentro creado');
avanzarHastaEncuentro($svc, $p1, $enc1);
avanzarMasAllaEncuentro($svc, $p1, $enc1);
ok(missionState($p1, TutorialPrimerosPasos::M1) === MisionDiariaEngine::EST_CUMPLIDA, '1g. M1 cumplida');

// ═══════════════════════════════════════════════════════════════════════════
// TEST 2: M3 cine con voluntad hostil → aceptada
// ═══════════════════════════════════════════════════════════════════════════
echo "\n--- TEST 2: M3 cine hostil → acepta ---\n";
$p2 = $svc->nuevaPartida('juego_v1', 'tut-guar-m3-cine');
$tercero = (string) ($p2['tutorial']['tercero'] ?? '');
$lugM3 = (string) ($p2['tutorial']['lugar_mision3'] ?? 'lug_cine');

completarM1($svc, $p2, $hostil);
ok(missionState($p2, TutorialPrimerosPasos::M1) === MisionDiariaEngine::EST_CUMPLIDA, '2. M1 cumplida');

completarM2($p2, $root);
ok(missionState($p2, TutorialPrimerosPasos::M2) === MisionDiariaEngine::EST_CUMPLIDA, '2b. M2 cumplida');
ok(missionState($p2, TutorialPrimerosPasos::M3) === MisionDiariaEngine::EST_PENDIENTE, '2c. M3 pendiente');

ok(TutorialPrimerosPasos::esPropuestaPedagogicaTutorial($p2, [$tercero], 'individual') === TutorialPrimerosPasos::M3, '2d. detecta M3');

$r3 = proponerIndividual($p2, $tercero, $lugM3, $hostil);
ok(!empty($r3['ok']) && !empty($r3['programado']), '2e. M3 cine ACEPTADA a pesar de voluntad hostil');

$propM3 = findPropuestaWithGarantia($p2, TutorialPrimerosPasos::M3);
ok($propM3 !== null, '2f. garantia_tutorial_mision = M3');

// ═══════════════════════════════════════════════════════════════════════════
// TEST 3: Acción que NO cumple misión tutorial → sin garantía
// ═══════════════════════════════════════════════════════════════════════════
echo "\n--- TEST 3: Acción incorrecta → sin garantía ---\n";
$p3 = $svc->nuevaPartida('juego_v1', 'tut-guar-no-match');
$a3 = (string) ($p3['tutorial']['pareja_mision1']['a'] ?? '');
$b3 = (string) ($p3['tutorial']['pareja_mision1']['b'] ?? '');
$tercero3 = (string) ($p3['tutorial']['tercero'] ?? '');

// PRESENTAR entre tercero + uno de la pareja → no coincide con ninguna misión
$rWrong = proponerPresentar($p3, $tercero3, $a3, $hostil);
ok(!empty($rWrong['ok']), '3. propuesta procesada');

// Si fue aceptada o rechazada depende de PropuestaNivel, pero NO debe tener garantía
$propNoMatch = findPropuestaWithGarantia($p3);
ok($propNoMatch === null, '3b. sin garantia_tutorial en acción no-tutorial');

// ═══════════════════════════════════════════════════════════════════════════
// TEST 4: Post-misión → Voluntad normal
// ═══════════════════════════════════════════════════════════════════════════
echo "\n--- TEST 4: Post-misión → Voluntad normal ---\n";
// p1 tiene M1 completada; M3 no pendiente (M2 no completada)
ok(missionState($p1, TutorialPrimerosPasos::M1) === MisionDiariaEngine::EST_CUMPLIDA, '4. M1 cumplida en p1');
ok(TutorialPrimerosPasos::esPropuestaPedagogicaTutorial($p1, [$a, $b], PropuestaNivel::PRESENTAR) === '', '4b. sin garantía para pareja post-M1');

// ═══════════════════════════════════════════════════════════════════════════
// TEST 5: Post-tutorial → Voluntad completamente normal
// ═══════════════════════════════════════════════════════════════════════════
echo "\n--- TEST 5: Post-tutorial → Voluntad normal ---\n";
$encM3 = findEncounterBySolo($p2, $tercero);
ok($encM3 !== null, '5. encuentro M3 creado');
avanzarHastaEncuentro($svc, $p2, $encM3);
avanzarMasAllaEncuentro($svc, $p2, $encM3);
ok(missionState($p2, TutorialPrimerosPasos::M3) === MisionDiariaEngine::EST_CUMPLIDA, '5b. M3 cumplida');
ok(!empty($p2['tutorial']['jugable_completado']), '5c. tutorial completado');

// Después del tutorial, esPropuestaPedagogicaTutorial debe devolver ''
$a2b = (string) ($p2['tutorial']['pareja_mision1']['a'] ?? '');
$b2b = (string) ($p2['tutorial']['pareja_mision1']['b'] ?? '');
ok(TutorialPrimerosPasos::esPropuestaPedagogicaTutorial($p2, [$a2b, $b2b], PropuestaNivel::PRESENTAR) === '', '5d. post-tutorial sin garantía para PRESENTAR');
ok(TutorialPrimerosPasos::esPropuestaPedagogicaTutorial($p2, [$tercero], 'individual') === '', '5e. post-tutorial sin garantía para individual');

// ═══════════════════════════════════════════════════════════════════════════
// TEST 6: Intento tutorial válido → no deja cooldown residual
// ═══════════════════════════════════════════════════════════════════════════
echo "\n--- TEST 6: Sin cooldown residual ---\n";
$p6 = $svc->nuevaPartida('juego_v1', 'tut-guar-no-cooldown');
$tercero6 = (string) ($p6['tutorial']['tercero'] ?? '');
$lugM36 = (string) ($p6['tutorial']['lugar_mision3'] ?? 'lug_cine');

completarM1($svc, $p6, $hostil);
completarM2($p6, $root);

$r6 = proponerIndividual($p6, $tercero6, $lugM36, $hostil);
ok(!empty($r6['ok']) && !empty($r6['programado']), '6. M3 aceptada con garantía');
ok(PropuestaCooldown::activo($p6, $tercero6, $tercero6, 'individual', $cal) === false, '6b. sin cooldown residual');
ok(RechazoMemoria::countHacia($p6, $tercero6, $tercero6) === 0, '6c. sin memoria de rechazo');

// ═══════════════════════════════════════════════════════════════════════════
// TEST 7: Planes normales fuera del tutorial → rechazos reales
// ═══════════════════════════════════════════════════════════════════════════
echo "\n--- TEST 7: Planes normales → rechazos reales ---\n";
$p7 = $svc->nuevaPartida('juego_v1', 'tut-guar-plan-normal');
$p7['tutorial']['jugable_completado'] = true;

$residentes = [];
foreach ($p7['residentes'] ?? [] as $id => $res) {
    if (is_string($id) && $id !== '' && is_array($res) && ($res['presencia'] ?? 'residente') === 'residente') {
        $residentes[] = $id;
    }
}
sort($residentes);
if (count($residentes) >= 2) {
    $r7 = proponerPresentar($p7, $residentes[0], $residentes[1], $hostil);
    ok(!empty($r7['ok']), '7. plan normal procesado');

    $propNormal = findPropuestaWithGarantia($p7);
    ok($propNormal === null, '7b. sin garantia_tutorial en plan normal');
}

// ═══════════════════════════════════════════════════════════════════════════
echo $fail === 0
    ? "\ntutorial_garantia_acciones_test OK\n"
    : "\ntutorial_garantia_acciones_test FAIL ($fail)\n";
exit($fail === 0 ? 0 : 1);
