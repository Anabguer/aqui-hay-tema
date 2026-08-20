<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\Voluntad\VoluntadPlanLab;

$root = dirname(__DIR__);
$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$cal = CalibracionConfig::load($root);
ok((string) CalibracionConfig::get($cal, 'voluntad.resolucion_plan', '') === 'media_geometrica', 'cal resolucion_plan = media_geometrica');

// p_plan teórico 70/70
$p70 = VoluntadPlanLab::pPlan('media_geometrica', 0.682, 0.682);
ok(abs($p70 - 0.682) < 0.01, 'geom 70/70 ≈ 0.68 (got ' . round($p70, 3) . ')');
$pAsim = VoluntadPlanLab::pPlan('media_geometrica', 0.897, 0.252);
ok($pAsim < 0.55 && $pAsim > 0.35, 'geom 95/20 penaliza asimetría (got ' . round($pAsim, 3) . ')');
$pProd = VoluntadPlanLab::pPlan('producto', 0.682, 0.682);
ok($pProd < 0.50, 'producto 70/70 sigue ~0.46 (referencia)');

// Monte Carlo real sobre motor (playtest_01, score típico ~70)
$svc = new PartidaService($root);
$acept = 0;
$total = 0;
$conResolucion = 0;
$sumPPlan = 0.0;
for ($s = 0; $s < 8; $s++) {
    $p = $svc->nuevaPartida('playtest_01', 'geom-reg-' . $s);
    $ids = array_keys($p['residentes']);
    for ($n = 0; $n < 30; $n++) {
        $a = $ids[$n % count($ids)];
        $b = $ids[($n + 3) % count($ids)];
        if ($a === $b) {
            continue;
        }
        $r = PropuestaEncuentroEngine::proponer($p, [$a, $b], 1, 11 + ($n % 6), 'conocerse', 'lug_cafeteria');
        $prop = $r['propuesta'] ?? null;
        if (!is_array($prop)) {
            continue;
        }
        // Solo contar si hubo resolución geom (ambos pasaron agenda)
        $res = $prop['resolucion_plan'] ?? null;
        if (!is_array($res) || ($res['modo'] ?? '') !== 'media_geometrica') {
            continue;
        }
        $conResolucion++;
        $sumPPlan += (float) ($res['p_plan'] ?? 0);
        $total++;
        if (empty($r['rechazada'])) {
            $acept++;
        }
        if ($n % 4 === 3) {
            $svc->avanzarReloj($p, 1);
        }
    }
}
ok($conResolucion >= 40, "muestra geom suficiente (n=$conResolucion)");
$tasa = $total > 0 ? $acept / $total : 0;
$pMed = $conResolucion > 0 ? $sumPPlan / $conResolucion : 0;
ok($tasa > 0.55 && $tasa < 0.85, 'tasa plan geom ~0.55–0.85 (obs=' . round($tasa, 3) . ', p_plan_med=' . round($pMed, 3) . ')');
ok(abs($tasa - $pMed) < 0.20, 'tasa observada cerca de p_plan medio (no ≈p²)');

echo $fail === 0 ? "OK voluntad_media_geometrica\n" : "FAIL voluntad_media_geometrica ($fail)\n";
exit($fail === 0 ? 0 : 1);
