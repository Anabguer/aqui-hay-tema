<?php
declare(strict_types=1);

/**
 * AHT-P4: Control jugador activo — 5 seeds, jugador acepta llegadas y hace社会 interactions.
 * Verifica que la intervención no rompe el flujo activo.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\IniciativaSocial;

$root = dirname(__DIR__);
$service = new PartidaService($root);

$seeds = ['p4-act01', 'p4-act02', 'p4-act03', 'p4-act04', 'p4-act05'];

echo "=== AHT-P4 CONTROL JUGADOR ACTIVO (5 seeds) ===\n\n";

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

function jugadorHaceSocial(array &$p, int $dia): void
{
    $activos = CapacidadViviendas::residentesActivos($p);
    if (count($activos) < 2) { return; }
    $i = array_rand($activos);
    $j = $i;
    while ($j === $i) { $j = array_rand($activos); }
    $a = (string) $activos[$i];
    $b = (string) $activos[$j];
    $resultado = RelacionEngine::registrarContacto($p, $a, $b, 'reunion', [
        'lugar' => 'calle',
        'tipo' => 'conversacion',
        'significativo' => true,
    ]);
    $nombreA = IdentidadPublica::nombre($p, $a);
    $nombreB = IdentidadPublica::nombre($p, $b);
    echo "  [JUGADOR] {$nombreA} ↔ {$nombreB}: " . json_encode($resultado) . "\n";
}

$allData = [];

foreach ($seeds as $seed) {
    $t0 = microtime(true);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $p['tutorial']['jugable_completado'] = true;
    CandidatoLlegadaEngine::activarModoNormal($p, $root);

    $popByDay = [];
    $encuentrosPorDia = [];
    $quedadasCount = 0;
    $romanceCount = 0;
    $socialEventsCount = 0;

    for ($dia = 1; $dia <= 30; $dia++) {
        avanzarYAceptar($service, $p, 24, $root);

        // Jugador hace 2 interacciones sociales por día
        if ($dia > 1) {
            jugadorHaceSocial($p, $dia);
            jugadorHaceSocial($p, $dia);
        }

        $activos = CapacidadViviendas::residentesActivos($p);
        $popByDay[$dia] = count($activos);

        $encHoy = 0;
        foreach ($p['encuentros'] ?? [] as $enc) {
            $d = (int) ($enc['dia'] ?? 0);
            if ($d === $dia) {
                $encHoy++;
                if (str_contains($enc['tipo'] ?? '', 'quedar')) { $quedadasCount++; }
                if (str_contains($enc['tipo'] ?? '', 'romant')) { $romanceCount++; }
            }
        }
        $encuentrosPorDia[$dia] = $encHoy;

        // Contar eventos sociales (contactos del jugador)
        foreach ($p['iniciativa_social_log'] ?? [] as $ev) {
            if ((int) ($ev['dia'] ?? 0) === $dia) { $socialEventsCount++; }
        }
    }

    $encuentrosTotal = count($p['encuentros'] ?? []);
    $elapsed = number_format(microtime(true) - $t0, 1);

    echo "Seed {$seed} ({$elapsed}s): pop_max=" . max($popByDay)
        . " enc_total={$encuentrosTotal} quedadas={$quedadasCount} romances={$romanceCount}\n";

    $allData[$seed] = [
        'pop' => $popByDay,
        'encuentros_total' => $encuentrosTotal,
        'quedadas' => $quedadasCount,
        'romances' => $romanceCount,
    ];
}

echo "\n=== RESUMEN ===\n";
$checkDays = [1, 5, 10, 15, 20, 30];
foreach ($checkDays as $d) {
    $vals = array_map(fn($data) => $data['pop'][$d] ?? 0, $allData);
    $mean = number_format(array_sum($vals) / count($vals), 1);
    echo "  D{$d} pop: {$mean}\n";
}

$allEnc = array_map(fn($data) => $data['encuentros_total'], $allData);
$allQued = array_map(fn($data) => $data['quedadas'], $allData);
$allRom = array_map(fn($data) => $data['romances'], $allData);

echo "  Encuentros total: " . number_format(array_sum($allEnc)/count($allEnc), 1) . " media\n";
echo "  Quedadas total: " . number_format(array_sum($allQued)/count($allQued), 1) . " media\n";
echo "  Romances total: " . number_format(array_sum($allRom)/count($allRom), 1) . " media\n";

echo "\nAHT-P4 CONTROL ACTIVO COMPLETADO\n";
