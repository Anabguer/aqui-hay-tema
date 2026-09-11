<?php
/**
 * SIMULACIÓN: Monopolio de autonomía — Antes vs Después del cap per-NPC
 * Modela 3 residentes con personalidades/emociones diferentes.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\RngService;

const RESIDENTES = ['Silvia', 'Carlos', 'Luna'];
// Silvia: sociable (emoción neutra), Carlos: triste, Luna: poco activa (sin protagonismo reciente)
const PESOS_BASE = [
    'Silvia' => 1.0,       // default
    'Carlos' => 1.0 + 0.8, // triste → +0.8
    'Luna'   => 1.0 * 1.6, // poco activa → *1.6
];
const DIAS = 14;
const HORAS_DIA = 13;
const CAP_NPC = 6;

function simular(bool $conCap, int $seed): array {
    $rng = new RngService("sim_" . ($conCap ? 'cap' : 'nocap') . "_$seed");
    $counts = array_fill_keys(RESIDENTES, 0);
    $acciones_hoy = array_fill_keys(RESIDENTES, 0);
    $ultimo_protagonismo = array_fill_keys(RESIDENTES, 0);
    $poco_activo_bonus = 1.6;

    for ($dia = 1; $dia <= DIAS; $dia++) {
        foreach (RESIDENTES as $r) $acciones_hoy[$r] = 0;

        for ($h = 0; $h < HORAS_DIA; $h++) {
            for ($sys = 0; $sys < 3; $sys++) {
                $pesos = [];
                foreach (RESIDENTES as $r) {
                    if ($conCap && $acciones_hoy[$r] >= CAP_NPC) continue;
                    $w = PESOS_BASE[$r];
                    // poco activo bonus (same as production)
                    if ($ultimo_protagonismo[$r] === 0 || ($dia - $ultimo_protagonismo[$r]) >= 3) {
                        $w *= $poco_activo_bonus;
                    }
                    $pesos[$r] = max(0.05, $w);
                }
                if ($pesos === []) continue;
                $total = array_sum($pesos);
                $pick = $rng->nextFloat() * $total;
                $acc = 0;
                $elegido = null;
                foreach ($pesos as $r => $pw) {
                    $acc += $pw;
                    if ($pick <= $acc) { $elegido = $r; break; }
                }
                if ($elegido === null) $elegido = array_key_last($pesos);
                $counts[$elegido]++;
                $acciones_hoy[$elegido]++;
                $ultimo_protagonismo[$elegido] = $dia;
            }
        }
    }
    return $counts;
}

echo "=== SIMULACIÓN: Distribución de Autonomía (3 residentes, " . DIAS . " días) ===\n\n";
echo "Residentes:\n";
echo "  Silvia: sociable, emoción neutra (peso base 1.0)\n";
echo "  Carlos: triste (peso +0.8)\n";
echo "  Luna:   poco activa, sin protagonismo reciente (peso *1.6)\n";
echo "Cap per-NPC: " . CAP_NPC . " acciones/día\n\n";

$seeds = [42, 137, 256, 789, 1001, 2048, 3141, 9999];
$nSeeds = count($seeds);

echo "SIN CAP (antes — situación de playtest):\n";
echo str_repeat("-", 60) . "\n";
$acum = array_fill_keys(RESIDENTES, 0);
foreach ($seeds as $s) {
    $r = simular(false, $s);
    foreach ($r as $k => $v) $acum[$k] += $v;
}
$total = array_sum($acum);
$maxPct = 0;
foreach ($acum as $k => $v) {
    $avg = round($v / $nSeeds, 1);
    $pct = round($avg / ($total / $nSeeds) * 100, 1);
    echo sprintf("  %-10s: %6.1f avg (%5.1f%%)\n", $k, $avg, $pct);
    if ($pct > $maxPct) $maxPct = $pct;
}
echo sprintf("  Max share: %.1f%%\n", $maxPct);

echo "\nCON CAP (después — con protección per-NPC):\n";
echo str_repeat("-", 60) . "\n";
$acum2 = array_fill_keys(RESIDENTES, 0);
foreach ($seeds as $s) {
    $r = simular(true, $s);
    foreach ($r as $k => $v) $acum2[$k] += $v;
}
$total2 = array_sum($acum2);
$maxPct2 = 0;
foreach ($acum2 as $k => $v) {
    $avg = round($v / $nSeeds, 1);
    $pct = round($avg / ($total2 / $nSeeds) * 100, 1);
    echo sprintf("  %-10s: %6.1f avg (%5.1f%%)\n", $k, $avg, $pct);
    if ($pct > $maxPct2) $maxPct2 = $pct;
}
echo sprintf("  Max share: %.1f%%\n", $maxPct2);

echo "\n=== RESUMEN ===\n";
echo sprintf("  Sin cap: max=%.1f%%, ratio max/min=%.1fx\n",
    $maxPct,
    max(array_map(fn($v) => $v / $total * 100, $acum)) /
    max(0.1, min(array_map(fn($v) => $v / $total * 100, $acum)))
);
echo sprintf("  Con cap: max=%.1f%%, ratio max/min=%.1fx\n",
    $maxPct2,
    max(array_map(fn($v) => $v / $total2 * 100, $acum2)) /
    max(0.1, min(array_map(fn($v) => $v / $total2 * 100, $acum2)))
);
echo "\nLos pesos de personalidad/emoción siguen generando variedad.\n";
echo "El cap previene que un solo NPC monopolice las 390 slots posibles.\n";
