<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\RelacionBitacora;
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

function cotilleosPorDia(array $p, int $dia): array {
    return array_filter($p['buzon'] ?? [], fn($m) => ($m['canal'] ?? '') === 'cotilleo' && ($m['dia'] ?? 0) === $dia);
}

function hitosEntre(array $p, string $a, string $b): array {
    $ids = [$a, $b];
    sort($ids);
    $hits = [];
    foreach (RelacionBitacora::entre($p, $ids[0], $ids[1]) as $h) {
        $hits[] = $h;
    }
    return $hits;
}

function nombreRes(array $p, string $id): string {
    return $p['residentes'][$id]['nombre'] ?? substr($id, -3);
}

function parKey(string $a, string $b): string {
    $ids = [$a, $b];
    sort($ids);
    return $ids[0] . '/' . $ids[1];
}

function clasificarCotilleo(array $msg, array $p): string {
    $tipo = $msg['tipo'] ?? '';
    $texto = $msg['texto'] ?? '';
    $actores = $msg['actores'] ?? [];
    $eventoId = $msg['origen']['evento_id'] ?? '';

    if ($tipo === 'cotilleo_autonomo') return 'D';
    if ($tipo === 'cotilleo_patron') return 'B';

    if ($tipo === 'cotilleo_hito') {
        $hitoTipo = $msg['hito_tipo'] ?? '';
        if (in_array($hitoTipo, ['se_conocieron', 'primera_cita', 'ruptura', 'inicio_pareja', 'crisis'], true)) return 'A';
        return 'B';
    }

    if ($tipo === 'senal_romantica' || $tipo === 'progresion_romantica') return 'B';
    if ($tipo === 'cotilleo_casual_descubrimiento') return 'C';

    if ($tipo === 'cotilleo' && count($actores) >= 2) {
        $par = parKey($actores[0], $actores[1]);
        $hits = hitosEntre($p, $actores[0], $actores[1]);
        if (stripos($texto, 'cita') !== false) return 'A';
        if (stripos($texto, 'tensa') !== false || stripos($texto, 'conflicto') !== false || stripos($texto, 'cabezota') !== false) return 'B';
        if (stripos($texto, 'apunta:') !== false) return 'C';
        if (count($hits) >= 2) return 'B';
        return 'C';
    }

    return 'D';
}

function tieneMemoria(array $msg, array $p, array $historial): bool {
    $actores = $msg['actores'] ?? [];
    if (count($actores) < 2) return false;
    $par = parKey($actores[0], $actores[1]);
    $previos = $historial[$par] ?? [];
    return count($previos) > 0;
}

function detectarContradiccion(array $msg, array $p): ?string {
    $actores = $msg['actores'] ?? [];
    $texto = $msg['texto'] ?? '';
    $tipo = $msg['tipo'] ?? '';

    if (count($actores) < 2) return null;

    if (stripos($texto, 'primera vez') !== false || stripos($texto, 'ya no son desconocidos') !== false) {
        $hits = hitosEntre($p, $actores[0], $actores[1]);
        $seConocieron = false;
        foreach ($hits as $h) {
            if (($h['tipo'] ?? '') === 'se_conocieron') { $seConocieron = true; break; }
        }
        if (!$seConocieron) return 'Dice primera vez pero no hay hito se_conocieron';
    }

    if (stripos($texto, 'cita') !== false && stripos($texto, 'Primera cita') !== false) {
        $hits = hitosEntre($p, $actores[0], $actores[1]);
        $primeraCita = false;
        foreach ($hits as $h) {
            if (($h['tipo'] ?? '') === 'primera_cita') { $primeraCita = true; break; }
        }
        if (!$primeraCita) return 'Dice primera_cita pero no hay hito primera_cita';
    }

    return null;
}

$SEEDS = 5;
$DIAS = 20;

echo "=== AHT-P10: TRAYECTORIA + CLASIFICACIÓN — {$SEEDS}x{$DIAS} ===\n\n";

$globalClase = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
$globalMemoria = ['con' => 0, 'sin' => 0];
$globalContradicciones = [];
$prioridadParejas = [];

for ($si = 1; $si <= $SEEDS; $si++) {
    $seed = sprintf('p10-traj-%02d', $si);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $service->avanzarRelojPasoAPaso($p, 24);
    aceptarCandidatos($service, $p);

    $parejas = [];
    $historial = [];
    $allCots = [];
    $allHitos = [];

    for ($d = 1; $d <= $DIAS; $d++) {
        $service->avanzarRelojPasoAPaso($p, 24);

        $resIds = array_keys($p['residentes']);
        for ($ri = 0; $ri < count($resIds); $ri++) {
            for ($rj = $ri + 1; $rj < count($resIds); $rj++) {
                foreach (RelacionBitacora::entre($p, $resIds[$ri], $resIds[$rj]) as $h) {
                    $dia = $h['fecha']['dia'] ?? 0;
                    if ($dia === $d) {
                        $par = parKey($h['participantes'][0] ?? '', $h['participantes'][1] ?? '');
                        $allHitos[] = ['dia' => $d, 'tipo' => $h['tipo'] ?? '', 'par' => $par];
                    }
                }
            }
        }

        $diaCots = cotilleosPorDia($p, $d);
        foreach ($diaCots as $msg) {
            $actores = $msg['actores'] ?? [];
            $par = count($actores) >= 2 ? parKey($actores[0], $actores[1]) : 'solo';
            $clase = clasificarCotilleo($msg, $p);
            $conMemoria = tieneMemoria($msg, $p, $historial);
            $contradiccion = detectarContradiccion($msg, $p);

            $allCots[] = [
                'dia' => $d, 'par' => $par, 'clase' => $clase,
                'tipo' => $msg['tipo'] ?? '', 'texto' => substr($msg['texto'] ?? '', 0, 150),
                'con_memoria' => $conMemoria, 'contradiccion' => $contradiccion,
            ];

            $globalClase[$clase]++;
            if ($conMemoria) $globalMemoria['con']++; else $globalMemoria['sin']++;
            if ($contradiccion !== null) $globalContradicciones[] = "D{$d} {$par}: {$contradiccion}";

            if ($par !== 'solo') {
                $historial[$par][] = ['dia' => $d, 'clase' => $clase, 'tipo' => $msg['tipo'] ?? ''];
                if (!isset($parejas[$par])) $parejas[$par] = ['hitos' => 0, 'cots' => 0];
                $parejas[$par]['cots']++;
            }
        }
    }

    foreach ($allHitos as $h) {
        if (isset($parejas[$h['par']])) $parejas[$h['par']]['hitos']++;
    }

    arsort($parejas);
    $i = 0;
    foreach ($parejas as $par => $info) {
        if ($info['cots'] < 3) continue;
        if ($i++ >= 5) break;
        $key = $seed . ':' . $par;
        $prioridadParejas[$key] = ['seed' => $seed, 'par' => $par, 'hitos' => $info['hitos'], 'cots' => $info['cots']];
    }
}

echo "\n=== 1. CLASIFICACIÓN A/B/C/D ===\n";
$total = array_sum($globalClase);
foreach (['A', 'B', 'C', 'D'] as $cl) {
    $n = $globalClase[$cl];
    $desc = match($cl) {
        'A' => 'HITO PÚBLICO',
        'B' => 'PROGRESIÓN',
        'C' => 'HECHO AISLADO ÚTIL',
        'D' => 'RUIDO',
    };
    echo "  {$cl} ({$desc}): {$n} (" . round($n / max(1, $total) * 100, 1) . "%)\n";
}

echo "\n=== 2. MEMORIA ===\n";
$totMem = $globalMemoria['con'] + $globalMemoria['sin'];
echo "  Con contexto previo: {$globalMemoria['con']} (" . round($globalMemoria['con'] / max(1, $totMem) * 100, 1) . "%)\n";
echo "  Sin contexto previo: {$globalMemoria['sin']} (" . round($globalMemoria['sin'] / max(1, $totMem) * 100, 1) . "%)\n";

echo "\n=== 3. CONTRADICCIONES ===\n";
if ($globalContradicciones === []) {
    echo "  0 contradicciones detectadas\n";
} else {
    echo "  " . count($globalContradicciones) . " contradicciones:\n";
    foreach (array_slice($globalContradicciones, 0, 20) as $c) {
        echo "    {$c}\n";
    }
}

echo "\n=== DONE ===\n";
