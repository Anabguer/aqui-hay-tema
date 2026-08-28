<?php
declare(strict_types=1);
/**
 * Simulación rápida motor puro (sin PartidaService/DB).
 * php dev/_sim_llegadas_motor.php [--seeds=500] [--legacy=1]
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\RngService;

$numSeeds = 500;
$legacy = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--seeds=')) {
        $numSeeds = max(10, (int) substr($arg, 8));
    }
    if ($arg === '--legacy=1') {
        $legacy = true;
    }
}

function pDiaLegacy(int $n): float
{
    $cap = CapacidadViviendas::capObjetivoPoblacionActiva();
    $h = max(0, $cap - $n);
    return min(0.30, 0.04 + 0.015 * $h);
}

function gapLegacy(int $n): int
{
    $cap = CapacidadViviendas::capObjetivoPoblacionActiva();
    $n = max(3, min($cap - 1, $n));
    return 2 + (int) floor(($n - 3) * 1.25);
}

function pct(array $xs, float $p): float
{
    sort($xs);
    $idx = (int) floor(($p / 100.0) * (count($xs) - 1));
    return (float) $xs[$idx];
}

$checkpoints = [3, 5, 7, 10, 15, 20, 30];
$byDay = array_fill_keys($checkpoints, []);

for ($s = 0; $s < $numSeeds; $s++) {
    $partida = [
        'meta' => ['seed' => 'sim-' . $s],
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8, 'minuto_actual' => 0],
        'llegadas' => [
            'modo' => 'normal',
            'normal_desde_dia' => 1,
            'cooldown_hasta_dia' => 3,
            'dias_sin_oferta' => 0,
            'ultimo_dia_intento_pity' => 0,
        ],
    ];
    $n = 3;
    for ($dia = 1; $dia <= 30; $dia++) {
        $partida['reloj']['dia_pueblo'] = $dia;
        if ($dia < (int) ($partida['llegadas']['cooldown_hasta_dia'] ?? 0) || $n >= 16) {
            $byDay[$dia][] = $n;
            continue;
        }
        $diasSin = (int) ($partida['llegadas']['dias_sin_oferta'] ?? 0);
        if ($legacy) {
            $pDia = pDiaLegacy($n);
            $forzar = false;
            $pEff = $pDia;
        } else {
            $pEff = CandidatoLlegadaEngine::pDiaEfectiva($n, $diasSin);
            $forzar = CandidatoLlegadaEngine::forzarOfertaPorPity($n, $diasSin);
        }
        $rng = RngService::fromPartida($partida);
        $ok = $forzar || $rng->nextFloat() < $pEff;
        $rng->persistToPartida($partida);
        if ($ok) {
            $n++;
            $partida['llegadas']['dias_sin_oferta'] = 0;
            $partida['llegadas']['ultimo_dia_intento_pity'] = $dia;
            $gap = $legacy ? gapLegacy($n - 1) : CandidatoLlegadaEngine::gapMin($n - 1);
            $rng2 = RngService::fromPartida($partida);
            $jitter = $rng2->nextInt(0, 2);
            $rng2->persistToPartida($partida);
            $partida['llegadas']['cooldown_hasta_dia'] = $dia + $gap + $jitter;
        } else {
            $ultimo = (int) ($partida['llegadas']['ultimo_dia_intento_pity'] ?? 0);
            if ($dia > $ultimo) {
                $partida['llegadas']['dias_sin_oferta'] = $diasSin + 1;
                $partida['llegadas']['ultimo_dia_intento_pity'] = $dia;
            }
        }
        $byDay[$dia][] = $n;
    }
}

$label = $legacy ? 'ANTES (curva legacy)' : 'DESPUÉS (curva + pity)';
echo "=== $label seeds=$numSeeds ===\n";
foreach ($checkpoints as $d) {
    $xs = $byDay[$d];
    sort($xs);
    $still3 = count(array_filter($xs, static fn ($v) => $v === 3));
    echo sprintf(
        "  d%2d: med=%.1f p25=%d p75=%d min=%d max=%d | aún_3=%.1f%%\n",
        $d,
        array_sum($xs) / count($xs),
        (int) pct($xs, 25),
        (int) pct($xs, 75),
        $xs[0],
        $xs[count($xs) - 1],
        round(100 * $still3 / count($xs), 1)
    );
}
