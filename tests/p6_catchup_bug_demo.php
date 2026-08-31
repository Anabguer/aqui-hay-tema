<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\CatchUpEngine;
$root = dirname(__DIR__);
$service = new PartidaService($root);
$seed = 'p6-bug01';
function snap(array $p): array {
    $a = CapacidadViviendas::residentesActivos($p);
    $e = count($p['encuentros'] ?? []);
    $f = 0; foreach ($p['relaciones_romanticas'] ?? [] as $r) { $f += count($r['flechazos'] ?? []); }
    $rm = 0;
    for ($i=0;$i<count($a);$i++) { for ($j=$i+1;$j<count($a);$j++) {
        $rm = max($rm, (int)(RelacionEngine::romanceHacia($p,$a[$i],$a[$j]) ?? 0), (int)(RelacionEngine::romanceHacia($p,$a[$j],$a[$i]) ?? 0));
    }}
    $pa = 0; foreach ($p['relaciones_romanticas'] ?? [] as $r) { if (($r['estado_pareja'] ?? 'ninguna') !== 'ninguna') $pa++; }
    $mem = []; foreach ($p['memoria_eventos'] ?? [] as $ev) { $fm = $ev['familia'] ?? $ev['tipo'] ?? '?'; $mem[$fm] = ($mem[$fm] ?? 0) + 1; }
    return ['res'=>count($a),'enc'=>$e,'flech'=>$f,'rom'=>$rm,'pa'=>$pa,'mem'=>$mem];
}
function acep(PartidaService $s, array &$p) {
    $mx = 10;
    while (($p['llegadas']['candidato_activo'] ?? null) !== null && $mx-- > 0) {
        $a = CapacidadViviendas::residentesActivos($p);
        if ($a === []) break;
        $r = CandidatoLlegadaEngine::aceptar($p, dirname(__DIR__), null, null, (string)$a[0]);
        if (!($r['ok'] ?? false)) break;
        $s->avanzarRelojPasoAPaso($p, 1);
    }
}
function setup($root, $seed) {
    $svc = new PartidaService($root);
    $p = $svc->nuevaPartida('juego_v1', $seed);
    $svc->avanzarRelojPasoAPaso($p, 24);
    acep($svc, $p);
    return [$svc, $p];
}
echo "=== AHT-P6 CATCH-UP BUG DEMO (24h) ===\n\n";

// B) reference: 24h more real
[$svcB, $pB] = setup($root, $seed);
$svcB->avanzarRelojPasoAPaso($pB, 24);
acep($svcB, $pB);
$sB = snap($pB);

// C) 24h via CatchUpEngine
[$svcC, $pC] = setup($root, $seed);
$pC['features']['offline_events_enabled'] = true;
$pC['reloj']['ultimo_catch_up_iso'] = (new DateTimeImmutable('-24 hours', new DateTimeZone('UTC')))->format(DATE_ATOM);
$cuResult = CatchUpEngine::ejecutarAlCargar($pC, $root);
$sC = snap($pC);

echo "B(24h real): enc={$sB['enc']} flech={$sB['flech']} rom={$sB['rom']} pa={$sB['pa']}\n";
echo "C(24h cu):   enc={$sC['enc']} flech={$sC['flech']} rom={$sC['rom']} pa={$sC['pa']}\n";
echo "Mem B: " . json_encode($sB['mem']) . "\n";
echo "Mem C: " . json_encode($sC['mem']) . "\n";
$st = $cuResult['stats'] ?? [];
echo "CuStats: horas=" . ($st['horas_avanzadas'] ?? '?') . " enc=" . ($st['encuentros_resueltos'] ?? '?') . " pasos=" . ($st['pasos'] ?? '?') . "\n";
echo "Ejecutado: " . (($cuResult['ejecutado'] ?? false) ? 'SI' : 'NO') . "\n";
$de = abs($sB['enc']-$sC['enc']); $df = abs($sB['flech']-$sC['flech']); $dr = abs($sB['rom']-$sC['rom']);
echo "\nB vs C diff: enc={$de} flech={$df} rom={$dr}\n";
if ($sC['enc'] > 0 && $de <= 5) echo "\nCatchUp FUNCIONANDO!\n";
elseif ($sC['enc'] === 0) echo "\nCatchUp SIN VIDA.\n";
else echo "\nDiferencias grandes.\n";
echo "=== DONE ===\n";