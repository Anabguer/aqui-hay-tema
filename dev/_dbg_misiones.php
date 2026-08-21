<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\VidaPuebloEngine;
use AquiHayTema\Engine\CalibracionConfig;

$root = dirname(__DIR__);
$s = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$p = $s->nuevaPartida('playtest_01', 'b3-misiones-slot-dbg');
$vidaS = VidaPuebloEngine::valor($p);
$posS = (int) ($p['vida_pueblo']['positivos_desde_latido'] ?? 0);
echo "initial pos=$posS vida=$vidaS\n";
foreach (MisionDiariaEngine::delDia($p) as $m) {
    echo "mission {$m['id']} latido=" . (int)!empty($m['cuenta_latido']) . " ex={$m['exigencia']} estado={$m['estado']}\n";
}
$siSlot = null; $noSlot = null;
foreach (MisionDiariaEngine::delDia($p) as $m) {
    if (!empty($m['cuenta_latido'])) { $siSlot = $m; } else { $noSlot = $m; }
}
if ($noSlot) {
    $enc = MisionDiariaEngine::encuentroSinteticoPara($noSlot, $p);
    MisionDiariaEngine::onEncuentroCelestine($p, $enc, $cal, null);
    echo "after noSlot pos=" . ($p['vida_pueblo']['positivos_desde_latido'] ?? 0) . " vida=" . VidaPuebloEngine::valor($p) . "\n";
}
if ($siSlot) {
    $enc = MisionDiariaEngine::encuentroSinteticoPara($siSlot, $p);
    MisionDiariaEngine::onEncuentroCelestine($p, $enc, $cal, null);
    echo "after siSlot pos=" . ($p['vida_pueblo']['positivos_desde_latido'] ?? 0) . " vida=" . VidaPuebloEngine::valor($p) . " expect=" . ($posS+1) . "\n";
}
