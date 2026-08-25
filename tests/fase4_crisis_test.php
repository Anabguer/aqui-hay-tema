<?php
declare(strict_types=1);

// FASE 4 (R5) · CRISIS CAUSAL — nunca random porque toca RNG.
//
// Cobertura:
//   1 SIN causas suficientes ⇒ p=0 EXACTO, cero draws RNG (aunque p_base alto)
//   2 causas suficientes + tirada forzada ⇒ CRISIS con todo el contrato
//     (estado, bitácora CRISIS+DISCUSION_FUERTE, crisis_desde, memoria, tristeza)
//   3 cooldown canónico 48h de familia crisis
//   4 max_por_par_mes
//   5 matemática de probabilidadCrisis (0 sin causas / bonus con suelo)
//   6 causas individuales detectadas correctamente

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\IniciativaPareja;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$root = dirname(__DIR__);
$GLOBALS['__root'] = $root;
DomainBootstrap::boot();

const CA = 'ana';
const CB = 'bruno';

function calCrisis(array $overrides = []): array
{
    $cal = CalibracionConfig::load(dirname(__DIR__));
    $cal['romance_autonomo']['crisis_activa'] = true;
    // La fixture forma pareja el MISMO día (gasta un hito); el cap de test es 2.
    $cal['romance_autonomo']['max_hitos_por_dia'] = 2;
    foreach ($overrides as $k => $v) {
        $cal['romance_autonomo']['crisis'][$k] = $v;
    }
    return $cal;
}

function labPareja(array $cal): array
{
    $p = [
        'reloj' => ['dia_pueblo' => 40, 'hora_actual' => 23],
        'rng' => ['seed' => 'f4', 'state' => 303],
        'meta' => ['seed' => 'f4'],
        'residentes' => [
            CA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            CB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => ['lugares_desbloqueados' => ['lug_cafeteria']],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'encuentros' => [],
        'parentesco' => [],
        'continuidad_romantica' => [],
        'bitacora_relaciones' => [],
        'memoria_eventos' => [],
        'propuestas_cooldown' => [],
        'rechazos_propuesta' => [],
        'diario' => [],
        'buzon' => [],
        'narrativa_hitos_publicados' => [],
    ];
    for ($i = 0; $i < 3; $i++) {
        RelacionEngine::ajustarSocialHacia($p, CA, CB, 10);
        RelacionEngine::ajustarSocialHacia($p, CB, CA, 10);
    }
    ParejaEngine::formar($p, CA, CB, true, true, RelacionBitacora::DECLARACION, $cal);
    return $p;
}

/** Causas artificiales completas: conflicto + racha mala + suelo + abandono. */
function sembrarCausas(array &$p): void
{
    // C1 conflicto alto
    RelacionEngine::upsertConflicto($p, CA, CB, 9, 'roce', 'test');
    // C2 racha mala (últimos encuentros del par)
    MemoriaEventos::registrar($p, 'encuentro', [CA, CB], null, 'cita', 'mal');
    MemoriaEventos::registrar($p, 'encuentro', [CA, CB], null, 'cita', 'muy_mal');
    // C3 estabilidad en suelo
    $rel = RelacionEngine::obtenerEntre($p, CA, CB)['romance'];
    $rel['estabilidad_pareja']['valor'] = 15;
    RelacionEngine::persistirRomance($p, $rel);
    // C4 abandono
    $socId = 'soc_' . min(CA, CB) . '_' . max(CA, CB);
    foreach ($p['relaciones_sociales'] as $i => $s) {
        if (($s['id'] ?? '') === $socId) {
            $p['relaciones_sociales'][$i]['ultimo_contacto']['dia'] = 40 - 10;
        }
    }
}

$calBase = calCrisis();

// ============ 6: detección individual de causas ============
$p = labPareja($calBase);
ok(IniciativaPareja::causasCrisis($p, CA, CB, $calBase) === [], '6a pareja sana: cero causas');
sembrarCausas($p);
$causas = IniciativaPareja::causasCrisis($p, CA, CB, $calBase);
ok(in_array('conflicto', $causas, true), '6b C1 conflicto');
ok(in_array('racha_mala', $causas, true), '6c C2 racha_mala');
ok(in_array('estabilidad_suelo', $causas, true), '6d C3 estabilidad_suelo');
ok(in_array('abandono', $causas, true), '6e C4 abandono');

// ============ 5: matemática de la probabilidad ============
ok(IniciativaPareja::probabilidadCrisis($calBase, []) === 0.0, '5a sin causas: p=0');
ok(IniciativaPareja::probabilidadCrisis($calBase, ['conflicto']) === 0.0, '5b una causa (< minimas=2): p=0');
$pDos = IniciativaPareja::probabilidadCrisis($calBase, ['conflicto', 'racha_mala']);
ok(abs($pDos - 0.015) < 1e-9, '5c dos causas: p base');
$pSuelo = IniciativaPareja::probabilidadCrisis($calBase, ['conflicto', 'estabilidad_suelo']);
ok(abs($pSuelo - 0.055) < 1e-9, '5d suelo añade bonus (0.015+0.04)');

// ============ 1: sin causas suficientes ⇒ ni con p_base=1 hay crisis NI draws ============
$calP1 = calCrisis(['probabilidad' => 1.0]);
$p = labPareja($calP1);
RelacionEngine::upsertConflicto($p, CA, CB, 9, 'roce', 'test'); // solo UNA causa
$st0 = (int) $p['rng']['state'];
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calP1);
ok(($out['crisis'] ?? 0) === 0 && ParejaEngine::estado($p, CA, CB) === ParejaEngine::PAREJA, '1a una causa sola: NUNCA crisis');
ok((int) $p['rng']['state'] === $st0, '1b CERO consumo RNG sin causas suficientes');

// ============ 2: causas suficientes + tirada ⇒ crisis completa ============
$p = labPareja($calP1);
sembrarCausas($p);
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calP1);
ok(($out['crisis'] ?? 0) === 1, '2a evaluador reporta 1 crisis');
ok(ParejaEngine::estado($p, CA, CB) === ParejaEngine::CRISIS, '2b estado CRISIS');
ok(count(RelacionBitacora::entre($p, CA, CB, RelacionBitacora::CRISIS)) === 1, '2c hito CRISIS');
ok(count(RelacionBitacora::entre($p, CA, CB, RelacionBitacora::DISCUSION_FUERTE)) === 1, '2d flavor DISCUSION_FUERTE (conflicto)');
$rel = RelacionEngine::obtenerEntre($p, CA, CB)['romance'];
ok(is_array($rel['crisis_desde'] ?? null), '2e crisis_desde sellado');
ok((int) ($rel['fallos_reparacion'] ?? -1) === 0, '2f fallos_reparacion a cero');
$hayMemCrisis = false;
foreach (($p['memoria_eventos'] ?? []) as $ev) {
    if (($ev['familia'] ?? '') === 'crisis') {
        $hayMemCrisis = true;
    }
}
ok($hayMemCrisis, '2g memoria familia crisis');
$emoA = (string) ($p['residentes'][CA]['runtime']['estado_emocional']['id'] ?? '');
ok($emoA === 'triste', '2h tristeza aplicada');

// ============ 3: cooldown 48h familia crisis ============
$out2 = IniciativaPareja::evaluarAlCerrarDia($p, $calP1);
ok(($out2['crisis'] ?? 0) === 0 && ParejaEngine::estado($p, CA, CB) === ParejaEngine::CRISIS, '3 cooldown crisis: sin doble crisis');

// ============ 4: max_por_par_mes ============
$p = labPareja($calP1);
sembrarCausas($p);
$calMes = calCrisis(['probabilidad' => 1.0, 'max_por_par_mes' => 0]);
$outM = IniciativaPareja::evaluarAlCerrarDia($p, $calMes);
ok(($outM['crisis'] ?? 0) === 0 && ParejaEngine::estado($p, CA, CB) === ParejaEngine::PAREJA, '4 max_por_par_mes=0 bloquea incluso con causas');

echo $fail === 0 ? "\nOK fase4_crisis\n" : "\nFAIL fase4_crisis ($fail)\n";
exit($fail === 0 ? 0 : 1);
