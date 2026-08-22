<?php
require dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;

$et = [];
for ($n = 8; $n <= 23; $n++) {
    $gap = CandidatoLlegadaEngine::gapMin($n);
    $p = CandidatoLlegadaEngine::pDiaV3($n);
    $et[$n] = $gap + 1 + (1 / $p);
}
echo 'N8=' . $et[8] . ' N12=' . $et[12] . ' sum=' . array_sum($et) . PHP_EOL;
