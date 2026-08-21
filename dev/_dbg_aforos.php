<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PlaytestIntegralRunner;
$r = new PlaytestIntegralRunner(dirname(__DIR__));
$ref = new ReflectionClass($r);
$m = $ref->getMethod('secAforos');
$m->setAccessible(true);
$l = $m->invoke($r);
echo json_encode($l, JSON_PRETTY_PRINT) . PHP_EOL;
