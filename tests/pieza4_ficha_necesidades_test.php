<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\NecesidadEstado;

$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

// Test 1: vistaNecesidades returns null when needs are all bien
$runtime1 = ['runtime' => ['necesidades' => [
    'social' => ['valor' => 85, 'banda' => 'bien'],
    'diversion' => ['valor' => 80, 'banda' => 'bien'],
    'actividad' => ['valor' => 90, 'banda' => 'bien'],
    'calma' => ['valor' => 75, 'banda' => 'bien'],
]]];
$partida = ['features' => ['necesidades_enabled' => true]];
// Use reflection to test private method
$method = new ReflectionMethod(\AquiHayTema\Engine\PartidaService::class, 'vistaNecesidades');
$result = $method->invoke(null, $runtime1, $partida);
ok($result === null, 'vistaNecesidades null when all bien');

// Test 2: vistaNecesidades returns items for low needs
$runtime2 = ['runtime' => ['necesidades' => [
    'social' => ['valor' => 20, 'banda' => 'en_rojo'],
    'diversion' => ['valor' => 80, 'banda' => 'bien'],
    'actividad' => ['valor' => 30, 'banda' => 'lo_necesita'],
    'calma' => ['valor' => 85, 'banda' => 'bien'],
]]];
$result2 = $method->invoke(null, $runtime2, $partida);
ok($result2 !== null, 'vistaNecesidades returns data for low needs');
ok(count($result2['items']) === 2, 'Returns 2 items for social+actividad');
$ids = array_column($result2['items'], 'id');
ok(in_array('social', $ids), 'social in items');
ok(in_array('actividad', $ids), 'actividad in items');
ok(!in_array('diversion', $ids), 'diversion not in items');

// Test 3: Each item has required fields
$item = $result2['items'][0];
ok(isset($item['id']), 'Item has id');
ok(isset($item['icono']), 'Item has icono');
ok(isset($item['banda']), 'Item has banda');
ok(isset($item['copy']), 'Item has copy');
ok($item['copy'] !== '', 'Copy is not empty');
ok(isset($item['valor']), 'Item has valor');
ok(is_int($item['valor']), 'valor is integer');
ok($item['valor'] >= 0 && $item['valor'] <= 100, 'valor in 0-100 range');

// Test 4: valor matches input data
$socialItem = null;
foreach ($result2['items'] as $it) {
    if ($it['id'] === 'social') { $socialItem = $it; break; }
}
ok($socialItem !== null, 'social item found');
ok($socialItem['valor'] === 20, 'social valor is 20 (matches input)');
$actividadItem = null;
foreach ($result2['items'] as $it) {
    if ($it['id'] === 'actividad') { $actividadItem = $it; break; }
}
ok($actividadItem !== null, 'actividad item found');
ok($actividadItem['valor'] === 30, 'actividad valor is 30 (matches input)');

// Test 4: Copy narrativo is correct
$socialCopy = NecesidadEstado::copyNecesidad('social', 'en_rojo');
ok($socialCopy !== '', 'Social en_rojo has copy');
$actividadCopy = NecesidadEstado::copyNecesidad('actividad', 'lo_necesita');
ok($actividadCopy !== '', 'Actividad lo_necesita has copy');

echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "{$failures} TESTS FAILED") . "\n";
exit($failures > 0 ? 1 : 0);
