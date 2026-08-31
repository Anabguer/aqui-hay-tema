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

function hitosEntre(array $p, string $a, string $b): array {
    return iterator_to_array(RelacionBitacora::entre($p, $a, $b));
}

function clasificarCotilleo(array $msg, array $p): string {
    $tipo = $msg['tipo'] ?? '';
    $texto = $msg['texto'] ?? '';
    $actores = $msg['actores'] ?? [];

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
        if (stripos($texto, 'cita') !== false) return 'A';
        if (stripos($texto, 'tensa') !== false || stripos($texto, 'conflicto') !== false || stripos($texto, 'cabezota') !== false) return 'B';
        if (stripos($texto, 'apunta:') !== false) return 'C';
        $hits = hitosEntre($p, $actores[0], $actores[1]);
        if (count($hits) >= 2) return 'B';
        return 'C';
    }
    return 'D';
}

function clasificarRuido(array $msg, array $p): string {
    $tipo = $msg['tipo'] ?? '';
    $texto = $msg['texto'] ?? '';
    $actores = $msg['actores'] ?? [];

    if ($tipo === 'cotilleo_autonomo') {
        $autTipo = $msg['autonomo_tipo'] ?? '';
        if (stripos($texto, ' rutina') !== false || stripos($texto, 'paseo') !== false) return 'D6';
        if (stripos($texto, ' excursion') !== false) return 'D6';
        return 'D2';
    }
    if ($tipo === 'estado_emocional') return 'D5';
    if ($tipo === 'cotilleo' && count($actores) >= 2) {
        $hits = hitosEntre($p, $actores[0], $actores[1]);
        if (stripos($texto, 'parecen llevarse bien') !== false) {
            if (count($hits) === 0) return 'D3';
            return 'D4';
        }
        if (stripos($texto, 'vuelven a') !== false || stripos($texto, 'otra vez') !== false || stripos($texto, 'ya no es casualidad') !== false || stripos($texto, 'costumbre') !== false || stripos($texto, 'parece que se buscan') !== false) {
            return 'D4';
        }
        return 'D2';
    }
    return 'D2';
}

$SEEDS = 15;
$DIAS = 20;

echo "=== AHT-P10.2: D NOISE AUDIT — {$SEEDS}x{$DIAS} ===\n\n";

$allD = [];
$familias = [];
$fuentesD = [];
$tiposD = [];
$totalCots = 0;
$totalD = 0;

for ($si = 1; $si <= $SEEDS; $si++) {
    $seed = sprintf('p10-2-%02d', $si);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $service->avanzarRelojPasoAPaso($p, 24);
    aceptarCandidatos($service, $p);

    for ($d = 1; $d <= $DIAS; $d++) {
        $service->avanzarRelojPasoAPaso($p, 24);
        $diaCots = cotilleosPorDia($p, $d);

        foreach ($diaCots as $msg) {
            $totalCots++;
            $clase = clasificarCotilleo($msg, $p);
            if ($clase !== 'D') continue;
            $totalD++;

            $tipo = $msg['tipo'] ?? 'desconocido';
            $texto = trim($msg['texto'] ?? '');
            $actores = $msg['actores'] ?? [];
            $par = count($actores) >= 2 ? parKey($actores[0], $actores[1]) : 'solo';
            $motivo = clasificarRuido($msg, $p);

            $tiposD[$tipo] = ($tiposD[$tipo] ?? 0) + 1;
            $fuentesD[$motivo] = ($fuentesD[$motivo] ?? 0) + 1;

            $key = $tipo . '|' . substr($texto, 0, 60);
            if (!isset($familias[$key])) {
                $familias[$key] = [
                    'tipo' => $tipo,
                    'texto_corto' => substr($texto, 0, 80),
                    'count' => 0,
                    'motivo' => $motivo,
                    'ejemplos' => [],
                ];
            }
            $familias[$key]['count']++;
            if (count($familias[$key]['ejemplos']) < 3) {
                $nombres = array_map(fn($id) => nombreRes($p, $id), $actores);
                $familias[$key]['ejemplos'][] = "D{$d} [" . implode('/', $nombres) . "] {$texto}";
            }
        }
    }
}

uasort($familias, fn($a, $b) => $b['count'] <=> $a['count']);

echo "=== RESUMEN GLOBAL ===\n";
echo "Total cotilleos: {$totalCots}\n";
echo "Total D (ruido): {$totalD} (" . round($totalD / max(1, $totalCots) * 100, 1) . "%)\n\n";

echo "=== 1. TIPOS QUE GENERAN D ===\n";
arsort($tiposD);
foreach ($tiposD as $tipo => $count) {
    echo "  {$tipo}: {$count} (" . round($count / max(1, $totalD) * 100, 1) . "%)\n";
}

echo "\n=== 2. MOTIVO DE RUIDO (D1-D7) ===\n";
$motivosLabel = [
    'D1' => 'hecho trivial',
    'D2' => 'copy genérico',
    'D3' => 'falta contexto previo',
    'D4' => 'repita info conocida',
    'D5' => 'interioridad no observable',
    'D6' => 'evento no merece cotilleo',
    'D7' => 'otro',
];
arsort($fuentesD);
foreach ($fuentesD as $motivo => $count) {
    $label = $motivosLabel[$motivo] ?? $motivo;
    echo "  {$motivo} ({$label}): {$count} (" . round($count / max(1, $totalD) * 100, 1) . "%)\n";
}

echo "\n=== 3. TOP 20 FAMILIAS DE RUIDO ===\n";
$i = 0;
foreach ($familias as $key => $f) {
    if ($i++ >= 20) break;
    echo "\n  #{$i} [{$f['tipo']}] ({$f['count']}x) motivo={$f['motivo']}\n";
    echo "     \"{$f['texto_corto']}\"\n";
    foreach ($f['ejemplos'] as $ej) {
        echo "     └ {$ej}\n";
    }
}

echo "\n=== DONE ===\n";
