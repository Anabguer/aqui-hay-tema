#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/src/dev_gate.php';

use AquiHayTema\Engine\Catalog;

$cat = new Catalog(dirname(__DIR__));
$ids = $cat->listPersonajeIdsJugables();
$pool = [];
foreach ($ids as $id) {
    $d = $cat->loadPersonajeRaw($id);
    $pool[] = [
        'id' => $id,
        'nombre' => $d['identidad']['nombre'],
        'genero' => $d['identidad']['genero'],
        'edad' => (int) $d['identidad']['edad'],
    ];
}
echo "Total pool: " . count($pool) . "\n\n";

// Histogram
$bins = ['20-24' => 0, '25-29' => 0, '30-34' => 0, '35-39' => 0, '40-44' => 0, '45-49' => 0, '50-54' => 0, '55-59' => 0, '60-64' => 0, '65-69' => 0, '70+' => 0];
foreach ($pool as $c) {
    $e = $c['edad'];
    if ($e < 25) $bins['20-24']++;
    elseif ($e < 30) $bins['25-29']++;
    elseif ($e < 35) $bins['30-34']++;
    elseif ($e < 40) $bins['35-39']++;
    elseif ($e < 45) $bins['40-44']++;
    elseif ($e < 50) $bins['45-49']++;
    elseif ($e < 55) $bins['50-54']++;
    elseif ($e < 60) $bins['55-59']++;
    elseif ($e < 65) $bins['60-64']++;
    elseif ($e < 70) $bins['65-69']++;
    else $bins['70+']++;
}
echo "=== HISTOGRAMA EDADES (5-year bins) ===\n";
foreach ($bins as $k => $v) echo "  $k: $v\n";

// By gender
$mujeres = array_values(array_filter($pool, fn($c) => $c['genero'] === 'mujer'));
$hombres = array_values(array_filter($pool, fn($c) => $c['genero'] === 'hombre'));
$mEdades = array_column($mujeres, 'edad');
$hEdades = array_column($hombres, 'edad');
echo "\n=== POR GENERO ===\n";
echo "Mujeres: " . count($mujeres) . " (min=" . min($mEdades) . " max=" . max($mEdades) . " media=" . round(array_sum($mEdades) / count($mEdades), 1) . ")\n";
echo "Hombres: " . count($hombres) . " (min=" . min($hEdades) . " max=" . max($hEdades) . " media=" . round(array_sum($hEdades) / count($hEdades), 1) . ")\n";

// >= 50
echo "\n=== TODOS >= 50 ANOS ===\n";
$mayores = array_values(array_filter($pool, fn($c) => $c['edad'] >= 50));
usort($mayores, fn($a, $b) => $b['edad'] <=> $a['edad']);
foreach ($mayores as $c) echo "  {$c['id']} {$c['nombre']} ({$c['genero']}) edad={$c['edad']}\n";

// Global stats
$all = array_column($pool, 'edad');
sort($all);
echo "\n=== ESTADISTICAS ===\n";
echo "min=" . min($all) . " max=" . max($all) . " media=" . round(array_sum($all) / count($all), 1) . " mediana=" . $all[(int) floor(count($all) / 2)] . "\n";

// For each threshold, count valid trios
echo "\n=== TRIOS VALIDOS POR UMBRAL DE DIFERENCIA MAXIMA ===\n";
$mEdadMap = [];
$hEdadMap = [];
foreach ($mujeres as $c) $mEdadMap[$c['id']] = $c['edad'];
foreach ($hombres as $c) $hEdadMap[$c['id']] = $c['edad'];
$mIds = array_keys($mEdadMap);
$hIds = array_keys($hEdadMap);

foreach ([10, 12, 15, 18, 20, 22, 25, 30] as $maxDiff) {
    $valid_2m1h = 0;
    $total_m = count($mIds);
    $total_h = count($hIds);
    for ($i = 0; $i < $total_m; $i++) {
        for ($j = $i + 1; $j < $total_m; $j++) {
            $mAge1 = $mEdadMap[$mIds[$i]];
            $mAge2 = $mEdadMap[$mIds[$j]];
            foreach ($hIds as $hid) {
                $ages = [$mAge1, $mAge2, $hEdadMap[$hid]];
                if (max($ages) - min($ages) <= $maxDiff) $valid_2m1h++;
            }
        }
    }
    $valid_2h1m = 0;
    for ($i = 0; $i < $total_h; $i++) {
        for ($j = $i + 1; $j < $total_h; $j++) {
            $hAge1 = $hEdadMap[$hIds[$i]];
            $hAge2 = $hEdadMap[$hIds[$j]];
            foreach ($mIds as $mid) {
                $ages = [$hAge1, $hAge2, $mEdadMap[$mid]];
                if (max($ages) - min($ages) <= $maxDiff) $valid_2h1m++;
            }
        }
    }
    $total = $valid_2m1h + $valid_2h1m;
    echo "  max_diff=$maxDiff: 2M+1H=$valid_2m1h 2H+1M=$valid_2h1m total_trios=$total\n";
}
