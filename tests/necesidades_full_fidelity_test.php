<?php
declare(strict_types=1);

/**
 * Simulación full-fidelity de necesidades: usa el ciclo real del juego.
 * MotorVidaDiaria, encuentros, lugares, recuperación real.
 * 5 seeds × 30 días + métricas detalladas.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\NecesidadEstado;
use AquiHayTema\Engine\PartidaLifecycle;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorMisionesDiarias;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);

$seeds = ['nec-full-01', 'nec-full-02', 'nec-full-03', 'nec-full-04', 'nec-full-05'];
$maxDias = 30;
$marcas = [0, 1, 2, 3, 5, 10, 15, 20, 30];

// Acumuladores globales
$globalStats = []; // dia => nec => [vals]
$globalRecuperaciones = []; // seed => dia => nec => count
$globalDecays = []; // seed => dia => count

foreach ($seeds as $si => $seed) {
    echo "=== SEED $seed ===\n";

    $rng = new RngService($seed);
    $p = SimuladorMisionesDiarias::partidaLab(8, $rng, $cal);
    unset($p['_lab_misiones_b3']);
    $p['features'] = [
        'necesidades_enabled' => true,
        'encuentros_enabled' => true,
        'npc_autonomy_enabled' => true,
        'vida_pueblo_enabled' => false,
        'mensajitos_espontaneos_enabled' => false,
        'buzon_enabled' => false,
        'misiones_diarias_enabled' => false,
        'peticiones_pueblo_enabled' => false,
    ];
    $p['reloj']['hora_actual'] = 9;
    $p['reloj']['minuto_actual'] = 0;

    // Asegurar necesidades
    foreach ($p['residentes'] as &$res) {
        NecesidadEstado::ensureResidente($res, $p['reloj']);
    }
    unset($res);

    $rids = array_keys($p['residentes']);
    $relojOps = new RelojOperations($root);

    // Contadores de recuperación
    $recCounts = array_fill_keys(NecesidadEstado::TODAS, 0);

    // Estado anterior para detectar recuperaciones
    $antesNec = [];
    foreach ($rids as $rid) {
        $antesNec[$rid] = NecesidadEstado::obtener($p['residentes'][$rid]);
    }

    for ($dia = 0; $dia <= $maxDias; $dia++) {
        // Checkpoint
        if (in_array($dia, $marcas, true)) {
            $stats = [];
            foreach (NecesidadEstado::TODAS as $nec) {
                $vals = [];
                foreach ($rids as $rid) {
                    $n = NecesidadEstado::obtener($p['residentes'][$rid]);
                    $vals[] = $n[$nec]['valor'];
                }
                sort($vals);
                $stats[$nec] = [
                    'min' => min($vals),
                    'max' => max($vals),
                    'avg' => round(array_sum($vals) / count($vals), 1),
                    'median' => $vals[(int)(count($vals) / 2)],
                    'vals' => $vals,
                ];
            }

            // Vectores únicos
            $vectores = [];
            foreach ($rids as $rid) {
                $n = NecesidadEstado::obtener($p['residentes'][$rid]);
                $vectores[] = implode(',', array_map(function($x) { return $x['valor']; }, $n));
            }
            $uniqueCount = count(array_unique($vectores));

            // Todos en 0
            $allZero = 0;
            foreach ($rids as $rid) {
                $n = NecesidadEstado::obtener($p['residentes'][$rid]);
                $all = true;
                foreach ($n as $v) { if ($v['valor'] > 0) { $all = false; break; } }
                if ($all) $allZero++;
            }

            echo "  Día $dia:\n";
            foreach (NecesidadEstado::TODAS as $nec) {
                $s = $stats[$nec];
                $pct0 = round(100 * count(array_filter($s['vals'], function($v) { return $v === 0; })) / count($s['vals']), 0);
                $pct25 = round(100 * count(array_filter($s['vals'], function($v) { return $v < 25; })) / count($s['vals']), 0);
                $pct50 = round(100 * count(array_filter($s['vals'], function($v) { return $v >= 25 && $v <= 50; })) / count($s['vals']), 0);
                $pct75 = round(100 * count(array_filter($s['vals'], function($v) { return $v > 50 && $v <= 75; })) / count($s['vals']), 0);
                $pct100 = round(100 * count(array_filter($s['vals'], function($v) { return $v > 75; })) / count($s['vals']), 0);
                echo "    $nec: min={$s['min']} avg={$s['avg']} max={$s['max']} | 0:{$pct0}% <25:{$pct25}% 25-50:{$pct50}% 50-75:{$pct75}% >75:{$pct100}%\n";
            }
            echo "    vectores_unicos=$uniqueCount all_zero=$allZero\n";
        }

        // Avanzar 24 horas (día completo)
        if ($dia < $maxDias) {
            // Guardar estado antes
            $antesNec = [];
            foreach ($rids as $rid) {
                $antesNec[$rid] = NecesidadEstado::obtener($p['residentes'][$rid]);
            }

            // Avanzar hora por hora
            for ($h = 0; $h < 24; $h++) {
                $relojOps->avanzar($p, 1);
            }

            // Detectar recuperaciones comparando antes/después
            foreach ($rids as $rid) {
                $despuesNec = NecesidadEstado::obtener($p['residentes'][$rid]);
                foreach (NecesidadEstado::TODAS as $nec) {
                    if ($despuesNec[$nec]['valor'] > $antesNec[$rid][$nec]['valor']) {
                        $recCounts[$nec]++;
                    }
                }
            }
        }
    }

    echo "\n  Recuperaciones totales (30 días): ";
    foreach (NecesidadEstado::TODAS as $nec) {
        echo "$nec={$recCounts[$nec]} ";
    }
    echo "\n\n";
}

echo "=== RESUMEN GLOBAL ===\n";
echo "Simulación completada: " . count($seeds) . " seeds × $maxDias días\n";
echo "Usa MotorVidaDiaria real + encuentros + lugares + recuperación\n";
