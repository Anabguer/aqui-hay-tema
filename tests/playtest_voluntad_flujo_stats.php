<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$n = 40;

function tasa(PartidaService $service, int $n, string $escenario): array
{
    $ok = 0;
    $ko = 0;
    for ($i = 0; $i < $n; $i++) {
        $p = $service->nuevaPartida('playtest_01', 'vol-flujo-' . $escenario . '-' . $i);
        $a = 'per_p001';
        $b = 'per_p002';
        $tipo = 'conocerse';
        if ($escenario === 'quedar') {
            RelacionEngine::registrarContacto($p, $a, $b, 'normal');
            RelacionEngine::registrarContacto($p, $b, $a, 'normal');
            $tipo = 'quedar';
        } elseif ($escenario === 'primera_cita') {
            RelacionEngine::registrarContacto($p, $a, $b, 'normal');
            RelacionEngine::registrarContacto($p, $b, $a, 'normal');
            RelacionEngine::setRomanceHacia($p, $a, $b, 22);
            $tipo = 'primera_cita';
        }
        $r = $service->proponerEncuentro($p, [$a, $b], 1, 18, $tipo, 'lug_cafeteria');
        if (!empty($r['rechazada']) || ($r['ok'] ?? true) === false || (($r['propuesta']['estado'] ?? '') === 'rechazada')) {
            $ko++;
        } else {
            $ok++;
        }
    }
    $tot = $ok + $ko;
    return [
        'escenario' => $escenario,
        'n' => $tot,
        'aceptadas' => $ok,
        'rechazadas' => $ko,
        'pct' => $tot > 0 ? round(100 * $ok / $tot, 1) : 0.0,
    ];
}

echo "=== TASAS VOLUNTAD FLUJO RELACIONAL V1 (sin recalibrar) ===\n";
foreach (['conocerse', 'quedar', 'primera_cita'] as $esc) {
    $row = tasa($service, $n, $esc);
    echo $row['escenario'] . ': n=' . $row['n'] . ' aceptadas=' . $row['aceptadas']
        . ' rechazadas=' . $row['rechazadas'] . ' pct_conjunto=' . $row['pct'] . "%\n";
}
echo "calibración intacta base=" . json_encode(CalibracionConfig::get($cal, 'voluntad.base', null))
    . " p_min=" . json_encode(CalibracionConfig::get($cal, 'voluntad.p_min', null))
    . " p_max=" . json_encode(CalibracionConfig::get($cal, 'voluntad.p_max', null)) . "\n";
exit(0);
