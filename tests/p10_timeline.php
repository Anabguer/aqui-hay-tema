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

function parKey(string $a, string $b): string {
    $ids = [$a, $b];
    sort($ids);
    return $ids[0] . '/' . $ids[1];
}

function nombreRes(array $p, string $id): string {
    return $p['residentes'][$id]['nombre'] ?? substr($id, -3);
}

function clasificarRapido(array $msg): string {
    $tipo = $msg['tipo'] ?? '';
    if ($tipo === 'cotilleo_autonomo') return 'D';
    if ($tipo === 'cotilleo_patron') return 'B';
    if ($tipo === 'cotilleo_hito') return 'A';
    if ($tipo === 'senal_romantica' || $tipo === 'progresion_romantica') return 'B';
    if ($tipo === 'cotilleo_casual_descubrimiento') return 'C';
    if ($tipo === 'cotilleo') {
        $texto = $msg['texto'] ?? '';
        if (stripos($texto, 'cita') !== false) return 'A';
        if (stripos($texto, 'tensa') !== false || stripos($texto, 'cabezota') !== false) return 'B';
        if (stripos($texto, 'apunta:') !== false) return 'C';
        return 'C';
    }
    return 'D';
}

$DIAS = 20;
$seed = 'p10-timeline';
$p = $service->nuevaPartida('juego_v1', $seed);
$service->avanzarRelojPasoAPaso($p, 24);
aceptarCandidatos($service, $p);

$resIds = array_keys($p['residentes']);
$nombreMap = [];
foreach ($resIds as $id) {
    $nombreMap[$id] = nombreRes($p, $id);
}

$timeline = [];
$hitosPorPar = [];

for ($d = 1; $d <= $DIAS; $d++) {
    $service->avanzarRelojPasoAPaso($p, 24);

    for ($ri = 0; $ri < count($resIds); $ri++) {
        for ($rj = $ri + 1; $rj < count($resIds); $rj++) {
            foreach (RelacionBitacora::entre($p, $resIds[$ri], $resIds[$rj]) as $h) {
                $dia = $h['fecha']['dia'] ?? 0;
                if ($dia === $d) {
                    $par = parKey($resIds[$ri], $resIds[$rj]);
                    $hitosPorPar[$par][] = ['dia' => $d, 'tipo' => $h['tipo'] ?? ''];
                }
            }
        }
    }

    $diaCots = cotilleosPorDia($p, $d);
    foreach ($diaCots as $msg) {
        $actores = $msg['actores'] ?? [];
        $par = count($actores) >= 2 ? parKey($actores[0], $actores[1]) : 'solo';
        $clase = clasificarRapido($msg);
        $texto = trim($msg['texto'] ?? '');

        $timeline[] = [
            'dia' => $d, 'par' => $par, 'clase' => $clase,
            'tipo' => $msg['tipo'] ?? '', 'texto' => substr($texto, 0, 180),
            'actores' => $actores,
        ];
    }
}

$porPar = [];
foreach ($timeline as $t) {
    if ($t['par'] === 'solo') continue;
    $porPar[$t['par']][] = $t;
}

arsort($porPar);

echo "=== AHT-P10: TIMELINE POR PAREJA — seed={$seed} ===\n\n";
echo "Residentes: " . implode(', ', array_map(fn($id) => $nombreMap[$id] ?? $id, $resIds)) . "\n\n";

$parejasAnalizadas = 0;
foreach ($porPar as $par => $cots) {
    if (count($cots) < 2) continue;
    if ($parejasAnalizadas >= 12) break;
    $parejasAnalizadas++;

    $nombres = explode('/', $par);
    $n1 = $nombreMap[$nombres[0]] ?? $nombres[0];
    $n2 = $nombreMap[$nombres[1]] ?? $nombres[1];

    echo "=== {$n1} ↔ {$n2} (" . count($cots) . " cotilleos) ===\n";

    $hitos = $hitosPorPar[$par] ?? [];
    if ($hitos !== []) {
        echo "  HITOS REALES: ";
        foreach ($hitos as $h) {
            echo "D{$h['dia']}:{$h['tipo']} ";
        }
        echo "\n";
    }

    echo "  COTILLEOS:\n";
    foreach ($cots as $c) {
        $tag = match($c['clase']) { 'A' => '★HITO', 'B' => '→PROG', 'C' => '○AISL', 'D' => '·RUID' };
        $mem = in_array($c['tipo'], ['cotilleo_hito', 'senal_romantica', 'progresion_romantica'], true) ? '[conocido]' : '';
        echo "    D{$c['dia']} [{$tag}] {$mem} {$c['texto']}\n";
    }

    $conMemoria = 0;
    $sinMemoria = 0;
    foreach ($cots as $i => $c) {
        $prevCots = array_slice($cots, 0, $i);
        if ($prevCots !== []) $conMemoria++; else $sinMemoria++;
    }
    $pctMem = round($conMemoria / max(1, count($cots)) * 100);
    echo "  MEMORIA: {$conMemoria}/" . count($cots) . " con contexto previo ({$pctMem}%)\n\n";
}

echo "\n=== DONE ===\n";
