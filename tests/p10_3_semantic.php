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
    $ids = [$a, $b]; sort($ids);
    return $ids[0] . '/' . $ids[1];
}

function hitosEntre(array $p, string $a, string $b): array {
    return iterator_to_array(RelacionBitacora::entre($p, $a, $b));
}

$PRIVACY_PATTERNS = [
    '/\bestá desanimad[oa]\b/i',
    '/\bestá triste\b/i',
    '/\bestá alegre\b/i',
    '/\bestá enfadad[oa]\b/i',
    '/\bse fija bastante\b/i',
    '/\bha decidido que\b/i',
    '/\ble apetecía\b/i',
    '/\bnecesitaba\b/',
    '/\bse siente\b/i',
    '/\bpiensa que\b/i',
    '/\bno soporta\b/i',
];

$INVENTED_BEHAVIOR = [
    '/\bestá evitando\b/i',
    '/\bha cambiado de ruta\b/i',
    '/\bno pasa por donde\b/i',
    '/\bha dejado de ir\b/i',
];

$SEEDS = 15;
$DIAS = 20;

echo "=== AHT-P10.3: MÉTRICAS SEMÁNTICAS — {$SEEDS}x{$DIAS} ===\n\n";

$stats = [
    'total' => 0,
    'continuidad' => ['con' => 0, 'sin' => 0],
    'redundancia' => 0,
    'info_nueva' => 0,
    'privacidad_viola' => 0,
    'inventado' => 0,
    'contradicciones' => 0,
    'progresion_parejas' => ['total' => 0, 'con_progresion' => 0],
    'por_tipo' => [],
];

for ($si = 1; $si <= $SEEDS; $si++) {
    $seed = sprintf('p10-3-%02d', $si);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $service->avanzarRelojPasoAPaso($p, 24);
    aceptarCandidatos($service, $p);

    $historialParejas = [];

    for ($d = 1; $d <= $DIAS; $d++) {
        $service->avanzarRelojPasoAPaso($p, 24);
        $diaCots = cotilleosPorDia($p, $d);

        foreach ($diaCots as $msg) {
            $stats['total']++;
            $tipo = $msg['tipo'] ?? 'desconocido';
            $texto = $msg['texto'] ?? '';
            $actores = $msg['actores'] ?? [];
            $stats['por_tipo'][$tipo] = ($stats['por_tipo'][$tipo] ?? 0) + 1;

            if (count($actores) >= 2) {
                $par = parKey($actores[0], $actores[1]);
                $previos = $historialParejas[$par] ?? [];
                if ($previos !== []) {
                    $stats['continuidad']['con']++;
                } else {
                    $stats['continuidad']['sin']++;
                }
                $historialParejas[$par][] = $d;
            }

            foreach ($PRIVACY_PATTERNS as $pat) {
                if (preg_match($pat, $texto)) {
                    $stats['privacidad_viola']++;
                    break;
                }
            }
            foreach ($INVENTED_BEHAVIOR as $pat) {
                if (preg_match($pat, $texto)) {
                    $stats['inventado']++;
                    break;
                }
            }

            if (count($actores) >= 2) {
                $par = parKey($actores[0], $actores[1]);
                $hits = hitosEntre($p, $actores[0], $actores[1]);
                if (count($hits) >= 2 && in_array($tipo, ['cotilleo', 'senal_romantica', 'progresion_romantica'], true)) {
                    $stats['info_nueva']++;
                }
            }
        }
    }

    foreach ($historialParejas as $par => $dias) {
        if (count($dias) >= 3) {
            $stats['progresion_parejas']['total']++;
            $sorted = array_values(array_unique($dias));
            sort($sorted);
            $rangos = [];
            for ($i = 1; $i < count($sorted); $i++) {
                $rangos[] = $sorted[$i] - $sorted[$i - 1];
            }
            $avgGap = $rangos !== [] ? array_sum($rangos) / count($rangos) : 999;
            if ($avgGap <= 5) {
                $stats['progresion_parejas']['con_progresion']++;
            }
        }
    }
}

echo "=== 1. VOLUMEN ===\n";
echo "Total cotilleos: {$stats['total']}\n";
echo "Promedio/día: " . round($stats['total'] / ($SEEDS * $DIAS), 2) . "\n\n";

echo "=== 2. POR TIPO ===\n";
arsort($stats['por_tipo']);
foreach ($stats['por_tipo'] as $tipo => $count) {
    echo "  {$tipo}: {$count} (" . round($count / max(1, $stats['total']) * 100, 1) . "%)\n";
}

$totalContexto = $stats['continuidad']['con'] + $stats['continuidad']['sin'];
echo "\n=== 3. CONTINUIDAD NARRATIVA ===\n";
echo "Con continuidad: {$stats['continuidad']['con']} (" . round($stats['continuidad']['con'] / max(1, $totalContexto) * 100, 1) . "%)\n";
echo "Sin continuidad: {$stats['continuidad']['sin']} (" . round($stats['continuidad']['sin'] / max(1, $totalContexto) * 100, 1) . "%)\n";

echo "\n=== 4. REDUNDANCIA ===\n";
echo "Publicaciones con info nueva (par con >=2 hitos): {$stats['info_nueva']}\n";

echo "\n=== 5. PRIVACIDAD ===\n";
echo "Violaciones de privacidad (interioridad expuesta): {$stats['privacidad_viola']}\n";

echo "\n=== 6. Afirmaciones inventadas ===\n";
echo "Comportamientos públicos inventados: {$stats['inventado']}\n";

echo "\n=== 7. CONTRADICCIONES ===\n";
echo "Contradicciones factuales: {$stats['contradicciones']}\n";

echo "\n=== 8. PROGRESIÓN DE PAREJAS ===\n";
$tp = $stats['progresion_parejas']['total'];
$cp = $stats['progresion_parejas']['con_progresion'];
echo "Pares con >=3 cotilleos: {$tp}\n";
echo "Con progresión (gap avg <=5d): {$cp} (" . round($cp / max(1, $tp) * 100, 1) . "%)\n";

echo "\n=== DONE ===\n";
