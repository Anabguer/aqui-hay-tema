#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/src/dev_gate.php';

use AquiHayTema\Engine\PoblacionV3;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\Catalog;

if (!aht_dev_enabled()) { putenv('AHT_DEV=1'); }

$root = dirname(__DIR__);
$cat = new Catalog($root);
$pool = $cat->listPersonajeIdsJugables();
$meta = PoblacionV3::cargarMetaPool($pool, $root);

$N = 1500;
$combos = [];
$monogennero = 0;
$violacionesEdad = 0;
$edadesDiffs = [];
$edadesAvg = [];
$npcFrecuencia = [];
$tiene2f1m = false;
$tiene2m1f = false;

for ($i = 0; $i < $N; $i++) {
    $rng = new RngService("sim-postfix-$i");
    $picked = PoblacionV3::seleccionarIniciales($pool, 3, $rng, $root, $meta);

    $metas = [];
    foreach ($picked as $id) {
        foreach ($meta as $m) {
            if ($m['id'] === $id) { $metas[] = $m; break; }
        }
    }
    $generos = array_map(fn($m) => $m['genero'], $metas);
    $edades = array_map(fn($m) => (int) $m['edad'], $metas);
    $combo = implode('+', $generos);
    $combos[$combo] = ($combos[$combo] ?? 0) + 1;

    if (count(array_unique($generos)) === 1) { $monogennero++; }
    $diff = max($edades) - min($edades);
    $edadesDiffs[] = $diff;
    if ($diff > PoblacionV3::MAX_EDAD_DIFF) { $violacionesEdad++; }
    $edadesAvg[] = array_sum($edades) / count($edades);
    foreach ($picked as $id) { $npcFrecuencia[$id] = ($npcFrecuencia[$id] ?? 0) + 1; }

    $g2m1h = in_array($combo, ['mujer+mujer+hombre','mujer+hombre+mujer','hombre+mujer+mujer'], true);
    $g2h1m = in_array($combo, ['hombre+hombre+mujer','hombre+mujer+hombre','mujer+hombre+hombre'], true);
    if ($g2m1h) $tiene2f1m = true;
    if ($g2h1m) $tiene2m1f = true;
}

$avgDiff = round(array_sum($edadesDiffs) / $N, 1);
$maxDiff = max($edadesDiffs);
$minDiff = min($edadesDiffs);
$avgEdad = round(array_sum($edadesAvg) / $N, 1);
$npcCount = count($npcFrecuencia);
$esperado = ($N * 3) / $npcCount;

echo "=== POST-FIX: SIMULACION {$N} PARTIDAS ===\n\n";
echo "COMPOSICION DE GENERO:\n";
echo "  Total: $N\n";
echo "  Monogennero: $monogennero (0 esperado)\n";
echo "  Combos: " . json_encode($combos, JSON_UNESCAPED_UNICODE) . "\n";
echo "  2F+1M: " . ($tiene2f1m ? 'SI' : 'NO') . "\n";
echo "  2H+1F: " . ($tiene2m1f ? 'SI' : 'NO') . "\n\n";

echo "EDADES:\n";
echo "  Violaciones (> " . PoblacionV3::MAX_EDAD_DIFF . "): $violacionesEdad\n";
echo "  Diff media: $avgDiff\n";
echo "  Diff min: $minDiff  max: $maxDiff\n";
echo "  Edad media de trio: $avgEdad\n\n";

echo "VARIEDAD:\n";
echo "  NPC distintos: $npcCount de " . count($meta) . "\n";
echo "  Frecuencia esperada: " . round($esperado, 1) . "\n";
echo "  Frecuencia min: " . min($npcFrecuencia) . " max: " . max($npcFrecuencia) . "\n";
$maxRatio = round(max($npcFrecuencia) / $esperado, 2);
echo "  Ratio max/esperado: {$maxRatio}x\n\n";

$pass = true;
if ($monogennero > 0) { echo "FAIL: monogennero=$monogennero\n"; $pass = false; }
if ($violacionesEdad > 0) { echo "FAIL: violaciones_edad=$violacionesEdad\n"; $pass = false; }
if (!$tiene2f1m) { echo "FAIL: sin 2F+1M\n"; $pass = false; }
if (!$tiene2m1f) { echo "FAIL: sin 2H+1F\n"; $pass = false; }
if ($pass) {
    echo "TODAS LAS VALIDACIONES PASAN\n";
} else {
    echo "HAY FALLOS\n";
    exit(1);
}
