<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\InteraccionCasual;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;

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

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'casual-dedup-' . time());
$cal = CalibracionConfig::load($root);
$a = array_key_first($p['residentes'] ?? []);
$b = null;
foreach (array_keys($p['residentes'] ?? []) as $rid) {
    if ($rid !== $a) {
        $b = $rid;
        break;
    }
}
if (!is_string($a) || !is_string($b)) {
    echo "FAIL: sin par\n";
    exit(1);
}

$p['rng'] = ['seed' => 'dedup-test', 'state' => 0];
$rng = RngService::fromPartida($p);
$dia = 3;
$hora = 11;
$lugar = 'lug_cafeteria';

$antes = InteraccionCasual::resolverGrupo($p, [$a, $b], $lugar, $dia, $hora, $rng, $cal, $svc->getCatalog());
$segundo = InteraccionCasual::resolverGrupo($p, [$a, $b], $lugar, $dia, $hora, $rng, $cal, $svc->getCatalog());
ok(count($antes) <= 1, 'primer intento como maximo una interaccion');
ok($segundo === [], 'segundo intento misma hora/lugar/par bloqueado por dedup');

$prob = InteraccionCasual::probabilidadPar($p, $a, $b, $cal, 0.18);
ok($prob >= 0.32 && $prob <= 0.85, 'probabilidadPar integra bonus extra y tope');

exit($failures > 0 ? 1 : 0);
