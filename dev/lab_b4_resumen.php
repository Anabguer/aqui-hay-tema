<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorPeticionesPueblo;

$root = dirname(__DIR__);
$seeds = isset($argv[1]) ? max(1, (int) $argv[1]) : 2;
$tamanos = [8];
if (isset($argv[2]) && trim((string) $argv[2]) !== '') {
    $tamanos = array_values(array_filter(array_map('intval', explode(',', (string) $argv[2]))));
}
$esquemas = ['E1', 'E2', 'E3', 'E4', 'E5'];
if (isset($argv[3]) && trim((string) $argv[3]) !== '') {
    $esquemas = array_values(array_filter(array_map('trim', explode(',', (string) $argv[3]))));
}

$horizontes = [30, 100, 365];
if (isset($argv[4]) && trim((string) $argv[4]) !== '') {
    $horizontes = array_values(array_filter(array_map('intval', explode(',', (string) $argv[4]))));
}

$lab = SimuladorPeticionesPueblo::ejecutarComparacion(
    $root,
    $esquemas,
    $tamanos,
    $horizontes,
    $seeds,
    'lab-peticiones-b4'
);

echo json_encode($lab['recomendacion'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
foreach ($lab['esquemas'] ?? [] as $id => $b) {
    echo "\n==== {$id} " . ($b['label'] ?? '') . ' score=' . ($b['score']['puntos'] ?? '?')
        . ' farm=' . (!empty($b['farming_detectado']) ? 'SI' : 'no')
        . ' imp=' . ($b['imposibles_total'] ?? 0) . "\n";
    foreach ($b['por_tamano'] ?? [] as $n => $tb) {
        echo "-- n={$n}\n";
        foreach (['A', 'B', 'C', 'D'] as $p) {
            echo $p;
            foreach ($horizontes as $h) {
                $x = $tb['por_perfil'][$p]['por_horizonte'][(string) $h] ?? [];
                echo " |{$h}d lat=" . ($x['latidos'] ?? '-')
                    . ' 1L=' . ($x['primer_latido_media'] ?? '-')
                    . ' vida=' . ($x['vida_media'] ?? '-')
                    . ' GO=' . ($x['pct_game_over'] ?? '-')
                    . ' pet/d=' . ($x['peticiones_por_dia'] ?? '-')
                    . ' ok%=' . ($x['pct_pet_cumplidas'] ?? '-')
                    . ' cad%=' . ($x['pct_pet_caducadas'] ?? '-')
                    . ' valP=' . ($x['validos_pet'] ?? '-')
                    . ' val/d=' . ($x['validos_por_dia'] ?? '-');
            }
            echo "\n";
        }
    }
}
