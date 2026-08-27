<?php
declare(strict_types=1);

/**
 * Simulación controlada del ritmo de llegadas (solo motor población, sin narrativa).
 * Ejecutar: php tests/poblacion_ritmo_sim_test.php
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\RngService;

$root = dirname(__DIR__);
$cap = CapacidadViviendas::capObjetivoPoblacionActiva();
$diasMax = 120;
$seeds = ['ritmo-a', 'ritmo-b', 'ritmo-c'];

echo "=== SIM POBLACIÓN (cap objetivo Bloque A = $cap) ===\n\n";

foreach ($seeds as $seed) {
    $partida = [
        'meta' => ['seed' => $seed],
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8, 'minuto_actual' => 0],
        'llegadas' => ['cooldown_hasta_dia' => 0],
    ];
    $n = 3;
    $llegadas = [];
    $diaUltima = 0;
    $partida['llegadas']['cooldown_hasta_dia'] = 3; // gracia post-núcleo (días 1-2 sin ofertas)

    for ($dia = 1; $dia <= $diasMax; $dia++) {
        $partida['reloj']['dia_pueblo'] = $dia;
        if ($dia < (int) ($partida['llegadas']['cooldown_hasta_dia'] ?? 0)) {
            continue;
        }
        if ($n >= $cap) {
            continue;
        }
        $pDia = CandidatoLlegadaEngine::pDiaV3($n);
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if ($tirada >= $pDia) {
            continue;
        }
        $n++;
        $llegadas[] = $dia;
        $gap = CandidatoLlegadaEngine::gapMin($n - 1);
        $rng2 = RngService::fromPartida($partida);
        $jitter = $rng2->nextInt(0, 2);
        $rng2->persistToPartida($partida);
        $partida['llegadas']['cooldown_hasta_dia'] = $dia + $gap + $jitter;
        $diaUltima = $dia;
    }

    $huecoDia = null;
    if ($n >= $cap && $diaUltima > 0) {
        $nHueco = $cap - 1;
        $partida['reloj']['dia_pueblo'] = $diaUltima + 1;
        $partida['llegadas']['cooldown_hasta_dia'] = 0;
        for ($d = $diaUltima + 1; $d <= min($diasMax, $diaUltima + 40); $d++) {
            $partida['reloj']['dia_pueblo'] = $d;
            if ($d < (int) ($partida['llegadas']['cooldown_hasta_dia'] ?? 0)) {
                continue;
            }
            $pDia = CandidatoLlegadaEngine::pDiaV3($nHueco);
            $rng = RngService::fromPartida($partida);
            if ($rng->nextFloat() < $pDia) {
                $huecoDia = $d;
                break;
            }
            $gap = CandidatoLlegadaEngine::gapMin($nHueco);
            $rng2 = RngService::fromPartida($partida);
            $jitter = $rng2->nextInt(0, 2);
            $rng2->persistToPartida($partida);
            $partida['llegadas']['cooldown_hasta_dia'] = $d + $gap + $jitter;
        }
    }

    $primeras = $llegadas === [] ? '—' : (string) $llegadas[0];
    $resumen = implode(',', array_slice($llegadas, 0, 8));
    if (count($llegadas) > 8) {
        $resumen .= ',…';
    }
    echo "seed=$seed | inicio=3 | fin_d$diasMax=$n | 1ª llegada=d$primeras | días con llegada: [$resumen]\n";
    echo "  hueco artificial (cap-1): " . ($huecoDia === null ? 'sin llegada en ventana' : "llegada d$huecoDia") . "\n";
}

echo "\nCurva referencia p_dia/gap:\n";
foreach ([3, 5, 8, 12, 15, 16] as $n) {
    echo "  N=$n → p_dia=" . round(CandidatoLlegadaEngine::pDiaV3($n), 3)
        . ' gap_min=' . CandidatoLlegadaEngine::gapMin($n) . "\n";
}
