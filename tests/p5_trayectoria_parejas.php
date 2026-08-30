<?php
declare(strict_types=1);

/**
 * AHT-P5: Verificar trayectoria de parejas formadas.
 * Para cada pareja, ¿hubo historia previa visible?
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionBandas;
use AquiHayTema\Engine\RelacionBitacora;

$root = dirname(__DIR__);
$service = new PartidaService($root);

echo "=== AHT-P5 TRAYECTORIA PAREJAS ===\n\n";

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

$seeds = ['p5-p01','p5-p05','p5-p08','p5-p15','p5-p16','p5-p19','p5-p23','p5-p25','p5-p26','p5-p27','p5-p28','p5-p29'];

foreach ($seeds as $seed) {
    $p = $service->nuevaPartida('juego_v1', $seed);
    $p['tutorial']['jugable_completado'] = true;
    CandidatoLlegadaEngine::activarModoNormal($p, $root);

    for ($dia = 1; $dia <= 30; $dia++) {
        avanzarYAceptar($service, $p, 24, $root);
    }

    $activos = CapacidadViviendas::residentesActivos($p);
    $encontradas = [];

    for ($i = 0; $i < count($activos); $i++) {
        for ($j = $i + 1; $j < count($activos); $j++) {
            $a = $activos[$i];
            $b = $activos[$j];
            $est = ParejaEngine::estado($p, $a, $b);
            if ($est !== 'pareja') { continue; }

            $nomA = IdentidadPublica::nombre($p, $a);
            $nomB = IdentidadPublica::nombre($p, $b);

            // Buscar hitos en bitácora
            $hitos = RelacionBitacora::entre($p, $a, $b);
            $hitoTipos = array_map(fn($h) => ($h['tipo'] ?? '?') . '@D' . ($h['dia'] ?? '?'), $hitos);

            // Romance directional
            $romAB = RelacionEngine::romanceHacia($p, $a, $b) ?? 0;
            $romBA = RelacionEngine::romanceHacia($p, $b, $a) ?? 0;

            // Social directional
            $socAB = RelacionEngine::socialHacia($p, $a, $b);
            $socBA = RelacionEngine::socialHacia($p, $b, $a);

            // Fecha inicio pareja
            $rel = RelacionEngine::obtenerEntre($p, $a, $b)['romance'] ?? [];
            $inicio = $rel['fecha_inicio'] ?? [];

            // Contar encuentros previos entre ellos
            $encPrevios = 0;
            foreach ($p['encuentros'] ?? [] as $enc) {
                $parts = $enc['participantes'] ?? [];
                if (in_array($a, $parts) && in_array($b, $parts)) {
                    $encPrevios++;
                }
            }

            $dInicio = $inicio['dia'] ?? '?';

            // Clasificar trayectoria
            $clasificacion = 'D';
            $hitosRelevantes = array_filter($hitos, fn($h) => in_array($h['tipo'] ?? '', [
                'flechazo', 'hito_romantico', 'inicio_pareja', 'primera_cita',
            ]));
            $hitosDias = array_map(fn($h) => (int) ($h['dia'] ?? 0), $hitosRelevantes);
            $hitosDias = array_filter($hitosDias, fn($d) => $d > 0 && $d < $dInicio);

            if (count($hitosDias) >= 2 && ($romAB > 0 || $romBA > 0)) {
                $clasificacion = 'A'; // trayectoria fuerte
            } elseif (count($hitosDias) >= 1 || $encPrevios >= 3) {
                $clasificacion = 'B'; // trayectoria suficiente
            } elseif ($encPrevios >= 1) {
                $clasificacion = 'C'; // salto brusco
            }

            echo "Seed {$seed}: {$nomA} ↔ {$nomB} (D{$dInicio})\n";
            echo "  Romance: A→B={$romAB} B→A={$romBA}\n";
            echo "  Social: A→B=" . ($socAB['valor'] ?? '?') . " B→A=" . ($socBA['valor'] ?? '?') . "\n";
            echo "  Encuentros previos: {$encPrevios}\n";
            echo "  Hitos: " . implode(', ', $hitoTipos) . "\n";
            echo "  Clasificación: {$clasificacion}\n\n";

            $encontradas[] = ['seed' => $seed, 'a' => $nomA, 'b' => $nomB, 'dia' => $dInicio, 'clas' => $clasificacion];
        }
    }

    if ($encontradas === []) {
        echo "Seed {$seed}: sin parejas\n\n";
    }
}

echo "=== RESUMEN TRAYECTORIAS ===\n";
$clases = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
foreach ($encontradas as $p) { $clases[$p['clas']]++; }
foreach ($clases as $k => $v) {
    $letras = ['A' => 'Trayectoria fuerte', 'B' => 'Suficiente', 'C' => 'Salto brusco', 'D' => 'Incoherente'];
    echo "  {$k} ({$letras[$k]}): {$v}\n";
}

echo "\nAHT-P5 TRAYECTORIA COMPLETADA\n";
