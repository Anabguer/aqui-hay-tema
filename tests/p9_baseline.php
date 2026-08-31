<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);

function aceptarCandidatos(PartidaService $svc, array &$p): void {
    global $root;
    $mx = 15;
    while (($p['llegadas']['candidato_activo'] ?? null) !== null && $mx-- > 0) {
        $a = CapacidadViviendas::residentesActivos($p);
        if ($a === []) break;
        $r = CandidatoLlegadaEngine::aceptar($p, $root, null, null, (string)$a[0]);
        if (!($r['ok'] ?? false)) break;
        $svc->avanzarRelojPasoAPaso($p, 1);
    }
}

$SEEDS = 15;
$DIAS = 10;

echo "=== AHT-P9: BASELINE DIARIO — {$SEEDS}x{$DIAS} ===\n\n";

$globalStats = [
    'total' => 0, 'por_dia' => [], 'por_tipo' => [], 'por_tono' => [],
    'duplicadas' => 0, 'por_residente' => [], 'por_seed' => [],
    'progresion_romantica' => 0, 'por_clase' => ['A_hito_mayor' => 0, 'B_hito_menor' => 0, 'C_rutina' => 0],
];

$HITO_MAYOR = [
    'se_conocieron', 'primera_cita', 'regalo', 'rechazo_importante',
    'flechazo', 'inicio_pareja', 'vuelta', 'reconciliacion',
    'ruptura', 'crisis', 'discusion_fuerte', 'declaracion',
    'hito_romantico', 'apoyo_importante', 'perder_trabajo', 'encontrar_trabajo',
];

$HITO_MENOR = [
    'encuentro', 'descubrimiento', 'progresion_romantica',
];

$RUTINA = [
    'cotilleo_autonomo', 'cotilleo_patron', 'cotilleo_casual_descubrimiento',
    'cotilleo', 'cotilleo_hito', 'discusion', 'senal_romantica',
];

$POSITIVE = [
    'primera_cita', 'regalo', 'flechazo', 'inicio_pareja', 'vuelta',
    'reconciliacion', 'hito_romantico', 'apoyo_importante', 'encontrar_trabajo',
    'progresion_romantica',
];

$NEGATIVE = [
    'rechazo_importante', 'ruptura', 'crisis', 'discusion_fuerte',
    'declaracion', 'perder_trabajo',
];

for ($i = 1; $i <= $SEEDS; $i++) {
    $seed = sprintf('p9-base-%02d', $i);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $service->avanzarRelojPasoAPaso($p, 24);
    aceptarCandidatos($service, $p);

    $seedTotal = 0;
    $seedPorDia = [];

    for ($d = 1; $d <= $DIAS; $d++) {
        $antes = count($p['diario'] ?? []);
        $service->avanzarRelojPasoAPaso($p, 24);
        $despues = count($p['diario'] ?? []);
        $nuevos = $despues - $antes;

        $diaCount = 0;
        for ($k = $antes; $k < $despues; $k++) {
            $e = $p['diario'][$k];
            $tipo = $e['tipo'] ?? 'ruido';
            $subtipo = $e['subtipo'] ?? '';
            $eventoId = $e['origen']['evento_id'] ?? '';
            $actores = $e['actores'] ?? [];

            $globalStats['total']++;
            $diaCount++;
            $globalStats['por_tipo'][$tipo] = ($globalStats['por_tipo'][$tipo] ?? 0) + 1;

            foreach ($actores as $actor) {
                $globalStats['por_residente'][$actor] = ($globalStats['por_residente'][$actor] ?? 0) + 1;
            }

            $tono = 'neutro';
            if (in_array($subtipo, $POSITIVE) || in_array($tipo, $POSITIVE)) {
                $tono = 'positivo';
            } elseif (in_array($subtipo, $NEGATIVE) || in_array($tipo, $NEGATIVE)) {
                $tono = 'negativo';
            }
            $globalStats['por_tono'][$tono] = ($globalStats['por_tono'][$tono] ?? 0) + 1;

            if (in_array($subtipo, $HITO_MAYOR) || in_array($tipo, $HITO_MAYOR)) {
                $globalStats['por_clase']['A_hito_mayor']++;
            } elseif (in_array($subtipo, $HITO_MENOR) || in_array($tipo, $HITO_MENOR)) {
                $globalStats['por_clase']['B_hito_menor']++;
            } else {
                $globalStats['por_clase']['C_rutina']++;
            }

            if ($tipo === 'progresion_romantica') {
                $globalStats['progresion_romantica']++;
            }
        }

        $globalStats['por_dia'][$d] = ($globalStats['por_dia'][$d] ?? 0) + $diaCount;
        $seedPorDia[$d] = $diaCount;
        $seedTotal += $diaCount;
    }

    $globalStats['por_seed'][$seed] = $seedTotal;
    $residents = CapacidadViviendas::residentesActivos($p);
    $resCount = count($residents);
    $avgPerChar = $resCount > 0 ? round($seedTotal / $resCount / $DIAS, 1) : 0;
    $avgPerDay = round($seedTotal / $DIAS, 1);
    echo "{$seed}: total={$seedTotal} prom/dia={$avgPerDay} prom/personaje/dia={$avgPerChar} residents={$resCount}\n";
}

echo "\n=== SECTION 1: VOLUMEN GLOBAL ===\n";
echo "Total entradas: {$globalStats['total']}\n";
$avgPerSeed = round($globalStats['total'] / $SEEDS, 1);
$avgPerDay = round($globalStats['total'] / $SEEDS / $DIAS, 1);
echo "Promedio por seed ({$DIAS}d): {$avgPerSeed}\n";
echo "Promedio por dia: {$avgPerDay}\n";

echo "\n=== SECTION 2: POR DIA ===\n";
for ($d = 1; $d <= $DIAS; $d++) {
    $v = $globalStats['por_dia'][$d] ?? 0;
    $avg = round($v / $SEEDS, 1);
    echo "D{$d}: {$v} total ({$avg}/seed)\n";
}

echo "\n=== SECTION 3: POR TIPO ===\n";
arsort($globalStats['por_tipo']);
foreach ($globalStats['por_tipo'] as $tipo => $count) {
    $pct = round($count / $globalStats['total'] * 100, 1);
    echo "  {$tipo}: {$count} ({$pct}%)\n";
}

echo "\n=== SECTION 4: CLASIFICACION A/B/C ===\n";
foreach ($globalStats['por_clase'] as $clase => $count) {
    $pct = round($count / $globalStats['total'] * 100, 1);
    echo "  {$clase}: {$count} ({$pct}%)\n";
}

echo "\n=== SECTION 5: TONO ===\n";
foreach ($globalStats['por_tono'] as $tono => $count) {
    $pct = round($count / $globalStats['total'] * 100, 1);
    echo "  {$tono}: {$count} ({$pct}%)\n";
}

echo "\n=== SECTION 6: PROGRESION ROMANTICA ===\n";
echo "  Total entradas progresion_romantica: {$globalStats['progresion_romantica']}\n";

echo "\n=== SECTION 7: DUPLICADOS ===\n";
echo "  ( Medido en test especifico )\n";

echo "\n=== RESUMEN ===\n";
echo "Entradas totales: {$globalStats['total']}\n";
echo "Promedio por seed: {$avgPerSeed}\n";
echo "Promedio por dia: {$avgPerDay}\n";
echo "A (hito mayor): {$globalStats['por_clase']['A_hito_mayor']} (" . round($globalStats['por_clase']['A_hito_mayor'] / max(1, $globalStats['total']) * 100, 1) . "%)\n";
echo "B (hito menor): {$globalStats['por_clase']['B_hito_menor']} (" . round($globalStats['por_clase']['B_hito_menor'] / max(1, $globalStats['total']) * 100, 1) . "%)\n";
echo "C (rutina): {$globalStats['por_clase']['C_rutina']} (" . round($globalStats['por_clase']['C_rutina'] / max(1, $globalStats['total']) * 100, 1) . "%)\n";
echo "\n=== DONE ===\n";
