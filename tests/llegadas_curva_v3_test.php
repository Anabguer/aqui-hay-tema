<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;

$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$et = [];
for ($n = 8; $n <= 23; $n++) {
    $gap = CandidatoLlegadaEngine::gapMin($n);
    $p = CandidatoLlegadaEngine::pDiaV3($n);
    $et[$n] = $gap + 1 + (1 / $p);
}

ok(abs($et[8] - 6.571) < 0.2, 'E[T] N=8 ~6.6');
ok(abs($et[12] - 12.545) < 0.3, 'E[T] N=12 ~12.5');
ok($et[23] > $et[8], 'curva creciente');

$sum = 0.0;
for ($n = 8; $n <= 23; $n++) {
    $sum += $et[$n];
}
ok(abs($sum - 312) < 5, 'horizonte 8-24 ~312 dias');

echo $failures === 0 ? "OK llegadas_curva_v3\n" : "FAIL llegadas_curva_v3 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
