<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PlaytestIntegralRunner;
use AquiHayTema\Engine\PlaytestInvariantes;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialBucle;

$root = dirname(__DIR__);
$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$service = new PartidaService($root);
$p = $service->nuevaPartida('playtest_01', 'inv-unit');
$fallos = PlaytestInvariantes::auditar($p, $root);
ok(is_array($fallos), 'invariantes devuelve lista');
ok(!in_array('reloj_dia_invalido:0', $fallos, true), 'día válido al crear');

$j = $service->nuevaPartida('juego_v1', 'tut-unit');
ok(count($j['residentes']) === 3, 'juego_v1 arranca con 3');
ok(!empty(TutorialBucle::vista($j)['activo']), 'tutorial activo');

// Smoke del runner sin sims largas: solo tutorial + no-impl + aforos vía reflexión de secciones ligeras
$runner = new PlaytestIntegralRunner($root);
$ref = new ReflectionClass($runner);
$secA = $ref->getMethod('secTutorial');
$secA->setAccessible(true);
$a = $secA->invoke($runner);
ok(($a['inicial_n'] ?? 0) === 3, 'secTutorial ve 3 iniciales');
ok(($a['crecimiento_a_8'] ?? true) === false, 'crecimiento 3→8 aún no cableado');

$secL = $ref->getMethod('secAforos');
$secL->setAccessible(true);
$l = $secL->invoke($runner);
ok(($l['status'] ?? '') === 'PASS', 'aforos canónicos PASS');

$gateMini = [
    'secciones' => [
        'A_tutorial' => $a,
        'B_llegadas' => ['status' => 'NO_IMPLEMENTADO'],
        'J_marchas' => ['status' => 'NO_IMPLEMENTADO'],
        'L_aforos' => $l,
        'M_integracion' => ['status' => 'PASS'],
        'N_invariantes' => ['status' => 'PASS'],
    ],
];
$ver = $ref->getMethod('veredictoNeni');
$ver->setAccessible(true);
$neni = $ver->invoke($runner, $gateMini);
ok(($neni['veredicto'] ?? '') === 'NO', 'veredicto NO mientras falte 3→8');

echo $failures === 0 ? "OK playtest_integral_gate\n" : "FAIL playtest_integral_gate ($failures)\n";
exit($failures === 0 ? 0 : 1);
