<?php
declare(strict_types=1);

/**
 * AHT-P5: Baseline pasiva romance — 30 seeds D1-D30 jugador pasivo.
 * Mide: flechazos, romanceHacia, señales, 1ª cita, parejas, trayectorias.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\SenalRomantica;

$root = dirname(__DIR__);
$service = new PartidaService($root);

$seeds = [];
for ($i = 1; $i <= 30; $i++) { $seeds[] = sprintf('p5-p%02d', $i); }

echo "=== AHT-P5 BASELINE PASIVA 30 SEEDS ===\n\n";

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

function capturarEstadoRomance(array $partida, int $dia): array
{
    $activos = CapacidadViviendas::residentesActivos($partida);
    $cal = \AquiHayTema\Engine\CalibracionConfig::load(dirname(__DIR__));
    $estado = [
        'poblacion' => count($activos),
        'flechazos' => [],
        'senales' => [],
        'romance_values' => [],
        'citas_programadas' => 0,
        'primera_cita_hecha' => false,
        'parejas' => 0,
    ];

    // Flechazos
    foreach ($partida['encuentros'] ?? [] as $enc) {
        if (($enc['tipo'] ?? '') === 'flechazo' && (int) ($enc['dia'] ?? 0) <= $dia) {
            $parts = $enc['participantes'] ?? [];
            if (count($parts) >= 2) {
                $estado['flechazos'][] = [
                    'desde' => IdentidadPublica::nombre($partida, (string) $parts[0]),
                    'hacia' => IdentidadPublica::nombre($partida, (string) $parts[1]),
                    'dia' => (int) ($enc['dia'] ?? 0),
                ];
            }
        }
    }

    // Romance values and signals for all pairs
    for ($i = 0; $i < count($activos); $i++) {
        for ($j = $i + 1; $j < count($activos); $j++) {
            $a = $activos[$i];
            $b = $activos[$j];
            if (!RelacionEngine::seConocen($partida, $a, $b)) { continue; }

            $romAB = RelacionEngine::romanceHacia($partida, $a, $b);
            $romBA = RelacionEngine::romanceHacia($partida, $b, $a);

            if (($romAB ?? 0) !== 0 || ($romBA ?? 0) !== 0) {
                $estado['romance_values'][] = [
                    'par' => IdentidadPublica::nombre($partida, $a) . '→' . IdentidadPublica::nombre($partida, $b),
                    'romance' => $romAB ?? 0,
                ];
                $estado['romance_values'][] = [
                    'par' => IdentidadPublica::nombre($partida, $b) . '→' . IdentidadPublica::nombre($partida, $a),
                    'romance' => $romBA ?? 0,
                ];
            }

            $senal = SenalRomantica::desdeHacia($partida, $a, $b, $cal);
            if (!empty($senal['ok'])) {
                $estado['senales'][] = [
                    'desde' => IdentidadPublica::nombre($partida, $a),
                    'hacia' => IdentidadPublica::nombre($partida, $b),
                    'motivo' => $senal['motivo'],
                ];
            }
            $senal2 = SenalRomantica::desdeHacia($partida, $b, $a, $cal);
            if (!empty($senal2['ok'])) {
                $estado['senales'][] = [
                    'desde' => IdentidadPublica::nombre($partida, $b),
                    'hacia' => IdentidadPublica::nombre($partida, $a),
                    'motivo' => $senal2['motivo'],
                ];
            }
        }
    }

    // Cita/primera_cita encounters
    foreach ($partida['encuentros'] ?? [] as $enc) {
        $t = $enc['tipo'] ?? '';
        $d = (int) ($enc['dia'] ?? 0);
        if (in_array($t, ['cita', 'primera_cita', 'romantico']) && $d <= $dia) {
            $estado['citas_programadas']++;
        }
    }

    // Primera cita hito
    foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
        if (($h['tipo'] ?? '') === 'primera_cita' && (int) ($h['dia'] ?? 0) <= $dia) {
            $estado['primera_cita_hecha'] = true;
            break;
        }
    }

    // Parejas
    for ($i = 0; $i < count($activos); $i++) {
        for ($j = $i + 1; $j < count($activos); $j++) {
            $est = ParejaEngine::estado($partida, $activos[$i], $activos[$j]);
            if ($est === 'pareja') { $estado['parejas']++; }
        }
    }

    return $estado;
}

$allData = [];
$primerMeGusta = [];
$primerCita = [];
$primerPareja = [];

foreach ($seeds as $seed) {
    $t0 = microtime(true);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $p['tutorial']['jugable_completado'] = true;
    CandidatoLlegadaEngine::activarModoNormal($p, $root);

    $snapshots = [];
    $milestones = [
        'flechazo' => null,
        'senal' => null,
        'primera_cita' => null,
        'pareja' => null,
    ];

    for ($dia = 1; $dia <= 30; $dia++) {
        avanzarYAceptar($service, $p, 24, $root);

        // Capturar estado romance
        $snapshots[$dia] = capturarEstadoRomance($p, $dia);

        // Milestones
        if ($milestones['flechazo'] === null && $snapshots[$dia]['flechazos'] !== []) {
            $milestones['flechazo'] = $dia;
        }
        if ($milestones['senal'] === null && $snapshots[$dia]['senales'] !== []) {
            $milestones['senal'] = $dia;
        }
        if ($milestones['primera_cita'] === null && $snapshots[$dia]['primera_cita_hecha']) {
            $milestones['primera_cita'] = $dia;
        }
        if ($milestones['pareja'] === null && $snapshots[$dia]['parejas'] > 0) {
            $milestones['pareja'] = $dia;
        }
    }

    $elapsed = number_format(microtime(true) - $t0, 1);
    echo "Seed {$seed} ({$elapsed}s): flechazo=D" . ($milestones['flechazo'] ?? 'N/A')
        . " senal=D" . ($milestones['senal'] ?? 'N/A')
        . " 1ªcita=D" . ($milestones['primera_cita'] ?? 'N/A')
        . " pareja=D" . ($milestones['pareja'] ?? 'N/A') . "\n";

    $allData[$seed] = [
        'snapshots' => $snapshots,
        'milestones' => $milestones,
    ];

    if ($milestones['senal'] !== null) { $primerMeGusta[] = $milestones['senal']; }
    if ($milestones['primera_cita'] !== null) { $primerCita[] = $milestones['primera_cita']; }
    if ($milestones['pareja'] !== null) { $primerPareja[] = $milestones['pareja']; }
}

// === RESUMEN ===
echo "\n=== RESUMEN MILESTONES ===\n";

function stats(array $vals): string
{
    if ($vals === []) { return "NUNCA (0/30)"; }
    sort($vals);
    $n = count($vals);
    $media = number_format(array_sum($vals) / $n, 1);
    $mediana = $vals[(int) ($n / 2)];
    $p25 = $vals[(int) ($n * 0.25)] ?? $vals[0];
    $p75 = $vals[(int) ($n * 0.75)] ?? $vals[$n - 1];
    return "n={$n}/30 media={$media} mediana={$mediana} p25={$p25} p75={$p75} min=" . min($vals) . " max=" . max($vals);
}

echo "Primer señal/tilin:   " . stats($primerMeGusta) . "\n";
echo "Primera 1ª cita:      " . stats($primerCita) . "\n";
echo "Primera pareja:       " . stats($primerPareja) . "\n";

// === PAREJAS POR DÍA ===
echo "\n=== PAREJAS D10/D15/D20/D25/D30 ===\n";
foreach ([10, 15, 20, 25, 30] as $d) {
    $count = 0;
    foreach ($allData as $data) {
        if (($data['snapshots'][$d]['parejas'] ?? 0) > 0) { $count++; }
    }
    echo "  D{$d}: {$count}/30 seeds\n";
}

// === DISTRIBUCIÓN ROMANCE ===
echo "\n=== DISTRIBUCIÓN ROMANCE A D30 ===\n";
$allRom = [];
foreach ($allData as $data) {
    foreach ($data['snapshots'][30]['romance_values'] ?? [] as $rv) {
        $allRom[] = $rv['romance'];
    }
}
if ($allRom !== []) {
    $pos = array_filter($allRom, fn($v) => $v > 0);
    $neg = array_filter($allRom, fn($v) => $v < 0);
    echo "  Total valores romance: " . count($allRom) . "\n";
    echo "  Positivos (>0): " . count($pos) . " media=" . number_format(array_sum($pos) / max(1, count($pos)), 1) . "\n";
    echo "  Negativos (<0): " . count($neg) . "\n";
    echo "  Cero: " . (count($allRom) - count($pos) - count($neg)) . "\n";
}

// === D5 CONTROL ===
echo "\n=== D5 CONTROL ===\n";
$d5conPareja = 0;
$d5conSenal = 0;
foreach ($allData as $data) {
    if (($data['snapshots'][5]['parejas'] ?? 0) > 0) { $d5conPareja++; }
    if (($data['snapshots'][5]['senales'] ?? []) !== []) { $d5conSenal++; }
}
echo "  Parejas D5: {$d5conPareja}/30 (target: 0)\n";
echo "  Señales D5: {$d5conSenal}/30\n";

echo "\nAHT-P5 BASELINE PASIVA COMPLETADA\n";
