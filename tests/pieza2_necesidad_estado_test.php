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

// Test 1: ensureResidente crea estructura
$residente = ['id' => 'test'];
NecesidadEstado::ensureResidente($residente);
ok(isset($residente['runtime']['necesidades']), 'ensureResidente crea runtime.necesidades');
ok(count($residente['runtime']['necesidades']) === 4, 'Crea las 4 necesidades');

// Test 2: Valores iniciales en banda bien
$nec = NecesidadEstado::obtener($residente);
ok($nec['social']['banda'] === 'bien', 'Social inicial en bien');
ok($nec['diversion']['banda'] === 'bien', 'Diversión inicial en bien');
ok($nec['actividad']['banda'] === 'bien', 'Actividad inicial en bien');
ok($nec['calma']['banda'] === 'bien', 'Calma inicial en bien');

// Test 3: Cálculo de bandas
ok(NecesidadEstado::calcularBanda(90) === 'bien', 'Banda bien para 90');
ok(NecesidadEstado::calcularBanda(60) === 'le_vendria_bien', 'Banda le_vendria_bien para 60');
ok(NecesidadEstado::calcularBanda(30) === 'lo_necesita', 'Banda lo_necesita para 30');
ok(NecesidadEstado::calcularBanda(10) === 'en_rojo', 'Banda en_rojo para 10');

// Test 4: Decay reduce valores
$residente2 = ['id' => 'test2'];
NecesidadEstado::ensureResidente($residente2);
$antes = NecesidadEstado::obtener($residente2)['social']['valor'];
NecesidadEstado::aplicarDecay($residente2, []);
$despues = NecesidadEstado::obtener($residente2)['social']['valor'];
ok($despues < $antes, 'Decay reduce social (' . $antes . ' → ' . $despues . ')');

// Test 5: Recuperación sube valores
$residente3 = ['id' => 'test3'];
NecesidadEstado::ensureResidente($residente3);
// Bajar social manualmente
$residente3['runtime']['necesidades']['social']['valor'] = 40;
$residente3['runtime']['necesidades']['social']['banda'] = NecesidadEstado::calcularBanda(40);
$antes3 = NecesidadEstado::obtener($residente3)['social']['valor'];
NecesidadEstado::aplicarRecuperacion($residente3, ['social' => 'principal'], false, false, []);
$despues3 = NecesidadEstado::obtener($residente3)['social']['valor'];
ok($despues3 > $antes3, 'Recuperación sube social (' . $antes3 . ' → ' . $despues3 . ')');

// Test 6: Comp + hobby match mejora o iguala recuperación
$residente4c = ['id' => 'test4c'];
NecesidadEstado::ensureResidente($residente4c);
$residente4c['runtime']['necesidades']['social']['valor'] = 10;
$residente4c['runtime']['necesidades']['social']['banda'] = NecesidadEstado::calcularBanda(10);
NecesidadEstado::aplicarRecuperacion($residente4c, ['social' => 'principal'], true, false, []);
$compSinMatch = NecesidadEstado::obtener($residente4c)['social']['valor'];
$residente4d = ['id' => 'test4d'];
NecesidadEstado::ensureResidente($residente4d);
$residente4d['runtime']['necesidades']['social']['valor'] = 10;
$residente4d['runtime']['necesidades']['social']['banda'] = NecesidadEstado::calcularBanda(10);
NecesidadEstado::aplicarRecuperacion($residente4d, ['social' => 'principal'], true, true, []);
$compConMatch = NecesidadEstado::obtener($residente4d)['social']['valor'];
ok($compConMatch >= $compSinMatch, 'Comp+hobby match >= solo comp (' . $compSinMatch . ' vs ' . $compConMatch . ')');

// Test 7: Recuperación sin lugar no hace nada
$residente5 = ['id' => 'test5'];
NecesidadEstado::ensureResidente($residente5);
$residente5['runtime']['necesidades']['social']['valor'] = 40;
$antes5 = NecesidadEstado::obtener($residente5)['social']['valor'];
NecesidadEstado::aplicarRecuperacion($residente5, [], false, false, []);
$despues5 = NecesidadEstado::obtener($residente5)['social']['valor'];
ok($despues5 === $antes5, 'Sin lugar no hay recuperación');

// Test 8: necesidadesBajas
$residente6 = ['id' => 'test6'];
NecesidadEstado::ensureResidente($residente6);
$residente6['runtime']['necesidades']['social']['valor'] = 20;
$residente6['runtime']['necesidades']['social']['banda'] = NecesidadEstado::calcularBanda(20);
$residente6['runtime']['necesidades']['actividad']['valor'] = 30;
$residente6['runtime']['necesidades']['actividad']['banda'] = NecesidadEstado::calcularBanda(30);
$bajas = NecesidadEstado::necesidadesBajas($residente6);
ok(in_array('social', $bajas), 'Social aparece en bajas');
ok(in_array('actividad', $bajas), 'Actividad aparece en bajas');
ok(!in_array('diversion', $bajas), 'Diversión no aparece en bajas');
ok(!in_array('calma', $bajas), 'Calma no aparece en bajas');

// Test 9: Copy narrativo
ok(NecesidadEstado::copyNecesidad('social', 'en_rojo') !== '', 'Copy social en_rojo no vacío');
ok(NecesidadEstado::copyNecesidad('diversion', 'lo_necesita') !== '', 'Copy diversión lo_necesita no vacío');

// Test 10: Lugar secundaria recupera menos que principal
$residente7 = ['id' => 'test7'];
NecesidadEstado::ensureResidente($residente7);
$residente7['runtime']['necesidades']['social']['valor'] = 40;
NecesidadEstado::aplicarRecuperacion($residente7, ['social' => 'principal'], false, false, []);
$principal = NecesidadEstado::obtener($residente7)['social']['valor'];
$residente7['runtime']['necesidades']['social']['valor'] = 40;
NecesidadEstado::aplicarRecuperacion($residente7, ['social' => 'secundaria'], false, false, []);
$secundaria = NecesidadEstado::obtener($residente7)['social']['valor'];
ok($principal > $secundaria, 'Principal (' . $principal . ') > secundaria (' . $secundaria . ')');

echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "{$failures} TESTS FAILED") . "\n";
exit($failures > 0 ? 1 : 0);
