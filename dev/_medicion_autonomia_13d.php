<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorMisionesDiarias;
use AquiHayTema\Engine\VidaPuebloEngine;

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$reloj = new RelojOperations($root);
$dias = 13;
$seeds = 8;
$residentes = 8;

$totales = [];
$netoTotal = 0;
$autoTotal = 0;
$valoresFinales = [];

for ($s = 0; $s < $seeds; $s++) {
    $rng = new RngService('med-autonomia-13d-' . $s);
    $p = SimuladorMisionesDiarias::partidaLab($residentes, $rng, $cal);
    unset($p['_lab_misiones_b3']);
    $p['meta']['config_id'] = 'juego_v1';
    $p['features'] = [
        VidaPuebloEngine::FLAG => true,
        'npc_autonomy_enabled' => true,
        MisionDiariaEngine::FLAG => false,
        PeticionPuebloEngine::FLAG => false,
    ];
    VidaPuebloEngine::ensure($p, $cal);
    $v0 = VidaPuebloEngine::valor($p);
    for ($d = 0; $d < $dias; $d++) {
        $reloj->avanzar($p, 24);
    }
    $v1 = VidaPuebloEngine::valor($p);
    $netoTotal += ($v1 - $v0);
    $valoresFinales[] = $v1;
    foreach ($p['vida_pueblo']['ledger'] ?? [] as $e) {
        if (!empty($e['lab'])) {
            continue;
        }
        $causa = (string) ($e['causa'] ?? '?');
        $delta = (int) ($e['delta'] ?? 0);
        $totales[$causa] = ($totales[$causa] ?? 0) + $delta;
        if ($causa === 'acontecimiento_vida') {
            $autoTotal += $delta;
        }
    }
}

echo "=== Medicion autonomia: {$dias} dias, {$residentes} residentes, {$seeds} seeds ===\n";
echo "Delta neto medio de Vida por seed: " . round($netoTotal / $seeds, 2) . "\n";
echo "Aporte acontecimiento_vida total: {$autoTotal} (" . round($autoTotal / $seeds, 2) . " por seed)\n";
echo "Valores finales: " . implode(',', $valoresFinales) . "\n";
echo "Por causa:\n";
foreach ($totales as $c => $t) {
    echo "  {$c}: {$t}\n";
}
