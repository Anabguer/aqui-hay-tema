<?php
declare(strict_types=1);

/**
 * CIERRE E2E Nº3 — ROMANCE AUTÓNOMO — FASE 3.2
 * Simulación con CONFIG NORMAL REAL (lab_vida_activa=false).
 * 
 * En producción, lab_vida_activa NO existe → empty()返回 true → enPlay = true
 * → familias_en_play FILTRA → romance_hito y pareja EXCLUIDOS
 * 
 * Replicamos esto: ponemos lab_vida_activa=false para activar el filtro.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorPuebloVivo;

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);

$nRes = 8;
$nSeeds = 5;

echo "=== ROMANCE — CONFIG NORMAL REAL (lab_vida_activa=false) ===\n";
echo "familias_en_play: trabajo, ocio, romance, consejo, romance_accion\n";
echo "EXCLUIDOS: romance_hito (declaracion), pareja (crisis/ruptura/reconciliacion)\n";
echo "Población: $nRes residentes, $nSeeds seeds\n\n";

// First: confirm what the effective config is
$familiasPlay = $cal['acontecimientos_dia']['familias_en_play'] ?? [];
echo "calibracion_vida.json familias_en_play: " . json_encode($familiasPlay) . "\n";
echo "pesos_familias: " . json_encode($cal['acontecimientos_dia']['pesos_familias'] ?? []) . "\n";
echo "edad.limite_duro_anos: " . ($cal['edad']['limite_duro_anos'] ?? 'DEFAULT(10)') . "\n";
echo "romance.cortes.tilin: " . ($cal['romance']['cortes']['tilin'] ?? 'DEFAULT(8)') . "\n\n";

// --- LAB simulation (lab_vida_activa=true) ---
echo "========================================\n";
echo "  CONFIG A: LAB (lab_vida_activa=true)\n";
echo "  familias_en_play NO aplica\n";
echo "========================================\n";
$labResults = [];
foreach ([10, 15, 20, 30] as $dias) {
    $labResults[$dias] = [];
    for ($s = 0; $s < $nSeeds; $s++) {
        $rng = new RngService("rom-lab-$nRes-$dias-$s");
        $partida = SimuladorPuebloVivo::pueblo($nRes, $rng, $cal, $catalog);
        // LAB: lab_vida_activa=true (default from pueblo())
        $partida['lab_vida_activa'] = true;
        $m = SimuladorPuebloVivo::correr($partida, $catalog, $cal, $rng, $dias);
        $labResults[$dias][] = $m;
    }
    printMetrics($labResults[$dias], $dias, $nRes);
}

// --- PRODUCTION simulation (lab_vida_activa=false) ---
echo "\n========================================\n";
echo "  CONFIG B: NORMAL REAL (lab_vida_activa=false)\n";
echo "  familias_en_play FILTRA → romance_hito/pareja EXCLUIDOS\n";
echo "========================================\n";
$prodResults = [];
foreach ([10, 15, 20, 30, 60] as $dias) {
    $prodResults[$dias] = [];
    for ($s = 0; $s < $nSeeds; $s++) {
        $rng = new RngService("rom-prod-$nRes-$dias-$s");
        $partida = SimuladorPuebloVivo::pueblo($nRes, $rng, $cal, $catalog);
        // PRODUCTION: lab_vida_activa=false → enPlay triggers familia filter
        $partida['lab_vida_activa'] = false;
        $m = SimuladorPuebloVivo::correr($partida, $catalog, $cal, $rng, $dias);
        $prodResults[$dias][] = $m;
    }
    printMetrics($prodResults[$dias], $dias, $nRes);
}

// --- AGE COMPATIBILITY ANALYSIS ---
echo "\n========================================\n";
echo "  ANÁLISIS DE EDAD\n";
echo "========================================\n";
foreach ([8, 12, 16] as $nResAge) {
    $rng = new RngService("rom-age-$nResAge-0");
    $partida = SimuladorPuebloVivo::pueblo($nResAge, $rng, $cal, $catalog);
    $ages = [];
    foreach ($partida['residentes'] as $id => $r) {
        $ages[$id] = (int) ($r['runtime']['perfil_partida']['edad'] ?? 0);
    }
    asort($ages);
    echo "Población $nResAge: " . implode(', ', array_map(fn($id, $age) => "$id=$age", array_keys($ages), array_values($ages))) . "\n";
    
    $duro = (int) CalibracionConfig::get($cal, 'edad.limite_duro_anos', 10);
    $total = 0;
    $compat = 0;
    $ids = array_keys($ages);
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $total++;
            $delta = abs($ages[$ids[$i]] - $ages[$ids[$j]]);
            if ($delta <= $duro) {
                $compat++;
            }
        }
    }
    echo "  Pares: $total, Compatibles (≤${duro}a): $compat (" . ($total > 0 ? round(100 * $compat / $total, 1) : 0) . "%)\n";
    // Show distribution
    $deltas = [];
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $deltas[] = abs($ages[$ids[$i]] - $ages[$ids[$j]]);
        }
    }
    sort($deltas);
    $median = $deltas[(int)(count($deltas) / 2)] ?? 0;
    $max = end($deltas) ?? 0;
    echo "  Deltas: min=" . ($deltas[0] ?? 0) . " median=$median max=$max\n";
}

function printMetrics(array $results, int $dias, int $nRes): void {
    $nSeeds = count($results);
    $pares = $nRes * ($nRes - 1) / 2;
    echo "--- D$dias ($nSeeds seeds) ---\n";
    
    $avg = fn(array $v) => $v === [] ? 0 : round(array_sum($v) / count($v), 1);
    
    // Flechazos from hitos
    $flech = array_map(fn($m) => (float)($m['hitos']['flechazo'] ?? 0), $results);
    echo "  Flechazos:              " . $avg($flech) . "\n";
    
    // Primera_cita from hitos
    $pc = array_map(fn($m) => (float)($m['hitos']['primera_cita'] ?? 0), $results);
    echo "  Primera_cita:           " . $avg($pc) . "\n";
    
    // Inicio_pareja from hitos
    $ip = array_map(fn($m) => (float)($m['hitos']['inicio_pareja'] ?? 0), $results);
    echo "  Inicio_pareja:          " . $avg($ip) . "\n";
    
    // Vuelta from hitos
    $vu = array_map(fn($m) => (float)($m['hitos']['vuelta'] ?? 0), $results);
    echo "  Vuelta:                 " . $avg($vu) . "\n";
    
    // Crisis from hitos
    $cr = array_map(fn($m) => (float)($m['hitos']['crisis'] ?? 0), $results);
    echo "  Crisis:                 " . $avg($cr) . "\n";
    
    // Ruptura from hitos
    $ru = array_map(fn($m) => (float)($m['hitos']['ruptura'] ?? 0), $results);
    echo "  Rupturas:               " . $avg($ru) . "\n";
    
    // Regalo / mandar_flores hitos
    $rf = array_map(fn($m) => (float)($m['hitos']['regalo'] ?? 0), $results);
    echo "  Regalo/plan:            " . $avg($rf) . "\n";
    
    // Parejas activas final
    $pa = array_map(fn($m) => (float)($m['parejas_activas_final'] ?? 0), $results);
    echo "  Parejas activas final:  " . $avg($pa) . "\n";
    
    // Rechazos
    $rec = array_map(fn($m) => (float)($m['rechazos'] ?? 0), $results);
    echo "  Rechazos:               " . $avg($rec) . "\n";
    
    // Duración media pareja
    $dm = array_map(fn($m) => (float)($m['duracion_media_pareja'] ?? 0), $results);
    echo "  Duración media pareja:  " . $avg($dm) . "\n";
    
    // Max declaraciones mismo par
    $md = array_map(fn($m) => (float)($m['max_declaraciones_mismo_par'] ?? 0), $results);
    echo "  Max decl mismo par:     " . $avg($md) . "\n";
    
    // Romance max (from first seed as example)
    $first = $results[0] ?? [];
    echo "  Hitos ejemplo (seed 0): " . json_encode($first['hitos'] ?? []) . "\n";
    echo "  Familias ejemplo:       " . json_encode($first['familias'] ?? []) . "\n";
}
