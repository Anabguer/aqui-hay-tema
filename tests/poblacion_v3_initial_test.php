<?php
declare(strict_types=1);

/**
 * Tests PoblacionV3: composición mixta obligatoria + edades cercanas.
 *
 * php tests/poblacion_v3_initial_test.php
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PoblacionV3;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\Catalog;

$root = dirname(__DIR__);
$cat = new Catalog($root);
$pool = $cat->listPersonajeIdsJugables();
$meta = PoblacionV3::cargarMetaPool($pool, $root);
$failures = 0;
$tests = 0;

function ok(bool $cond, string $msg): void
{
    global $failures, $tests;
    $tests++;
    if (!$cond) {
        echo "  FAIL: $msg\n";
        $failures++;
    }
}

function info(string $msg): void
{
    echo "  $msg\n";
}

function getMetaMap(array $meta): array
{
    $map = [];
    foreach ($meta as $m) {
        $map[$m['id']] = $m;
    }
    return $map;
}

$metaMap = getMetaMap($meta);

// ============================================================
// TEST 1: 1000 inicializaciones — 0 monogénero
// ============================================================
echo "TEST 1: 1000 inicializaciones — 0 composiciones monogénero\n";
$monogennero = 0;
$combos = [];
for ($i = 0; $i < 1000; $i++) {
    $rng = new RngService("test-p8-$i");
    $picked = PoblacionV3::seleccionarIniciales($pool, 3, $rng, $root, $meta);
    ok(count($picked) === 3, "trio tiene 3 elementos (seed $i)");
    ok(count(array_unique($picked)) === 3, "sin duplicados (seed $i)");

    $generos = array_map(fn($id) => $metaMap[$id]['genero'] ?? '?', $picked);
    $combo = implode('+', $generos);
    $combos[$combo] = ($combos[$combo] ?? 0) + 1;

    if (count(array_unique($generos)) === 1) {
        $monogennero++;
        echo "    MONOGENERO seed $i: $combo\n";
    }
}
ok($monogennero === 0, "0 casos monogénero en 1000 tests (encontrados: $monogennero)");
info("Combos: " . json_encode($combos));

// ============================================================
// TEST 2: 1000 inicializaciones — 0 violaciones rango edad
// ============================================================
echo "\nTEST 2: 1000 inicializaciones — 0 violaciones rango edad (max " . PoblacionV3::MAX_EDAD_DIFF . ")\n";
$violacionesEdad = 0;
$edadesDiffs = [];
for ($i = 0; $i < 1000; $i++) {
    $rng = new RngService("test-edad-$i");
    $picked = PoblacionV3::seleccionarIniciales($pool, 3, $rng, $root, $meta);
    $edades = array_map(fn($id) => $metaMap[$id]['edad'] ?? 0, $picked);
    $diff = max($edades) - min($edades);
    $edadesDiffs[] = $diff;
    if ($diff > PoblacionV3::MAX_EDAD_DIFF) {
        $violacionesEdad++;
        echo "    VIOLACION seed $i: diff=$diff edades=" . implode(',', $edades) . "\n";
    }
}
ok($violacionesEdad === 0, "0 violaciones de rango de edad (encontrados: $violacionesEdad)");
$avgDiff = round(array_sum($edadesDiffs) / count($edadesDiffs), 1);
info("Diff media: $avgDiff, max: " . max($edadesDiffs) . " (umbral: " . PoblacionV3::MAX_EDAD_DIFF . ")");

// ============================================================
// TEST 3: Ambas composiciones aparecen
// ============================================================
echo "\nTEST 3: Ambas composiciones (2F+1M y 2M+1F)\n";
$tiene2f1m = isset($combos['mujer+mujer+hombre']) || isset($combos['mujer+hombre+mujer']) || isset($combos['hombre+mujer+mujer']);
$tiene2m1f = isset($combos['hombre+hombre+mujer']) || isset($combos['hombre+mujer+hombre']) || isset($combos['mujer+hombre+hombre']);
ok($tiene2f1m, "existe 2F+1M");
ok($tiene2m1f, "existe 2M+1F");
info("2F+1M: " . ($combos['mujer+mujer+hombre'] ?? 0) . "+" . ($combos['mujer+hombre+mujer'] ?? 0) . "+" . ($combos['hombre+mujer+mujer'] ?? 0));
info("2M+1F: " . ($combos['hombre+hombre+mujer'] ?? 0) . "+" . ($combos['hombre+mujer+hombre'] ?? 0) . "+" . ($combos['mujer+hombre+hombre'] ?? 0));

// ============================================================
// TEST 4: Variedad de NPC iniciales
// ============================================================
echo "\nTEST 4: Variedad de NPC iniciales\n";
$npcFrecuencia = [];
for ($i = 0; $i < 1000; $i++) {
    $rng = new RngService("test-variedad-$i");
    $picked = PoblacionV3::seleccionarIniciales($pool, 3, $rng, $root, $meta);
    foreach ($picked as $id) {
        $npcFrecuencia[$id] = ($npcFrecuencia[$id] ?? 0) + 1;
    }
}
$npcCount = count($npcFrecuencia);
$maxFrec = max($npcFrecuencia);
$esperado = (1000 * 3) / count($pool);
ok($npcCount >= 50, "al menos 50 NPC distintos (encontrados: $npcCount)");
ok($maxFrec <= $esperado * 3, "sin sesgo extremo (max=$maxFrec, esperado=$esperado)");
info("NPC: $npcCount de " . count($pool) . " (esperado=$esperado, min=" . min($npcFrecuencia) . " max=$maxFrec)");

// ============================================================
// TEST 5: Sin duplicados
// ============================================================
echo "\nTEST 5: Sin duplicados en trío\n";
$dupCount = 0;
for ($i = 0; $i < 1000; $i++) {
    $rng = new RngService("test-dup-$i");
    $picked = PoblacionV3::seleccionarIniciales($pool, 3, $rng, $root, $meta);
    if (count(array_unique($picked)) !== 3) {
        $dupCount++;
    }
}
ok($dupCount === 0, "0 tríos con duplicados");

// ============================================================
// TEST 6: Fallback con pool limitado
// ============================================================
echo "\nTEST 6: Fallback pool pequeño (no puede cumplir contrato)\n";
$smallPool = ['per_p001', 'per_p002', 'per_p003'];
$smallMeta = PoblacionV3::cargarMetaPool($smallPool, $root);
$rng = new RngService("test-fallback");
$picked = PoblacionV3::seleccionarIniciales($smallPool, 3, $rng, $root, $smallMeta);
ok(count($picked) === 3, "fallback devuelve 3 IDs");
ok(count(array_unique($picked)) === 3, "fallback sin duplicados");
$fb = array_map(fn($id) => ($metaMap[$id] ?? ['genero' => '?', 'edad' => 0]), $picked);
info("Fallback: " . implode(', ', array_map(fn($m) => "{$m['genero']}({$m['edad']})", $fb)));

// ============================================================
// TEST 7: PoblacionV3 no toca llegadas
// ============================================================
echo "\nTEST 7: PoblacionV3 no modifica llegadas\n";
$code = file_get_contents($root . '/src/Engine/PoblacionV3.php');
$linLlegadas = preg_grep('/llegadas/', explode("\n", $code));
ok(count($linLlegadas) <= 1, "solo 1 mención de llegadas (tutorial_cola)");
foreach ($linLlegadas as $l) {
    info("  " . trim($l));
}

// ============================================================
// RESUMEN
// ============================================================
echo "\n" . str_repeat('=', 50) . "\n";
echo "RESULTADO: $tests tests, $failures fallos\n";
if ($failures === 0) {
    echo "TODOS LOS TESTS PASAN\n";
} else {
    echo "HAY $failures FALLOS\n";
}
exit($failures > 0 ? 1 : 0);
