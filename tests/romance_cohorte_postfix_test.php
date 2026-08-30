<?php
declare(strict_types=1);

/**
 * CIERRE E2E Nº3 — COHORTES POST-FIX — CONFIG NORMAL REAL
 * lab_vida_activa=false → enPlay=true → familias_en_play filtra
 * Verifica: toda INICIO_PAREJA tiene PRIMERA_CITA previa para ese par
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorPuebloVivo;

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);

$nRes = 8;
$nSeeds = 5;
$horizontes = [5, 10, 15, 20, 30];
// D60 se ejecuta con menos seeds para no exceder timeout
$d60Seeds = 3;

echo "=== COHORTES POST-FIX — CONFIG NORMAL REAL ===\n";
echo "lab_vida_activa=false | familias_en_play incluye romance_hito+pareja\n";
echo "Población: $nRes residentes\n\n";

foreach ($horizontes as $dias) {
    $results = [];
    $parejasSinPC = 0;
    $totalParejas = 0;
    for ($s = 0; $s < $nSeeds; $s++) {
        $rng = new RngService("rom-fix-$nRes-$dias-$s");
        $partida = SimuladorPuebloVivo::pueblo($nRes, $rng, $cal, $catalog);
        $partida['lab_vida_activa'] = false;
        $m = SimuladorPuebloVivo::correr($partida, $catalog, $cal, $rng, $dias);
        $results[] = $m;
        
        // Verificar: toda INICIO_PAREJA tiene PRIMERA_CITA previa para ese par
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (($h['tipo'] ?? '') === RelacionBitacora::INICIO_PAREJA) {
                $totalParejas++;
                $par = (array) ($h['par'] ?? $h['participantes'] ?? []);
                if (count($par) >= 2) {
                    $a = (string) $par[0];
                    $b = (string) $par[1];
                    if (!RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::PRIMERA_CITA)) {
                        $parejasSinPC++;
                        echo "  ⚠ PAREJA SIN PRIMERA_CITA: $a<->$b en D$dias seed=$s\n";
                    }
                }
            }
        }
    }
    
    printMetrics($results, $dias, $nRes, $parejasSinPC, $totalParejas);
}

// D60 con menos seeds
echo "\n--- D60 ($d60Seeds seeds) ---\n";
$d60Results = [];
$d60SinPC = 0;
$d60Total = 0;
for ($s = 0; $s < $d60Seeds; $s++) {
    $rng = new RngService("rom-fix-$nRes-60-$s");
    $partida = SimuladorPuebloVivo::pueblo($nRes, $rng, $cal, $catalog);
    $partida['lab_vida_activa'] = false;
    $m = SimuladorPuebloVivo::correr($partida, $catalog, $cal, $rng, 60);
    $d60Results[] = $m;
    foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
        if (($h['tipo'] ?? '') === RelacionBitacora::INICIO_PAREJA) {
            $d60Total++;
            $par = (array) ($h['par'] ?? $h['participantes'] ?? []);
            if (count($par) >= 2) {
                $a = (string) $par[0];
                $b = (string) $par[1];
                if (!RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::PRIMERA_CITA)) {
                    $d60SinPC++;
                    echo "  ⚠ PAREJA SIN PRIMERA_CITA: $a<->$b en D60 seed=$s\n";
                }
            }
        }
    }
}
printMetrics($d60Results, 60, $nRes, $d60SinPC, $d60Total);

echo "\n=== PAREJAS SIN PRIMERA_CITA TOTAL: " . ($parejasSinPC + $d60SinPC) . " ===\n";

function printMetrics(array $results, int $dias, int $nRes, int $parejasSinPC, int $totalParejas): void {
    $nSeeds = count($results);
    $pares = $nRes * ($nRes - 1) / 2;
    $avg = fn(array $v) => $v === [] ? 0.0 : round(array_sum($v) / count($v), 2);
    
    echo "--- D$dias ($nSeeds seeds) ---\n";
    
    $flech = array_map(fn($m) => (float)($m['hitos']['flechazo'] ?? 0), $results);
    echo "  Flechazos:              " . $avg($flech) . "\n";
    
    $pc = array_map(fn($m) => (float)($m['hitos']['primera_cita'] ?? 0), $results);
    echo "  Primera_cita:           " . $avg($pc) . "\n";
    
    // Contar declaraciones exitosas (inicio_pareja con hito=declaracion)
    $ip = array_map(fn($m) => (float)($m['hitos']['inicio_pareja'] ?? 0), $results);
    echo "  Inicio_pareja:          " . $avg($ip) . "\n";
    
    $vu = array_map(fn($m) => (float)($m['hitos']['vuelta'] ?? 0), $results);
    echo "  Vuelta:                 " . $avg($vu) . "\n";
    
    $cr = array_map(fn($m) => (float)($m['hitos']['crisis'] ?? 0), $results);
    echo "  Crisis:                 " . $avg($cr) . "\n";
    
    $ru = array_map(fn($m) => (float)($m['hitos']['ruptura'] ?? 0), $results);
    echo "  Rupturas:               " . $avg($ru) . "\n";
    
    $pa = array_map(fn($m) => (float)($m['parejas_activas_final'] ?? 0), $results);
    echo "  Parejas activas final:  " . $avg($pa) . "\n";
    
    $rec = array_map(fn($m) => (float)($m['rechazos'] ?? 0), $results);
    echo "  Rechazos:               " . $avg($rec) . "\n";
    
    $dm = array_map(fn($m) => (float)($m['duracion_media_pareja'] ?? 0), $results);
    echo "  Duración media pareja:  " . $avg($dm) . "\n";
    
    $rv = array_map(function ($m) {
        $mx = 0;
        foreach ($m['relaciones_romanticas'] ?? [] as $rel) {
            $v = max((int)($rel['romance_a_hacia_b'] ?? 0), (int)($rel['romance_b_hacia_a'] ?? 0));
            if ($v > $mx) { $mx = $v; }
        }
        return (float) $mx;
    }, $results);
    echo "  Romance máximo:         " . $avg($rv) . "\n";
    
    $romAny = array_map(fn($m) => (float) count(array_filter($m['relaciones_romanticas'] ?? [], fn($rel) => (int)($rel['romance_a_hacia_b'] ?? 0) > 0 || (int)($rel['romance_b_hacia_a'] ?? 0) > 0)), $results);
    echo "  Pares romance>0:        " . $avg($romAny) . " / $pares\n";
    
    echo "  Parejas con PC previa:  " . ($totalParejas - $parejasSinPC) . "/$totalParejas\n";
    echo "  Parejas SIN PC:         $parejasSinPC\n";
}
