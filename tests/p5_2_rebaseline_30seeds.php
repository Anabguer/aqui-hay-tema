<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\CalibracionConfig;
$root = dirname(__DIR__);
$service = new PartidaService($root);
$seeds = [];
for ($i = 1; $i <= 30; $i++) { $seeds[] = sprintf('p52-s%02d', $i); }
echo "=== AHT-P5.2 REBASELINE PASO A PASO ===\n";
function aceptarLlegadas(PartidaService $s, array &$p) {
    $mx = 10;
    while (($p['llegadas']['candidato_activo'] ?? null) !== null && $mx-- > 0) {
        $a = CapacidadViviendas::residentesActivos($p);
        if ($a === []) break;
        $r = CandidatoLlegadaEngine::aceptar($p, dirname(__DIR__), null, null, (string)$a[0]);
        if (!($r['ok'] ?? false)) break;
        $s->avanzarRelojPasoAPaso($p, 1);
    }
}
function calcStats(array $v) {
    if ($v === []) return ['min'=>null,'max'=>null,'median'=>null,'p25'=>null,'p75'=>null];
    sort($v); $n = count($v);
    return ['min'=>$v[0],'max'=>$v[$n-1],'median'=>$v[(int)($n/2)],'p25'=>$v[(int)($n*0.25)],'p75'=>$v[(int)($n*0.75)]];
}
$all = [];
foreach ($seeds as $seed) {
    $start = microtime(true);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $ppd = [];
    for ($h = 0; $h < 30*24; $h++) {
        $service->avanzarRelojPasoAPaso($p, 1);
        if ((int)($p['reloj']['hora_actual'] ?? 0) === 0) {
            $d = (int)($p['reloj']['dia_pueblo'] ?? 1);
            aceptarLlegadas($service, $p);
            if (in_array($d, [5,10,15,20,30])) {
                $ppd[$d] = 0;
                foreach ($p['relaciones_romanticas'] ?? [] as $r) {
                    if (($r['estado_pareja'] ?? 'ninguna') !== 'ninguna') $ppd[$d]++;
                }
            }
        }
    }
    aceptarLlegadas($service, $p);
    $el = round(microtime(true) - $start, 1);
    $f = 0; foreach ($p['relaciones_romanticas'] ?? [] as $r) { $f += count($r['flechazos'] ?? []); }
    $d2 = 0; foreach ($p['memoria_eventos'] ?? [] as $e) { if (($e['tipo'] ?? '') === 'declaracion') $d2++; }
    $pa = 0; foreach ($p['relaciones_romanticas'] ?? [] as $r) { if (($r['estado_pareja'] ?? 'ninguna') !== 'ninguna') $pa++; }
    $act = CapacidadViviendas::residentesActivos($p);
    $rm = 0;
    for ($i=0; $i<count($act); $i++) { for ($j=$i+1; $j<count($act); $j++) {
        $rm = max($rm, (int)(RelacionEngine::romanceHacia($p,$act[$i],$act[$j]) ?? 0), (int)(RelacionEngine::romanceHacia($p,$act[$j],$act[$i]) ?? 0));
    }}
    $all[$seed] = ['f'=>$f,'d'=>$d2,'pa'=>$pa,'rm'=>$rm,'ppd'=>$ppd];
    echo "[{$seed}] {$el}s flech={$f} decl={$d2} parejas={$pa} romMax={$rm}\n";
}
echo "\n=== RESUMEN ===\n";
foreach (['flechazos'=>'f','declaraciones'=>'d','parejas'=>'pa','romMax'=>'rm'] as $m=>$k) {
    $v = array_map(fn($r)=>$r[$k], $all);
    $s = calcStats($v);
    echo "{$m}: min={$s['min']} p25={$s['p25']} median={$s['median']} p75={$s['p75']} max={$s['max']}\n";
}
echo "\nPAREJAS POR DIA:\n";
foreach ([5,10,15,20,30] as $d) {
    $v = []; foreach ($all as $r) { $v[] = $r['ppd'][$d] ?? 0; }
    $cp = count(array_filter($v, fn($x)=>$x>0));
    echo "  D{$d}: {$cp}/30 con pareja\n";
}
$sp = count(array_filter($all, fn($r)=>$r['pa']===0));
echo "\nSeeds sin pareja: {$sp}/30\n";
$sd = count(array_filter($all, fn($r)=>$r['d']===0));
echo "Seeds sin declaracion: {$sd}/30\n";
echo "\n=== AHT-P5.2 REBASELINE COMPLETADA ===\n";