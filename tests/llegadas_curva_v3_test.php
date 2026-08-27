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

$cap = CapacidadViviendas::capObjetivoPoblacionActiva();
ok($cap === 16, 'cap objetivo Bloque A = 16');

ok(CandidatoLlegadaEngine::gapMin(3) === 2, 'gap_min N=3');
ok(CandidatoLlegadaEngine::gapMin(8) === 8, 'gap_min N=8');
ok(CandidatoLlegadaEngine::gapMin(12) === 13, 'gap_min N=12');
ok(CandidatoLlegadaEngine::gapMin(15) === 17, 'gap_min N=15');
ok(abs(CandidatoLlegadaEngine::pDiaV3(3) - 0.235) < 0.001, 'p_dia N=3');
ok(abs(CandidatoLlegadaEngine::pDiaV3(8) - 0.16) < 0.001, 'p_dia N=8');
ok(abs(CandidatoLlegadaEngine::pDiaV3(15) - 0.055) < 0.001, 'p_dia N=15');
ok(abs(CandidatoLlegadaEngine::pDiaV3(16) - 0.04) < 0.001, 'p_dia N=16 (sin huecos)');

$et = [];
for ($n = 3; $n <= $cap - 1; $n++) {
    $gap = CandidatoLlegadaEngine::gapMin($n);
    $p = CandidatoLlegadaEngine::pDiaV3($n);
    $et[$n] = $gap + 1 + (1 / max(0.001, $p));
}

ok($et[3] < $et[12], 'curva creciente (espera media sube con N)');
ok($et[15] > $et[8], 'desacelera cerca del cap');

$sum3_15 = 0.0;
for ($n = 3; $n <= 15; $n++) {
    $sum3_15 += $et[$n];
}
ok($sum3_15 > 60 && $sum3_15 < 260, 'bloque 3-15 ritmo acumulado razonable (~' . round($sum3_15) . ' días)');

echo $failures === 0 ? "OK llegadas_curva_v3\n" : "FAIL llegadas_curva_v3 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
