<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

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

$cal = CalibracionConfig::load($root);
$service = new PartidaService($root);
$p = $service->nuevaPartida('playtest_01', 'vol-tipo-unit');
$a = 'per_p001';
$b = 'per_p002';
$propK = ['participantes' => [$a, $b], 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria'];
$propQ = ['participantes' => [$a, $b], 'tipo' => 'quedar', 'lugar' => 'lug_cafeteria'];
$propP = ['participantes' => [$a, $b], 'tipo' => 'primera_cita', 'lugar' => 'lug_cafeteria'];

$s0 = VoluntadPonderadaEvaluator::score($p, $propK, $a, $b, $cal);
$cal0 = $cal;
$cal0['voluntad']['mod_tipo']['conocerse'] = 0;
$sZero = VoluntadPonderadaEvaluator::score($p, $propK, $a, $b, $cal0);
ok($sZero === $s0 || VoluntadPonderadaEvaluator::modTipo('conocerse', $cal) !== 0, 'score baseline coherente');

$cal34 = $cal;
$cal34['voluntad']['mod_tipo'] = ['conocerse' => 34, 'quedar' => 0, 'primera_cita' => 0, 'cita' => 0];
ok(VoluntadPonderadaEvaluator::modTipo('conocerse', $cal34) === 34, 'mod conocerse 34');
ok(VoluntadPonderadaEvaluator::modTipo('quedar', $cal34) === 0, 'mod quedar 0');
ok(VoluntadPonderadaEvaluator::modTipo('amistad', $cal34) === 0, 'alias amistad no hereda bonus conocerse');
ok(VoluntadPonderadaEvaluator::modTipo('primera_cita', $cal34) === 0, 'mod primera_cita 0');

$s34 = VoluntadPonderadaEvaluator::score($p, $propK, $a, $b, $cal34);
ok($s34 === min(100, $sZero + 34), 'bonus conocerse suma al score y no a la base global');

RelacionEngine::registrarContacto($p, $a, $b, 'normal', $cal);
RelacionEngine::registrarContacto($p, $b, $a, 'normal', $cal);
$q0 = VoluntadPonderadaEvaluator::score($p, $propQ, $a, $b, $cal0);
$q34 = VoluntadPonderadaEvaluator::score($p, $propQ, $a, $b, $cal34);
ok($q0 === $q34, 'quedar no cambia con bonus de conocerse');

RelacionEngine::setRomanceHacia($p, $a, $b, 22);
$p0s = VoluntadPonderadaEvaluator::score($p, $propP, $a, $b, $cal0);
$p34s = VoluntadPonderadaEvaluator::score($p, $propP, $a, $b, $cal34);
ok($p0s === $p34s, 'primera cita no cambia con bonus de conocerse');

$ev = new VoluntadPonderadaEvaluator($cal34);
$r = $ev->evaluar($p, $propK, $a);
ok(($r['p'] ?? 1) < 1.0, 'nunca 100%');
ok(($r['p'] ?? 0) <= (float) CalibracionConfig::get($cal, 'voluntad.p_max', 0.94) + 0.0001, 'respeta p_max');

$bonoCfg = (int) CalibracionConfig::get($cal, 'voluntad.mod_tipo.conocerse', 0);
ok($bonoCfg > 0, 'calibración tiene bonus conocerse > 0');
ok((int) CalibracionConfig::get($cal, 'voluntad.base', 0) === 48, 'base global intacta');
ok(PropuestaNivel::tiposPermitidos($p, $a, $b, $cal) !== ['conocerse'], 'gates relacionales intactos tras conocerse');

exit($failures > 0 ? 1 : 0);
