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

$absences = [24, 48, 72, 168, 720]; // 1d, 2d, 3d, 7d, 30d
$seeds = [];
for ($i = 1; $i <= 10; $i++) { $seeds[] = sprintf('perf-s%02d', $i); }

echo "=== AUSENCIAS LARGAS + RENDIMIENTO ===\n\n";
foreach ($absences as $ausH) {
    $ausD = round($ausH / 24, 1);
    echo "--- {$ausH}h ({$ausD}d) ---\n";
    $encDeltas = []; $flechDeltas = []; $paDeltas = []; $times = [];
    foreach ($seeds as $seed) {
        $svc = new PartidaService($root);
        $p = $svc->nuevaPartida('juego_v1', $seed);
        $svc->avanzarRelojPasoAPaso($p, 24);
        acep($svc, $p);
        $before = snap($p);
        $p['features']['offline_events_enabled'] = true;
        $p['reloj']['ultimo_catch_up_iso'] = (new DateTimeImmutable("-{$ausH} hours", new DateTimeZone('UTC')))->format(DATE_ATOM);
        $t0 = microtime(true);
        CatchUpEngine::ejecutarAlCargar($p, $root);
        $elapsed = round(microtime(true) - $t0, 2);
        $after = snap($p);
        $encDeltas[] = $after['enc'] - $before['enc'];
        $flechDeltas[] = $after['flech'] - $before['flech'];
        $paDeltas[] = $after['pa'] - $before['pa'];
        $times[] = $elapsed;
    }
    sort($encDeltas); sort($flechDeltas); sort($paDeltas); sort($times);
    $n = count($encDeltas);
    $avgEnc = round(array_sum($encDeltas)/$n, 1);
    $avgFlech = round(array_sum($flechDeltas)/$n, 1);
    $avgPa = round(array_sum($paDeltas)/$n, 1);
    $avgTime = round(array_sum($times)/$n, 2);
    $maxTime = max($times);
    echo "  Encuentros: avg={$avgEnc} min={$encDeltas[0]} max={$encDeltas[$n-1]}\n";
    echo "  Flechazos: avg={$avgFlech} min={$flechDeltas[0]} max={$flechDeltas[$n-1]}\n";
    echo "  Parejas: avg={$avgPa} min={$paDeltas[0]} max={$paDeltas[$n-1]}\n";
    echo "  CPU: avg={$avgTime}s max={$maxTime}s\n";
    $paNew = count(array_filter($paDeltas, fn($v) => $v > 0));
    echo "  Seeds con nueva pareja: {$paNew}/{$n}\n\n";
}
echo "=== DONE ===\n";