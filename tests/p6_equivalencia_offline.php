<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\CatchUpEngine;
$root = dirname(__DIR__);
$service = new PartidaService($root);

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
function seedSetup($root, $seed) {
    $svc = new PartidaService($root);
    $p = $svc->nuevaPartida('juego_v1', $seed);
    $svc->avanzarRelojPasoAPaso($p, 24);
    acep($svc, $p);
    return [$svc, $p];
}

// === 1. EQUIVALENCIA 6h/12h/24h ===
echo "=== 1. EQUIVALENCIA ONLINE vs OFFLINE ===\n\n";
$seeds = ['eq-s01','eq-s02','eq-s03'];
foreach ($seeds as $seed) {
    foreach ([6, 12, 24] as $horas) {
        [$svcB, $pB] = seedSetup($root, $seed);
        $svcB->avanzarRelojPasoAPaso($pB, $horas);
        acep($svcB, $pB);
        $sB = snap($pB);

        [$svcC, $pC] = seedSetup($root, $seed);
        $pC['features']['offline_events_enabled'] = true;
        $pC['reloj']['ultimo_catch_up_iso'] = (new DateTimeImmutable("-{$horas} hours", new DateTimeZone('UTC')))->format(DATE_ATOM);
        CatchUpEngine::ejecutarAlCargar($pC, $root);
        $sC = snap($pC);

        $diff = abs($sB['enc'] - $sC['enc']);
        $status = ($diff <= 1) ? 'OK' : 'DIF';
        echo "{$seed} {$horas}h: real={$sB['enc']} offline={$sC['enc']} diff={$diff} [{$status}]\n";
    }
    echo "\n";
}

// === 2. 30 SEEDS AUSENCIA 24h ===
echo "=== 2. 30 SEEDS AUSENCIA 24h ===\n\n";
$absSeeds = [];
for ($i = 1; $i <= 30; $i++) { $absSeeds[] = sprintf('abs-s%02d', $i); }
$results24 = [];
foreach ($absSeeds as $seed) {
    [$svc, $p] = seedSetup($root, $seed);
    $before = snap($p);
    $p['features']['offline_events_enabled'] = true;
    $p['reloj']['ultimo_catch_up_iso'] = (new DateTimeImmutable('-24 hours', new DateTimeZone('UTC')))->format(DATE_ATOM);
    $cu = CatchUpEngine::ejecutarAlCargar($p, $root);
    $after = snap($p);
    $deltaEnc = $after['enc'] - $before['enc'];
    $deltaFlech = $after['flech'] - $before['flech'];
    $deltaRom = $after['rom'] - $before['rom'];
    $deltaPa = $after['pa'] - $before['pa'];
    $results24[$seed] = ['before'=>$before,'after'=>$after,'delta_enc'=>$deltaEnc,'delta_flech'=>$deltaFlech,'delta_rom'=>$deltaRom,'delta_pa'=>$deltaPa];
    echo "{$seed}: enc+{$deltaEnc} flech+{$deltaFlech} rom+{$deltaRom} pa+{$deltaPa}\n";
}

// Estadísticas
$encDeltas = array_map(fn($r) => $r['delta_enc'], $results24);
$flechDeltas = array_map(fn($r) => $r['delta_flech'], $results24);
$paDeltas = array_map(fn($r) => $r['delta_pa'], $results24);
sort($encDeltas); sort($flechDeltas); sort($paDeltas);
$n = count($encDeltas);
echo "\nEncuentros: min=" . $encDeltas[0] . " median=" . $encDeltas[(int)($n/2)] . " max=" . $encDeltas[$n-1] . "\n";
echo "Flechazos: min=" . $flechDeltas[0] . " median=" . $flechDeltas[(int)($n/2)] . " max=" . $flechDeltas[$n-1] . "\n";
echo "Parejas: min=" . $paDeltas[0] . " median=" . $paDeltas[(int)($n/2)] . " max=" . $paDeltas[$n-1] . "\n";
$pa24h = count(array_filter($paDeltas, fn($v) => $v > 0));
echo "Seeds con nueva pareja 24h: {$pa24h}/30\n";
echo "\n=== DONE ===\n";