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

// Test 1: NecesidadEstado::obtenerUna works with resident
$residente = ['id' => 'test'];
NecesidadEstado::ensureResidente($residente);
$nec = NecesidadEstado::obtenerUna($residente, 'social');
ok($nec['banda'] === 'bien', 'Social starts in bien');
ok($nec['valor'] === 85, 'Social starts at 85');

// Test 2: Can simulate low need and check band
$residente['runtime']['necesidades']['social']['valor'] = 20;
$residente['runtime']['necesidades']['social']['banda'] = NecesidadEstado::calcularBanda(20);
$nec2 = NecesidadEstado::obtenerUna($residente, 'social');
ok($nec2['banda'] === 'en_rojo', 'Social at 20 is en_rojo');

// Test 3: LugarAutonomo loads places with needs
$root = dirname(__DIR__);
$catalog = new \AquiHayTema\Engine\Catalog($root);
$lugares = $catalog->loadLugares();
$gimnasio = null;
foreach ($lugares['items'] as $lug) {
    if (($lug['id'] ?? '') === 'lug_gimnasio') {
        $gimnasio = $lug;
        break;
    }
}
ok($gimnasio !== null, 'Gimnasio found');
ok(isset($gimnasio['necesidades']), 'Gimnasio has needs field');
ok($gimnasio['necesidades']['actividad'] === 'principal', 'Gimnasio actividad is principal');

// Test 4: NecesidadEstado.all IDs are correct
ok(count(NecesidadEstado::TODAS) === 4, '4 needs defined');
ok(in_array('social', NecesidadEstado::TODAS), 'social in TODAS');
ok(in_array('diversion', NecesidadEstado::TODAS), 'diversion in TODAS');
ok(in_array('actividad', NecesidadEstado::TODAS), 'actividad in TODAS');
ok(in_array('calma', NecesidadEstado::TODAS), 'calma in TODAS');

echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "{$failures} TESTS FAILED") . "\n";
exit($failures > 0 ? 1 : 0);
