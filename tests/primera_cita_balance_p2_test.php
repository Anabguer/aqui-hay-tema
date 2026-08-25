<?php
declare(strict_types=1);

// Test de balance P2 para primera_cita + REGRESIÓN OBLIGATORIA Sandra/Dolores.
// Estado auditado (día 12 16:00, cine): flechazo/interés mutuo 28, social +4/-7,
// conflicto 2, conocidas, neutras, sin rechazos previos. Los datos de la pareja
// NO cambian entre ANTES y DESPUÉS; solo la calibración del motor.
//
//   ANTES  (mod_tipo.pc=0, sin bonus, conf x1): p_plan = sqrt(.5272*.5014) = .5141 (~51.4%)
//   DESPUÉS P2 (mod_tipo.pc=+4, recíproco+12, conf x3): p_plan ~ .6173 (~61.7%)

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

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
$cal = CalibracionConfig::load($root);

function pDeScore(int $s, array $cal): float
{
    $pMin = (float) CalibracionConfig::get($cal, 'voluntad.p_min', 0.08);
    $pMax = (float) CalibracionConfig::get($cal, 'voluntad.p_max', 0.94);
    $pExc = (float) CalibracionConfig::get($cal, 'voluntad.p_excelente', 0.92);
    $sExc = (int) CalibracionConfig::get($cal, 'voluntad.score_excelente', 88);
    $p = $pMin + (max(0, min(100, $s)) / 100.0) * ($pMax - $pMin);
    if ($s >= $sExc) {
        $p = $pExc;
    }
    return max($pMin, min($pMax, $p));
}

function pPlan(array $scores, array $cal): float
{
    return sqrt(max(0.0, pDeScore((int) $scores[0], $cal)) * max(0.0, pDeScore((int) $scores[1], $cal)));
}

/** Partida mínima determinista. IDs fijos, sin RNG de motor. */
function partidaLab(): array
{
    return [
        'reloj' => ['dia_pueblo' => 12, 'hora_actual' => 14],
        'rng' => ['cursor' => 5],
        'residentes' => [
            'sandra' => ['identidad_publica' => ['nombre' => 'Sandra']],
            'dolores' => ['identidad_publica' => ['nombre' => 'Dolores']],
            'x1' => ['identidad_publica' => ['nombre' => 'X1']],
            'y2' => ['identidad_publica' => ['nombre' => 'Y2']],
        ],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'rechazos_propuesta' => [],
    ];
}

/** Fija el estado relacional exacto de un escenario (sin tocar nada más). */
function estadoPar(array &$p, string $a, string $b, int $socAB, int $socBA, ?int $romAB, ?int $romBA, ?int $conf): void
{
    // El techo por encuentro es ±10: acumular contactos como haría el juego real.
    acumularSocial($p, $a, $b, $socAB);
    acumularSocial($p, $b, $a, $socBA);
    if ($romAB !== null) {
        RelacionEngine::setRomanceHacia($p, $a, $b, $romAB);
    }
    if ($romBA !== null) {
        RelacionEngine::setRomanceHacia($p, $b, $a, $romBA);
    }
    if ($conf !== null && $conf > 0) {
        $lo = $a < $b ? $a : $b;
        $hi = $lo === $a ? $b : $a;
        $p['relaciones_conflicto'][] = [
            'id' => 'conf_' . $lo . '_' . $hi,
            'persona_a' => $lo,
            'persona_b' => $hi,
            'intensidad' => $conf,
        ];
    }
}

function acumularSocial(array &$p, string $desde, string $hacia, int $objetivo): void
{
    if ($objetivo === 0) {
        // Contacto neutro: marca conocidos=true sin mover la barra.
        RelacionEngine::ajustarSocialHacia($p, $desde, $hacia, 0);
        return;
    }
    $paso = $objetivo > 0 ? 10 : -10;
    $resto = $objetivo;
    while ($resto !== 0) {
        $d = abs($resto) > 10 ? $paso : $resto;
        RelacionEngine::ajustarSocialHacia($p, $desde, $hacia, $d);
        $resto -= $d;
    }
}

function desglosePar(array $p, array $cal, string $tipo): array
{
    $prop = ['tipo' => $tipo, 'participantes' => ['sandra', 'dolores'], 'lugar' => null];
    return [
        VoluntadPonderadaEvaluator::desglose($p, $prop, 'sandra', 'dolores', $cal),
        VoluntadPonderadaEvaluator::desglose($p, $prop, 'dolores', 'sandra', $cal),
    ];
}

// ===================== REGRESIÓN OBLIGATORIA SANDRA/DOLORES =====================
$p = partidaLab();
estadoPar($p, 'sandra', 'dolores', 4, -7, 28, 28, 2);
[$dA, $dB] = desglosePar($p, $cal, 'primera_cita');

ok((int) $dA['social'] === 4 && (int) $dB['social'] === -7, 'estado auditado intacto: social +4/-7');
ok((int) $dA['romance'] === 28 && (int) $dB['romance'] === 28, 'estado auditado intacto: romance mutuo 28');
ok((int) $dA['conflicto'] === 2, 'estado auditado intacto: conflicto 2');
ok(empty($dA['rechazos_previos']) || (int) $dA['rechazos_previos'] === 0, 'sin rechazos previos');

// ANTES: réplica inline documentada de la fórmula pre-P2 (misma base, sin knobs nuevos).
$sA_antes = 48 + (int) round(4 * 0.28) + (int) round(28 * 0.18) - 2 + 0;
$sB_antes = 48 + (int) round(-7 * 0.28) + (int) round(28 * 0.18) - 2 + 0;
$pPlanAntes = pPlan([$sA_antes, $sB_antes], $cal);
ok(abs($sA_antes - 52) <= 0 && abs($sB_antes - 49) <= 0, 'ANTES replica scores auditados 52/49');
ok(abs($pPlanAntes - 0.51413) < 0.002, "ANTES p_plan ~51.4% (obs=" . sprintf('%.4f', $pPlanAntes) . ")");

// DESPUÉS: motor real con calibración P2.
$sA = (int) $dA['score'];
$sB = (int) $dB['score'];
$pPlanDespues = pPlan([$sA, $sB], $cal);
ok((int) $dA['mod_tipo'] === 4 && (int) $dB['mod_tipo'] === 4, 'P2 activo: mod_tipo primera_cita +4');
ok((int) $dA['bonus_primera_cita_reciproca'] === 12 && (int) $dB['bonus_primera_cita_reciproca'] === 12, 'P2 activo: bonus recíproco +12 (señal mutua)');
ok((float) $dA['conflicto_mult_cita'] === 3.0, 'P2 activo: conflicto x3 en cita');
$pDespuesEsperado = sqrt(pDeScore(64, $cal) * pDeScore(61, $cal));
ok(abs($pPlanDespues - $pDespuesEsperado) < 0.001,
    "DESPUÉS P2 p_plan ~61.7% (scores $sA/$sB, obs=" . sprintf('%.4f', $pPlanDespues) . ")");
ok($pPlanDespues > 0.60 && $pPlanDespues < 0.63, "regresión ANTES→DESPUÉS: ~51.4% → ~61.7%");

// ===================== ESCENARIOS REPRESENTATIVOS ================================

// Interés unilateral 28/0: bonus NO aplica (falta señal en una dirección).
$p = partidaLab();
estadoPar($p, 'sandra', 'dolores', 0, 0, 28, null, null);
[$uA, $uB] = desglosePar($p, $cal, 'primera_cita');
$pu = pPlan([$uA['score'], $uB['score']], $cal);
ok((int) $uA['bonus_primera_cita_reciproca'] === 0 && (int) $uB['bonus_primera_cita_reciproca'] === 0, 'unilateral: bonus recíproco NO aplica');
ok($pu >= 0.53 && $pu <= 0.56, "interés unilateral ~53-55% (obs=" . sprintf('%.3f', $pu) . ")");

// Flechazo/interés mutuo limpio.
$p = partidaLab();
estadoPar($p, 'sandra', 'dolores', 0, 0, 28, 28, null);
[$mA] = desglosePar($p, $cal, 'primera_cita');
$pm = pPlan([$mA['score'], $mA['score']], $cal);
ok($pm > 0.65, "mutuo claramente sobre moneda ~67% (obs=" . sprintf('%.3f', $pm) . ")");

// Mutuo + roce leve simétrico (conf 2).
$p = partidaLab();
estadoPar($p, 'sandra', 'dolores', 0, 0, 28, 28, 2);
[$rA] = desglosePar($p, $cal, 'primera_cita');
$pr = pPlan([$rA['score'], $rA['score']], $cal);
ok($pr >= 0.60 && $pr <= 0.64, "mutuo + roce leve ~62% (obs=" . sprintf('%.3f', $pr) . ")");

// Relación muy mala + conflicto fuerte (con romance residual mutuo).
$p = partidaLab();
estadoPar($p, 'sandra', 'dolores', -40, -40, 28, 28, 8);
[$mA2] = desglosePar($p, $cal, 'primera_cita');
$pb = pPlan([$mA2['score'], $mA2['score']], $cal);
ok($pb < 0.42, "muy mala + conflicto fuerte ~37% (obs=" . sprintf('%.3f', $pb) . ")");

// Enemistad (rom tilín residual).
$p = partidaLab();
estadoPar($p, 'sandra', 'dolores', -70, -70, 8, 8, 10);
[$eA] = desglosePar($p, $cal, 'primera_cita');
$pe = pPlan([$eA['score'], $eA['score']], $cal);
ok($pe < 0.25, "enemistad ~21% (obs=" . sprintf('%.3f', $pe) . ")");

// ===================== TIPOS NO AFECTADOS ========================================

// conocerse desconocidos: intacto (48-12+34=70 -> ~68.2%), sin bonus ni mult.
$p = partidaLab();
$cA = VoluntadPonderadaEvaluator::desglose($p, ['tipo' => 'conocerse', 'participantes' => ['x1', 'y2'], 'lugar' => null], 'x1', 'y2', $cal);
$cB = VoluntadPonderadaEvaluator::desglose($p, ['tipo' => 'conocerse', 'participantes' => ['x1', 'y2'], 'lugar' => null], 'y2', 'x1', $cal);
$pc = pPlan([$cA['score'], $cB['score']], $cal);
ok((int) $cA['bonus_primera_cita_reciproca'] === 0 && (float) $cA['conflicto_mult_cita'] === 1.0, 'conocerse: sin knobs de cita');
ok($pc > 0.66 && $pc < 0.70, "conocerse desconocidos intacto ~68.2% (obs=" . sprintf('%.3f', $pc) . ")");

// quedar conocidos neutrales: intacto (48 -> ~49.3%) y conflicto SIN multiplicador.
$p = partidaLab();
estadoPar($p, 'sandra', 'dolores', 0, 0, null, null, 2);
$qA = VoluntadPonderadaEvaluator::desglose($p, ['tipo' => 'quedar', 'participantes' => ['sandra', 'dolores'], 'lugar' => null], 'sandra', 'dolores', $cal);
$qB = VoluntadPonderadaEvaluator::desglose($p, ['tipo' => 'quedar', 'participantes' => ['sandra', 'dolores'], 'lugar' => null], 'dolores', 'sandra', $cal);
$pq = pPlan([$qA['score'], $qB['score']], $cal);
ok((float) $qA['conflicto_mult_cita'] === 1.0 && (int) $qA['mod_conflicto'] === -2, 'quedar: conflicto pesa x1 (-2), no x3');
ok($pq > 0.47 && $pq < 0.51, "quedar neutrales intacto ~49.3% (obs=" . sprintf('%.3f', $pq) . ")");

// tipo cita (pareja): multiplicador SÍ aplica; bonus recíproco NO (es solo primera_cita).
$p = partidaLab();
estadoPar($p, 'sandra', 'dolores', 0, 0, 28, 28, null);
$tA = VoluntadPonderadaEvaluator::desglose($p, ['tipo' => 'cita', 'participantes' => ['sandra', 'dolores'], 'lugar' => null], 'sandra', 'dolores', $cal);
$tR = VoluntadPonderadaEvaluator::desglose($p, ['tipo' => 'cita', 'participantes' => ['sandra', 'dolores'], 'lugar' => null, '_conf_ctx' => true], 'sandra', 'dolores', $cal);
ok((float) $tA['conflicto_mult_cita'] === 3.0, 'cita (pareja): multiplicador de conflicto aplicado');
ok((int) $tA['bonus_primera_cita_reciproca'] === 0, 'cita (pareja): bonus recíproco NO aplica (solo primera_cita)');

echo $fail === 0 ? "\nOK primera_cita_balance_p2\n" : "\nFAIL primera_cita_balance_p2 ($fail)\n";
exit($fail === 0 ? 0 : 1);
