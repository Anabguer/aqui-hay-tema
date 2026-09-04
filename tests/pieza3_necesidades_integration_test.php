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

// Test 1: ensureResidente from partida context
$partida = [
    'residentes' => [
        'per_p001' => ['id' => 'per_p001', 'runtime' => []],
        'per_p002' => ['id' => 'per_p002'],
    ],
];
foreach ($partida['residentes'] as &$res) {
    NecesidadEstado::ensureResidente($res);
}
unset($res);

ok(isset($partida['residentes']['per_p001']['runtime']['necesidades']), 'per_p001 tiene necesidades');
ok(isset($partida['residentes']['per_p002']['runtime']['necesidades']), 'per_p002 tiene necesidades');
ok(count($partida['residentes']['per_p001']['runtime']['necesidades']) === 4, 'per_p001 tiene 4 necesidades');

// Test 2: Necesidades from catalog load correctly
require_once dirname(__DIR__) . '/src/Engine/CatalogStore.php';
$store = new \AquiHayTema\Engine\CatalogStore(dirname(__DIR__));
$necesidades = $store->items('necesidades');
ok(count($necesidades) === 4, 'Catálogo tiene 4 necesidades');
$ids = array_column($necesidades, 'id');
ok(in_array('social', $ids), 'social en catálogo');
ok(in_array('diversion', $ids), 'diversion en catálogo');
ok(in_array('actividad', $ids), 'actividad en catálogo');
ok(in_array('calma', $ids), 'calma en catálogo');

// Test 3: Place needs field works
$root = dirname(__DIR__);
$json = \AquiHayTema\Engine\JsonFile::read($root . '/data/lugares/lugares.json');
$gimnasio = null;
foreach ($json['items'] as $lug) {
    if (($lug['id'] ?? '') === 'lug_gimnasio') {
        $gimnasio = $lug;
        break;
    }
}
ok($gimnasio !== null, 'Gimnasio encontrado');
ok(($gimnasio['necesidades']['actividad'] ?? '') === 'principal', 'Gimnasio: actividad principal');
ok(($gimnasio['necesidades']['diversion'] ?? '') === 'secundaria', 'Gimnasio: diversión secundaria');

// Test 4: Non-canonical place has no needs field
$arcade = null;
foreach ($json['items'] as $lug) {
    if (($lug['id'] ?? '') === 'lug_arcade') {
        $arcade = $lug;
        break;
    }
}
ok($arcade !== null, 'Arcade encontrado en JSON');
ok(!isset($arcade['necesidades']), 'Arcade no tiene campo necesidades');

echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "{$failures} TESTS FAILED") . "\n";
exit($failures > 0 ? 1 : 0);
