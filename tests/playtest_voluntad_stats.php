<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);

$pares = [
    ['per_p001', 'per_p002', 'Carmen-José'],
    ['per_p001', 'per_p003', 'Carmen-Marta'],
    ['per_p002', 'per_p003', 'José-Marta'],
];
$n = 40;
echo "=== AUDITORÍA VOLUNTAD (sin recalibrar) ===\n";
echo "n_por_par={$n} tipo=conocerse desconocidos hora=18 lugar=cafeteria\n";
echo "variables: base, emoción aceptar_planes, -12 si no se conocen, social*0.28, romance*0.18, conflicto, rechazos, consejo, afinidad lugar, p_min/p_max, nunca 100%\n\n";

$globalA = 0;
$globalR = 0;
$porQuien = [];

foreach ($pares as $par) {
    $a = $par[0];
    $b = $par[1];
    $label = $par[2];
    $acepta = 0;
    $rechaza = 0;
    $scores = [];
    $ps = [];
    $rechazoPor = [];
    for ($i = 0; $i < $n; $i++) {
        $p = $service->nuevaPartida('playtest_01', 'vol-stat-' . $par[0] . '-' . $i);
        $r = $service->proponerEncuentro($p, [$a, $b], 1, 18, 'conocerse', 'lug_cafeteria');
        if (!empty($r['rechazada'])) {
            $rechaza++;
            $rid = (string) (($r['rechazado_por']['residente_id'] ?? '') ?: 'desconocido');
            $rechazoPor[$rid] = ($rechazoPor[$rid] ?? 0) + 1;
            $porQuien[$rid] = ($porQuien[$rid] ?? 0) + 1;
        } else {
            $acepta++;
        }
        $prop = $r['propuesta'] ?? [];
        foreach ($prop['reacciones'] ?? [] as $reac) {
            if (isset($reac['score'])) {
                $scores[] = (int) $reac['score'];
            }
            if (isset($reac['p'])) {
                $ps[] = (float) $reac['p'];
            }
        }
    }
    $globalA += $acepta;
    $globalR += $rechaza;
    $pct = $n > 0 ? round(100 * $acepta / $n, 1) : 0;
    $avgS = $scores !== [] ? round(array_sum($scores) / count($scores), 1) : null;
    $avgP = $ps !== [] ? round(array_sum($ps) / count($ps), 3) : null;
    echo "{$label}: propuestas={$n} aceptadas={$acepta} rechazadas={$rechaza} pct_acepta={$pct}% score_medio={$avgS} p_medio={$avgP}\n";
    echo "  rechazos_por " . json_encode($rechazoPor) . "\n";
}

$pCon = $service->nuevaPartida('playtest_01', 'vol-stat-conocidos');
RelacionEngine::registrarContacto($pCon, 'per_p001', 'per_p002', 'normal', $cal);
$ev = new VoluntadPonderadaEvaluator($cal);
$scoreDesc = VoluntadPonderadaEvaluator::score(
    $service->nuevaPartida('playtest_01', 'vol-stat-desc-score'),
    ['participantes' => ['per_p001', 'per_p002'], 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria'],
    'per_p001',
    'per_p002',
    $cal
);
$scoreCon = VoluntadPonderadaEvaluator::score(
    $pCon,
    ['participantes' => ['per_p001', 'per_p002'], 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria'],
    'per_p001',
    'per_p002',
    $cal
);
echo "\npenalización desconocidos: score Carmen→José desconocidos={$scoreDesc} conocidos={$scoreCon} (esperado ~ -12 si no hay más diffs)\n";
$tot = $globalA + $globalR;
$pctG = $tot > 0 ? round(100 * $globalA / $tot, 1) : 0;
echo "TOTAL propuestas={$tot} aceptadas={$globalA} rechazadas={$globalR} pct_acepta={$pctG}%\n";
echo "rechazos_por_id " . json_encode($porQuien) . "\n";

$pMin = CalibracionConfig::get($cal, 'voluntad.p_min', null);
$pMax = CalibracionConfig::get($cal, 'voluntad.p_max', null);
$base = CalibracionConfig::get($cal, 'voluntad.base', null);
echo "calibración leída (NO cambiada): base={$base} p_min={$pMin} p_max={$pMax}\n";
exit(0);
