<?php
declare(strict_types=1);

/**
 * AHT-P4: Red social detallada + rechazos autónomos.
 * 1 seed extendida con traza completa de cada seed representativa.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\IniciativaSocial;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionBandas;

$root = dirname(__DIR__);
$service = new PartidaService($root);

$seed = 'p4-red-detalle';
$maxDay = 30;

echo "=== AHT-P4 RED SOCIAL DETALLADA ===\n";
echo "Seed: {$seed}\n\n";

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

$p = $service->nuevaPartida('juego_v1', $seed);
$p['tutorial']['jugable_completado'] = true;
CandidatoLlegadaEngine::activarModoNormal($p, $root);

$snapshots = [];
$rechazosLog = [];
$iniciativaLog = [];
$encuentrosLog = [];

for ($dia = 1; $dia <= $maxDay; $dia++) {
    avanzarYAceptar($service, $p, 24, $root);

    $activos = CapacidadViviendas::residentesActivos($p);
    $n = count($activos);

    // Registrar iniciativa social del día
    foreach ($p['iniciativa_social_log'] ?? [] as $ev) {
        if ((int) ($ev['dia'] ?? 0) === $dia) {
            $iniciativaLog[] = $ev;
        }
    }

    // Registrar rechazos del día
    foreach ($p['encuentros'] ?? [] as $enc) {
        if ((int) ($enc['dia'] ?? 0) === $dia) {
            $encuentrosLog[] = [
                'dia' => $dia,
                'tipo' => $enc['tipo'] ?? '?',
                'participantes' => $enc['participantes'] ?? [],
                'resultado' => $enc['resultado']['por_participante'] ?? [],
                'intencion' => $enc['intencion'] ?? '?',
            ];
        }
    }

    // Snapshots en días clave
    if (in_array($dia, [1, 3, 5, 7, 10, 15, 20, 30])) {
        $red = [];
        for ($i = 0; $i < count($activos); $i++) {
            for ($j = $i + 1; $j < count($activos); $j++) {
                $a = $activos[$i];
                $b = $activos[$j];
                if (!RelacionEngine::seConocen($p, $a, $b)) { continue; }
                $socialAB = RelacionEngine::socialHacia($p, $a, $b);
                $socialBA = RelacionEngine::socialHacia($p, $b, $a);
                $vAB = $socialAB['valor'] ?? 0;
                $vBA = $socialBA['valor'] ?? 0;
                $bandaAB = $socialAB['banda'] ?? '?';
                $bandaBA = $socialBA['banda'] ?? '?';
                $nombreA = IdentidadPublica::nombre($p, $a);
                $nombreB = IdentidadPublica::nombre($p, $b);
                $red[] = "{$nombreA}→{$nombreB}: {$vAB}({$bandaAB}) | {$nombreB}→{$nombreA}: {$vBA}({$bandaBA})";
            }
        }
        $snapshots[$dia] = ['n' => $n, 'activos' => $activos, 'red' => $red];
    }
}

// === RED SOCIAL POR DÍA ===
foreach ($snapshots as $dia => $snap) {
    echo "--- D{$dia} ({$snap['n']} residentes) ---\n";
    foreach ($snap['activos'] as $rid) {
        $nombre = IdentidadPublica::nombre($p, $rid);
        $emo = $p['residentes'][$rid]['runtime']['estado_emocional']['id'] ?? 'neutro';
        $contactos = 0;
        foreach ($snap['activos'] as $other) {
            if ($other !== $rid && RelacionEngine::seConocen($p, $rid, $other)) {
                $contactos++;
            }
        }
        echo "  {$nombre} ({$emo}): {$contactos} contactos\n";
    }
    if ($snap['red'] !== []) {
        foreach ($snap['red'] as $line) {
            echo "    {$line}\n";
        }
    } else {
        echo "    (sin conexiones sociales)\n";
    }
    echo "\n";
}

// === INICIATIVA SOCIAL ===
echo "=== INICIATIVA SOCIAL ===\n";
$iniciativaStats = ['quedada_agendada' => 0, 'rechazada' => 0, 'sin_match' => 0, 'otro' => 0];
foreach ($iniciativaLog as $ev) {
    $resultado = $ev['resultado'] ?? 'otro';
    if (str_starts_with($resultado, 'quedada')) { $iniciativaStats['quedada_agendada']++; }
    elseif (str_contains($resultado, 'rechaz')) { $iniciativaStats['rechazada']++; }
    else { $iniciativaStats[$resultado] = ($iniciativaStats[$resultado] ?? 0) + 1; }
}
foreach ($iniciativaStats as $tipo => $count) {
    echo "  {$tipo}: {$count}\n";
}
echo "  Total intentos: " . count($iniciativaLog) . "\n";

// === ENCUENTROS POR INTENCIÓN ===
echo "\n=== ENCUENTROS POR INTENCIÓN ===\n";
$porIntencion = [];
foreach ($encuentrosLog as $enc) {
    $intencion = $enc['intencion'];
    $porIntencion[$intencion] = ($porIntencion[$intencion] ?? 0) + 1;
}
arsort($porIntencion);
foreach ($porIntencion as $intencion => $count) {
    echo "  {$intencion}: {$count}\n";
}

// === RECHAZOS (propuestas que no llegaron a encuentro) ===
echo "\n=== PROPUESTAS RECHAZADAS/CADUCADAS ===\n";
$rechazadas = 0;
$aceptadas = 0;
foreach ($p['propuestas'] ?? [] as $prop) {
    $estado = $prop['estado'] ?? '';
    if ($estado === 'rechazada') { $rechazadas++; }
    elseif ($estado === 'aceptada' || $estado === 'programada') { $aceptadas++; }
}
echo "  Aceptadas: {$aceptadas}\n";
echo "  Rechazadas: {$rechazadas}\n";
echo "  Total propuestas: " . count($p['propuestas'] ?? []) . "\n";

// === DISTRIBUCIÓN FINAL ===
echo "\n=== DISTRIBUCIÓN FINAL (D30) ===\n";
$encuentrosPorPersona = [];
$activos = CapacidadViviendas::residentesActivos($p);
foreach ($activos as $rid) { $encuentrosPorPersona[$rid] = 0; }
foreach ($encuentrosLog as $enc) {
    foreach ($enc['participantes'] as $pid) {
        $pid = (string) $pid;
        if (isset($encuentrosPorPersona[$pid])) {
            $encuentrosPorPersona[$pid]++;
        }
    }
}
foreach ($encuentrosPorPersona as $rid => $count) {
    $nombre = IdentidadPublica::nombre($p, $rid);
    $social = [];
    foreach ($activos as $other) {
        if ($other !== $rid && RelacionEngine::seConocen($p, $rid, $other)) {
            $s = RelacionEngine::socialHacia($p, $rid, $other);
            $social[] = IdentidadPublica::nombre($p, $other) . "=" . ($s['valor'] ?? 0);
        }
    }
    echo "  {$nombre}: {$count} encuentros, social=[" . implode(', ', $social) . "]\n";
}

echo "\nAHT-P4 RED SOCIAL COMPLETADA\n";
