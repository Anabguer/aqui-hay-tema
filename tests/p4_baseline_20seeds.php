<?php
declare(strict_types=1);

/**
 * AHT-P4: Baseline 20 seeds — jugador pasivo.
 * Mide: densidad social, encuentros, distribución, pares.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\ContactoCalidad;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$service = new PartidaService($root);

$seeds = [];
for ($i = 1; $i <= 20; $i++) { $seeds[] = sprintf('p4-b%02d', $i); }

$checkDays = [1, 3, 5, 7, 10, 15, 20, 25, 30];
$maxDay = max($checkDays);

echo "=== AHT-P4 BASELINE 20 SEEDS (jugador pasivo) ===\n\n";

function encontrarAcompanante(array $partida): ?string
{
    $activos = CapacidadViviendas::residentesActivos($partida);
    if ($activos === []) { return null; }
    $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
    $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
    foreach ($activos as $rid) {
        $disp = AgendaEngine::estaDisponibleIntervalo($partida, (string) $rid, $dia, $hora, 4);
        if (!empty($disp['disponible'])) { return (string) $rid; }
    }
    return (string) $activos[0];
}

function avanzarYAceptar(PartidaService $service, array &$p, int $horas, string $root): void
{
    $service->avanzarReloj($p, $horas);
    $maxIter = 5;
    while (($p['llegadas']['candidato_activo'] ?? null) !== null && $maxIter-- > 0) {
        $acomp = encontrarAcompanante($p);
        if ($acomp === null) { break; }
        $r = CandidatoLlegadaEngine::aceptar($p, $root, null, null, $acomp);
        if (!($r['ok'] ?? false)) { break; }
        $service->avanzarReloj($p, 1);
    }
}

function contarEncuentros(array $partida): array
{
    $porDia = [];
    $porPar = [];
    $porTipo = [];
    $porParticipante = [];

    foreach ($partida['encuentros'] ?? [] as $enc) {
        $dia = (int) ($enc['dia'] ?? 0);
        $tipo = $enc['tipo'] ?? '?';
        $participantes = $enc['participantes'] ?? [];
        $resultado = $enc['resultado'] ?? null;

        if ($dia > 0) {
            $porDia[$dia] = ($porDia[$dia] ?? 0) + 1;
        }
        $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;

        if (count($participantes) === 2) {
            $par = implode('-', array_map('strval', $participantes));
            $porPar[$par] = ($porPar[$par] ?? 0) + 1;
        }

        foreach ($participantes as $pid) {
            $pid = (string) $pid;
            $porParticipante[$pid] = ($porParticipante[$pid] ?? 0) + 1;
        }
    }

    return ['porDia' => $porDia, 'porPar' => $porPar, 'porTipo' => $porTipo, 'porParticipante' => $porParticipante];
}

function calcularDensidad(array $partida, int $diaSnapshot): array
{
    $activos = CapacidadViviendas::residentesActivos($partida);
    $n = count($activos);
    if ($n < 2) {
        return ['n' => $n, 'pares_posibles' => 0, 'pares_conectados' => 0, 'pct_conectados' => 0,
                'sin_interaccion' => $n, 'con_1' => 0, 'con_2plus' => 0, 'con_significativa' => 0,
                'pct_significativa' => 0, 'por_participante' => [], 'aislados' => []];
    }

    $paresPosibles = $n * ($n - 1) / 2;
    $interacciones = [];
    $paresConectados = 0;
    $paresSignificativa = 0;

    foreach ($activos as $rid) {
        $interacciones[$rid] = ['total' => 0, 'significativas' => 0, 'pares' => [], 'nombre' => IdentidadPublica::nombre($partida, $rid)];
    }

    for ($i = 0; $i < count($activos); $i++) {
        for ($j = $i + 1; $j < count($activos); $j++) {
            $a = $activos[$i];
            $b = $activos[$j];
            if (!RelacionEngine::seConocen($partida, $a, $b)) { continue; }

            $socialAB = RelacionEngine::socialHacia($partida, $a, $b);
            $socialBA = RelacionEngine::socialHacia($partida, $b, $a);
            $valorAB = $socialAB['valor'] ?? 0;
            $valorBA = $socialBA['valor'] ?? 0;
            $maxSocial = max($valorAB, $valorBA);

            if ($maxSocial > 0) {
                $paresConectados++;
                $interacciones[$a]['total']++;
                $interacciones[$a]['pares'][] = (string) $b;
                $interacciones[$b]['total']++;
                $interacciones[$b]['pares'][] = (string) $a;
            }
            if ($maxSocial >= 6) {
                $paresSignificativa++;
                $interacciones[$a]['significativas']++;
                $interacciones[$b]['significativas']++;
            }
        }
    }

    $sinInt = 0; $con1 = 0; $con2plus = 0; $aislados = [];
    foreach ($interacciones as $rid => $data) {
        if ($data['total'] === 0) { $sinInt++; $aislados[] = $data['nombre']; }
        elseif ($data['total'] === 1) { $con1++; }
        else { $con2plus++; }
    }

    $pctConectados = $paresPosibles > 0 ? ($paresConectados / $paresPosibles) * 100 : 0;
    $pctSignificativa = $paresPosibles > 0 ? ($paresSignificativa / $paresPosibles) * 100 : 0;

    return [
        'n' => $n, 'pares_posibles' => $paresPosibles, 'pares_conectados' => $paresConectados,
        'pct_conectados' => round($pctConectados, 1),
        'sin_interaccion' => $sinInt, 'con_1' => $con1, 'con_2plus' => $con2plus,
        'con_significativa' => $paresSignificativa, 'pct_significativa' => round($pctSignificativa, 1),
        'por_participante' => $interacciones, 'aislados' => $aislados,
    ];
}

$allData = [];
$allDensity = [];

foreach ($seeds as $seed) {
    $t0 = microtime(true);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $p['tutorial']['jugable_completado'] = true;
    CandidatoLlegadaEngine::activarModoNormal($p, $root);

    $popByDay = [];
    $encuentrosPorDia = [];
    $densitySnapshots = [];

    for ($dia = 1; $dia <= $maxDay; $dia++) {
        avanzarYAceptar($service, $p, 24, $root);

        $activos = CapacidadViviendas::residentesActivos($p);
        $popByDay[$dia] = count($activos);

        // Contar encuentros de ESTE día
        $encHoy = 0;
        foreach ($p['encuentros'] ?? [] as $enc) {
            if ((int) ($enc['dia'] ?? 0) === $dia) { $encHoy++; }
        }
        $encuentrosPorDia[$dia] = $encHoy;

        // Density snapshot
        if (in_array($dia, $checkDays)) {
            $densitySnapshots[$dia] = calcularDensidad($p, $dia);
        }
    }

    // Resumen de la seed
    $encuentros = contarEncuentros($p);
    $totalEnc = count($p['encuentros'] ?? []);
    $elapsed = number_format(microtime(true) - $t0, 1);

    // Parejas
    $romCount = 0;
    $ids = array_map('strval', array_keys($p['residentes'] ?? []));
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $est = ParejaEngine::estado($p, $ids[$i], $ids[$j]);
            if (($est['fase'] ?? '') === 'pareja') { $romCount++; }
        }
    }

    echo "Seed {$seed} ({$elapsed}s): pop_max=" . max($popByDay) . " enc_total={$totalEnc} parejas={$romCount}\n";

    $allData[$seed] = [
        'pop' => $popByDay,
        'encuentros_total' => $totalEnc,
        'encuentros_por_dia' => $encuentrosPorDia,
        'encuentros_por_par' => $encuentros['porPar'],
        'encuentros_por_tipo' => $encuentros['porTipo'],
        'encuentros_por_participante' => $encuentros['porParticipante'],
        'romances' => $romCount,
    ];
    $allDensity[$seed] = $densitySnapshots;
}

// === TABLA POBLACIÓN ===
echo "\n=== POBLACIÓN POR DÍA (media) ===\n";
$hdr = str_pad('', 8);
foreach ($checkDays as $d) { $hdr .= str_pad("D{$d}", 5); }
echo $hdr . "\n";
$line = str_pad('media', 8);
foreach ($checkDays as $d) {
    $vals = array_map(fn($data) => $data['pop'][$d] ?? 0, $allData);
    $line .= str_pad(number_format(array_sum($vals) / count($vals), 1), 5);
}
echo $line . "\n";

// === DENSIDAD POR DÍA ===
echo "\n=== DENSIDAD SOCIAL ===\n";
echo str_pad('Día', 5) . str_pad('Pares%', 8) . str_pad('SinInt', 7) . str_pad('Con1', 6) . str_pad('Con2+', 6) . str_pad('Sig%', 7) . "Aislados\n";
echo str_repeat('-', 60) . "\n";

foreach ($checkDays as $d) {
    $pctConectados = []; $sinInt = []; $con1 = []; $con2plus = []; $pctSig = []; $aisladosTotal = 0;
    foreach ($allDensity as $seed => $snapshots) {
        if (!isset($snapshots[$d])) { continue; }
        $s = $snapshots[$d];
        $pctConectados[] = $s['pct_conectados'];
        $sinInt[] = $s['sin_interaccion'];
        $con1[] = $s['con_1'];
        $con2plus[] = $s['con_2plus'];
        $pctSig[] = $s['pct_significativa'];
        $aisladosTotal += count($s['aislados']);
    }
    $n = count($pctConectados);
    if ($n === 0) { continue; }
    echo str_pad("D{$d}", 5)
        . str_pad(number_format(array_sum($pctConectados)/$n, 1) . '%', 8)
        . str_pad(number_format(array_sum($sinInt)/$n, 1), 7)
        . str_pad(number_format(array_sum($con1)/$n, 1), 6)
        . str_pad(number_format(array_sum($con2plus)/$n, 1), 6)
        . str_pad(number_format(array_sum($pctSig)/$n, 1) . '%', 7)
        . number_format($aisladosTotal / $n, 1) . "\n";
}

// === DISTRIBUCIÓN POR PARTICIPANTE ===
echo "\n=== DISTRIBUCIÓN (media encuentros/residente a D30) ===\n";
$allP30 = [];
foreach ($allData as $seed => $data) {
    foreach ($data['encuentros_por_participante'] as $pid => $count) {
        $allP30[] = $count;
    }
}
if ($allP30 !== []) {
    sort($allP30);
    $n = count($allP30);
    echo "  media=" . number_format(array_sum($allP30)/$n, 1)
        . " mediana=" . $allP30[(int)($n/2)]
        . " min=" . min($allP30) . " max=" . max($allP30) . "\n";

    // Top/bottom 25%
    $q1 = $allP30[(int)($n * 0.25)];
    $q3 = $allP30[(int)($n * 0.75)];
    $bottom25 = array_slice($allP30, 0, (int)($n * 0.25));
    $top25 = array_slice($allP30, (int)($n * 0.75));
    echo "  Q1=" . $q1 . " Q3=" . $q3 . "\n";
    echo "  bottom25 media=" . number_format(array_sum($bottom25)/max(1,count($bottom25)), 1)
        . " top25 media=" . number_format(array_sum($top25)/max(1,count($top25)), 1) . "\n";
}

// === PARES MÁS REPETIDOS ===
echo "\n=== TOP 10 PARES MÁS REPETIDOS ===\n";
$paresGlobal = [];
foreach ($allData as $data) {
    foreach ($data['encuentros_por_par'] as $par => $count) {
        $paresGlobal[$par] = ($paresGlobal[$par] ?? 0) + $count;
    }
}
arsort($paresGlobal);
$i = 0;
foreach ($paresGlobal as $par => $count) {
    if ($i++ >= 10) { break; }
    echo "  {$par}: {$count}\n";
}

// === TIPOS DE ENCUENTRO ===
echo "\n=== TIPOS DE ENCUENTRO ===\n";
$tiposGlobal = [];
foreach ($allData as $data) {
    foreach ($data['encuentros_por_tipo'] as $tipo => $count) {
        $tiposGlobal[$tipo] = ($tiposGlobal[$tipo] ?? 0) + $count;
    }
}
arsort($tiposGlobal);
foreach ($tiposGlobal as $tipo => $count) {
    $avg = number_format($count / count($allData), 1);
    echo "  {$tipo}: {$count} total ({$avg}/seed)\n";
}

// === ENCUENTROS POR DÍA (media) ===
echo "\n=== ENCUENTROS POR DÍA (media) ===\n";
foreach ($checkDays as $d) {
    $vals = array_map(fn($data) => $data['encuentros_por_dia'][$d] ?? 0, $allData);
    $mean = array_sum($vals) / count($vals);
    echo "  D{$d}: " . number_format($mean, 1) . "\n";
}

echo "\nAHT-P4 BASELINE COMPLETADA\n";
