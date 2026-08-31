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

function cotilleos(array $p): array {
    return array_filter($p['buzon'] ?? [], fn($m) => ($m['canal'] ?? '') === 'cotilleo');
}

function cotilleosPorDia(array $p, int $dia): array {
    return array_filter(cotilleos($p), fn($m) => ($m['dia'] ?? 0) === $dia);
}

$SEEDS = 15;
$DIAS = 20;

echo "=== AHT-P10: BASELINE COTILLEOS — {$SEEDS}x{$DIAS} ===\n\n";

$globalStats = [
    'total' => 0, 'por_dia' => [], 'por_tipo' => [], 'por_categoria' => [],
    'por_pareja' => [], 'por_seed' => [], 'textos' => [],
];

$allCotilleos = [];

for ($i = 1; $i <= $SEEDS; $i++) {
    $seed = sprintf('p10-base-%02d', $i);
    $p = $service->nuevaPartida('juego_v1', $seed);
    $service->avanzarRelojPasoAPaso($p, 24);
    aceptarCandidatos($service, $p);

    $seedTotal = 0;
    $seedPorDia = [];

    for ($d = 1; $d <= $DIAS; $d++) {
        $service->avanzarRelojPasoAPaso($p, 24);

        $diaCots = cotilleosPorDia($p, $d);
        $seedPorDia[$d] = count($diaCots);
        $globalStats['por_dia'][$d] = ($globalStats['por_dia'][$d] ?? 0) + count($diaCots);

        foreach ($diaCots as $msg) {
            $globalStats['total']++;
            $seedTotal++;

            $tipo = $msg['tipo'] ?? 'cotilleo';
            $texto = trim($msg['texto'] ?? '');
            $actores = $msg['actores'] ?? [];
            $dia = $msg['dia'] ?? $d;
            $cats = $msg['cotilleo_meta'] ?? [];
            $cat = $cats['categoria'] ?? 'encuentro';
            $eventoId = $msg['origen']['evento_id'] ?? '';
            $tipoEvento = $msg['origen']['tipo_evento'] ?? '';
            $ts = $msg['ts_juego'] ?? [];

            $globalStats['por_tipo'][$tipo] = ($globalStats['por_tipo'][$tipo] ?? 0) + 1;
            $globalStats['por_categoria'][$cat] = ($globalStats['por_categoria'][$cat] ?? 0) + 1;

            $par = implode('/', $actores);
            sort($actores);
            $parSorted = implode('/', $actores);
            $globalStats['por_pareja'][$parSorted] = ($globalStats['por_pareja'][$parSorted] ?? 0) + 1;

            $textoHash = md5($texto);
            $globalStats['textos'][$textoHash] = ($globalStats['textos'][$textoHash] ?? 0) + 1;

            $allCotilleos[] = [
                'seed' => $seed,
                'dia' => $dia,
                'tipo' => $tipo,
                'cat' => $cat,
                'actores' => $actores,
                'par' => $parSorted,
                'texto' => substr($texto, 0, 200),
                'evento_id' => $eventoId,
                'tipo_evento' => $tipoEvento,
                'ts' => $ts,
            ];
        }
    }

    $globalStats['por_seed'][$seed] = $seedTotal;
    echo "{$seed}: total={$seedTotal} prom/dia=" . round($seedTotal / $DIAS, 1) . "\n";
}

echo "\n=== 1. VOLUMEN GLOBAL ===\n";
echo "Total cotilleos: {$globalStats['total']}\n";
echo "Promedio por seed ({$DIAS}d): " . round($globalStats['total'] / $SEEDS, 1) . "\n";
echo "Promedio por dia: " . round($globalStats['total'] / ($SEEDS * $DIAS), 2) . "\n";

echo "\n=== 2. POR DIA ===\n";
for ($d = 1; $d <= $DIAS; $d++) {
    $n = $globalStats['por_dia'][$d] ?? 0;
    echo "  D{$d}: {$n} (" . round($n / $SEEDS, 1) . "/seed)\n";
}

echo "\n=== 3. POR TIPO ===\n";
arsort($globalStats['por_tipo']);
foreach ($globalStats['por_tipo'] as $t => $n) {
    echo "  {$t}: {$n} (" . round($n / max(1, $globalStats['total']) * 100, 1) . "%)\n";
}

echo "\n=== 4. POR CATEGORIA ===\n";
arsort($globalStats['por_categoria']);
foreach ($globalStats['por_categoria'] as $c => $n) {
    echo "  {$c}: {$n} (" . round($n / max(1, $globalStats['total']) * 100, 1) . "%)\n";
}

echo "\n=== 5. TOP 20 PAREJAS ===\n";
arsort($globalStats['por_pareja']);
$i = 0;
foreach ($globalStats['por_pareja'] as $p => $n) {
    if ($i++ >= 20) break;
    echo "  {$p}: {$n}\n";
}

echo "\n=== 6. DUPLICADOS DE TEXTO ===\n";
$dupes = array_filter($globalStats['textos'], fn($n) => $n > 1);
arsort($dupes);
$dupeCount = array_sum($dupes);
echo "Textos duplicados: " . count($dupes) . " patrones, {$dupeCount} entradas duplicadas\n";
echo "Publicaciones unicas textualmente: " . ($globalStats['total'] - $dupeCount) . "\n";
echo "Ratio unicos/total: " . round(($globalStats['total'] - $dupeCount) / max(1, $globalStats['total']) * 100, 1) . "%\n";

echo "\n=== DONE ===\n";
