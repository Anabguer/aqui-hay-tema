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
for ($n = 8; $n <= 45; $n++) {
    $gap = CandidatoLlegadaEngine::gapMin($n);
    $p = CandidatoLlegadaEngine::pDiaV3($n);
    $et[$n] = $gap + 1 + (1 / $p);
}

ok(abs($et[8] - 6.33) < 0.2, 'E[T] N=8 ~6.3');
ok(abs($et[12] - 11.33) < 0.3, 'E[T] N=12 ~11.3');
ok($et[23] > $et[8], 'curva creciente');
ok($et[45] > $et[23], 'curva sigue creciendo hasta N=45');
ok(abs(CandidatoLlegadaEngine::pDiaV3(45) - 0.055) < 0.001, 'p_dia N=45 mínima');

$sum8_23 = 0.0;
for ($n = 8; $n <= 23; $n++) {
    $sum8_23 += $et[$n];
}
ok(abs($sum8_23 - 245) < 5, 'bloque 8-23 ~245 dias (espíritu curva original)');

$sum8_45 = 0.0;
for ($n = 8; $n <= 45; $n++) {
    $sum8_45 += $et[$n];
}
ok($sum8_45 > 900, 'horizonte 8-45 >> 245 (relleno hasta 46 progresivamente lento)');
ok($sum8_45 < 1400, 'horizonte 8-45 acotado (~1172 días esperados)');

echo $failures === 0 ? "OK llegadas_curva_v3\n" : "FAIL llegadas_curva_v3 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
