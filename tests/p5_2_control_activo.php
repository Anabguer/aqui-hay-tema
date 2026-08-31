<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
$root = dirname(__DIR__);
$service = new PartidaService($root);
$seeds = [];
for ($i = 1; $i <= 5; $i++) { $seeds[] = sprintf('p52-act%02d', $i); }
echo "=== AHT-P5.2 CONTROL ACTIVO (2/dia) ===\n\n";
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
function encontrarPar(array $p): ?array {
    $a = CapacidadViviendas::residentesActivos($p);
    if (count($a) < 2) return null;
    $ic = []; foreach ($a as $r) $ic[$r] = 0;
    foreach ($p['encuentros'] ?? [] as $e) { foreach ($e['participantes'] ?? [] as $pid) { if (isset($ic[$pid])) $ic[$pid]++; } }
    asort($ic); $mid = array_key_first($ic);
    $otros = array_values(array_filter($a, fn($id)=>$id!==$mid));
    if ($otros === []) return null;
    $mp = null; $mr = -1;
    foreach ($otros as $o) { $rom = (int)(RelacionEngine::romanceHacia($p,$mid,$o) ?? 0); if ($rom > $mr) { $mr = $rom; $mp = [$mid,$o]; } }
    return $mp;
}
foreach ($seeds as $seed) {
    $start = microtime(true);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $ppd = [];
    for ($dia = 1; $dia <= 30; $dia++) {
        for ($hora = 0; $hora < 24; $hora++) {
            $service->avanzarRelojPasoAPaso($p, 1);
            if ($hora === 0) aceptarLlegadas($service, $p);
            if ($hora >= 9 && $hora <= 20 && ($hora % 6 === 0)) {
                $par = encontrarPar($p);
                if ($par !== null) {
                    $d = (int)($p['reloj']['dia_pueblo'] ?? 1);
                    $h = (int)($p['reloj']['hora_actual'] ?? 0);
                    PropuestaEncuentroEngine::proponer($p, $par, $d, $h + 1, 'conocerse');
                }
            }
        }
        $ppd[$dia] = 0;
        foreach ($p['relaciones_romanticas'] ?? [] as $r) { if (($r['estado_pareja'] ?? 'ninguna') !== 'ninguna') $ppd[$dia]++; }
    }
    $el = round(microtime(true) - $start, 1);
    $pa = 0; foreach ($p['relaciones_romanticas'] ?? [] as $r) { if (($r['estado_pareja'] ?? 'ninguna') !== 'ninguna') $pa++; }
    $fl = 0; foreach ($p['relaciones_romanticas'] ?? [] as $r) { $fl += count($r['flechazos'] ?? []); }
    $act = CapacidadViviendas::residentesActivos($p); $rm = 0;
    for ($i=0;$i<count($act);$i++) { for ($j=$i+1;$j<count($act);$j++) {
        $rm = max($rm, (int)(RelacionEngine::romanceHacia($p,$act[$i],$act[$j]) ?? 0), (int)(RelacionEngine::romanceHacia($p,$act[$j],$act[$i]) ?? 0));
    }}
    echo "[{$seed}] {$el}s flech={$fl} parejas={$pa} romMax={$rm}\n";
}
echo "\n=== CONTROL ACTIVO COMPLETADO ===\n";