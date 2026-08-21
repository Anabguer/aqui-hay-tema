<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\VidaPuebloEngine;
use AquiHayTema\Engine\CalibracionConfig;

$root = dirname(__DIR__);
$s = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$p = $s->nuevaPartida('playtest_01', 'b3-misiones-slot-dbg2');
$siSlot = null; $noSlot = null;
foreach (MisionDiariaEngine::delDia($p) as $m) {
    if (!empty($m['cuenta_latido'])) { $siSlot = $m; } else { $noSlot = $m; }
}
if ($noSlot) {
    $enc = MisionDiariaEngine::encuentroSinteticoPara($noSlot, $p);
    $n = MisionDiariaEngine::onEncuentroCelestine($p, $enc, $cal, null);
    echo "noSlot {$noSlot['plantilla_id']} hechos=$n estado=" . ($p['misiones_diarias']['items'][array_search($noSlot['id'], array_column($p['misiones_diarias']['items'], 'id'))]['estado'] ?? '?') . "\n";
}
if ($siSlot) {
    echo "siSlot {$siSlot['plantilla_id']} params=" . json_encode($siSlot['params'] ?? []) . "\n";
    $enc = MisionDiariaEngine::encuentroSinteticoPara($siSlot, $p);
    echo "enc=" . json_encode($enc) . "\n";
    $n = MisionDiariaEngine::onEncuentroCelestine($p, $enc, $cal, null);
    echo "siSlot hechos=$n pos=" . ($p['vida_pueblo']['positivos_desde_latido'] ?? 0) . " vida=" . VidaPuebloEngine::valor($p) . "\n";
    foreach (MisionDiariaEngine::delDia($p) as $m) {
        echo "  {$m['id']} {$m['plantilla_id']} {$m['estado']} latido=" . (int)!empty($m['cuenta_latido']) . "\n";
    }
}
