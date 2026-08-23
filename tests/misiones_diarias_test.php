<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\ContactoCalidad;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\MisionPlantillas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorMisionesDiarias;
use AquiHayTema\Engine\VidaPuebloEngine;

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
$nombres = [];
foreach (MisionPlantillas::catalogo() as $pl) {
    $nombres[] = (string) $pl['id'];
}
ok(count($nombres) >= 8 && count($nombres) <= 12, 'biblioteca 8–12 plantillas');
ok(count($nombres) === count(array_unique($nombres)), 'ids de plantilla únicos');

$service = new PartidaService($root);
$off = $service->nuevaPartida('debug_v0', 'b3-flag-off');
ok(!FeatureConfig::isEnabled($off, MisionDiariaEngine::FLAG), 'debug_v0 sin misiones');
ok(MisionDiariaEngine::delDia($off) === [], 'flag off no genera pack');

$p = $service->nuevaPartida('playtest_01', 'b3-misiones-a');
ok(FeatureConfig::isEnabled($p, VidaPuebloEngine::FLAG), 'playtest vida on');
ok(FeatureConfig::isEnabled($p, MisionDiariaEngine::FLAG), 'playtest misiones on');
$hoy = MisionDiariaEngine::delDia($p);
ok(count($hoy) <= 3, 'máximo 3 misiones día 1');
ok(count($hoy) >= 1, 'día 1 tiene al menos 1 misión realizable');
$estB3 = $service->estadoResumido($p);
ok(isset($estB3['misiones_hoy']['misiones']) && count($estB3['misiones_hoy']['misiones']) >= 1, 'API estadoResumido trae Hoy en el pueblo');
ok(($estB3['features'][MisionDiariaEngine::FLAG] ?? false) === true, 'flag misiones visible en estado');
$fams = [];
$imposibles = 0;
foreach ($hoy as $m) {
    $fam = (string) ($m['familia'] ?? '');
    ok(!isset($fams[$fam]), 'una familia por día: ' . $fam);
    $fams[$fam] = true;
    $enc = MisionDiariaEngine::encuentroSinteticoPara($m, $p);
    if (!MisionDiariaEngine::encaja($m, $enc)) {
        $imposibles++;
    }
    ok(strpos((string) ($m['texto'] ?? ''), 'positivo_valido') === false, 'copy sin jerga técnica');
}
ok($imposibles === 0, 'día 1 sin misiones imposibles');
$nSlot = 0;
foreach ($hoy as $m) {
    if (!empty($m['cuenta_latido'])) {
        $nSlot++;
    }
}
ok($nSlot === 1, 'solo una misión del día cuenta para Latido');

$vida0 = VidaPuebloEngine::valor($p);
$ids = array_keys($p['residentes']);
$encProg = $service->programarEncuentro($p, [$ids[0], $ids[1]], 1, 19, 'conocerse', 'lug_cafeteria');
ok(($encProg['ok'] ?? false) === true || isset($encProg['error']), 'programar no explota');
ok(VidaPuebloEngine::valor($p) === $vida0, 'proponer encuentro = 0 Vida');

$antes = count(MisionDiariaEngine::delDia($p));
$primera = null;
foreach (MisionDiariaEngine::delDia($p) as $m) {
    if (($m['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE) {
        $primera = $m;
        break;
    }
}
ok($primera !== null, 'hay misión pendiente');
if ($primera !== null) {
    $enc = MisionDiariaEngine::encuentroSinteticoPara($primera, $p);
    $n1 = MisionDiariaEngine::onEncuentroCelestine($p, $enc, $cal, null);
    $n2 = MisionDiariaEngine::onEncuentroCelestine($p, $enc, $cal, null);
    ok($n1 === 1, 'un encuentro completa 1 misión');
    ok($n2 === 0, 'el mismo encuentro no completa otra');
    $delta = VidaPuebloEngine::DELTA_MISION_CUMPLIDA;
    ok(VidaPuebloEngine::valor($p) === $vida0 + $delta, 'cumplida +2');
    ok(count(MisionDiariaEngine::delDia($p)) === $antes, 'no nace una cuarta al completar');
}

$pSlot = $service->nuevaPartida('playtest_01', 'b3-misiones-slot');
$vidaS = VidaPuebloEngine::valor($pSlot);
$posS = (int) ($pSlot['vida_pueblo']['positivos_desde_latido'] ?? 0);
$noSlot = null;
$siSlot = null;
foreach (MisionDiariaEngine::delDia($pSlot) as $m) {
    if (!empty($m['cuenta_latido'])) {
        $siSlot = $m;
    } else {
        $noSlot = $m;
    }
}
ok($siSlot !== null, 'hay slot de Latido');
if ($noSlot !== null) {
    $enc = MisionDiariaEngine::encuentroSinteticoPara($noSlot, $pSlot);
    MisionDiariaEngine::onEncuentroCelestine($pSlot, $enc, $cal, null);
    $deltaN = VidaPuebloEngine::DELTA_MISION_CUMPLIDA;
    ok(VidaPuebloEngine::valor($pSlot) === $vidaS + $deltaN, 'misión no slot +2');
    ok((int) ($pSlot['vida_pueblo']['positivos_desde_latido'] ?? 0) === $posS, 'no slot no suma positivo válido');
}
if ($siSlot !== null) {
    $enc = MisionDiariaEngine::encuentroSinteticoPara($siSlot, $pSlot);
    MisionDiariaEngine::onEncuentroCelestine($pSlot, $enc, $cal, null);
    ok((int) ($pSlot['vida_pueblo']['positivos_desde_latido'] ?? 0) === $posS + 1, 'solo el slot suma positivo válido');
}

$pCad = $service->nuevaPartida('playtest_01', 'b3-misiones-cad');
$vidaCad = VidaPuebloEngine::valor($pCad);
$nPend = 0;
foreach (MisionDiariaEngine::delDia($pCad) as $m) {
    if (($m['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE) {
        $nPend++;
    }
}
$reloj = new RelojOperations($root);
$reloj->avanzar($pCad, 24);
$nCad = 0;
foreach ($pCad['misiones_diarias']['items'] ?? [] as $m) {
    if ((int) ($m['dia'] ?? 0) === 1 && ($m['estado'] ?? '') === MisionDiariaEngine::EST_CADUCADA) {
        $nCad++;
    }
}
ok($nCad === $nPend, 'pendientes del día cerrado caducan');
ok(VidaPuebloEngine::valor($pCad) === $vidaCad - (3 * $nPend), 'caducada -3 por misión');
$dia2 = MisionDiariaEngine::delDia($pCad);
ok(count($dia2) <= 3, 'día 2 máximo 3');
$f2 = [];
$dup = false;
foreach ($dia2 as $m) {
    $fam = (string) ($m['familia'] ?? '');
    if (isset($f2[$fam])) {
        $dup = true;
    }
    $f2[$fam] = true;
}
ok(!$dup, 'día 2 sin familias duplicadas');

$est = $service->estadoResumido($p);
ok(isset($est['vida_pueblo']['etiqueta']), 'API vista vida');
ok(!isset($est['vida_pueblo']['valor']), 'API sin 65/100');
ok(isset($est['misiones_hoy']['plazo_humano']), 'API plazo humano');
ok(isset($est['vida_debug']['valor']), 'debug separado');

$labMini = SimuladorMisionesDiarias::ejecutar($root, [8], [7], 1, 'lab-b3-test');
$a7 = $labMini['por_tamano']['8']['por_perfil']['A']['por_horizonte']['7'] ?? [];
ok((int) round((float) ($a7['imposibles'] ?? 1)) === 0, 'lab 8/7d imposibles = 0');
ok((float) ($a7['mas_de_tres'] ?? 1) === 0.0, 'lab nunca genera 4');
ok(empty($labMini['farming_detectado']), 'lab sin farming de microvida');

$rng = new RngService('b3-lab-world');
$mundo = SimuladorMisionesDiarias::partidaLab(8, $rng, $cal);
RelacionEngine::registrarContacto($mundo, 'lab_r01', 'lab_r02', ContactoCalidad::NORMAL, $cal);
ok(RelacionEngine::seConocen($mundo, 'lab_r01', 'lab_r02'), 'side-effect conocerse lab');
$candsQ = MisionDiariaEngine::candidatos($mundo, MisionPlantillas::porId('quedar_dos'), $cal);
ok($candsQ !== [], 'quedar solo si hay par conocido');
$candsP = MisionDiariaEngine::candidatos($mundo, MisionPlantillas::porId('primera_cita_hoy'), $cal);
ok($candsP === [], 'primera cita no nace sin señal');

exit($failures > 0 ? 1 : 0);
