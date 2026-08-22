<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CoincidenciasInteraccionBridge;
use AquiHayTema\Engine\CotilleoNarrativo;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\InteraccionCasual;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

DomainBootstrap::boot();
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'coin-interact-' . time());
$a = array_key_first($p['residentes'] ?? []);
$b = null;
foreach (array_keys($p['residentes'] ?? []) as $rid) {
    if ($rid !== $a) {
        $b = $rid;
        break;
    }
}
ok(is_string($a) && is_string($b), 'par de residentes');
if (!is_string($a) || !is_string($b)) {
    exit(1);
}

$dia = (int) ($p['reloj']['dia_pueblo'] ?? 1);
$lugar = 'lug_cafeteria';
$p['historial_coincidencias'] = [];
for ($d = $dia - 4; $d <= $dia; $d++) {
    $p['historial_coincidencias'][] = [
        'key' => 'coin_' . $d,
        'dia' => $d,
        'hora' => 10,
        'lugar_id' => $lugar,
        'residentes' => [$a, $b],
    ];
}
$dias = CotilleoNarrativo::diasPatronParLugar($p, [$a, $b], $lugar, $dia, []);
ok($dias >= 2, 'patron de coincidencias acumulado');
ok(!RelacionEngine::seConocen($p, $a, $b), 'aun no se conocen');

$entry = [
    'key' => 'coin_test',
    'dia' => $dia,
    'hora' => 11,
    'lugar_id' => $lugar,
    'residentes' => [$a, $b],
];
$p['rng']['seed'] = 'coin-interact-seed-fija';
$p['rng']['state'] = 0;
$antes = RelacionEngine::seConocen($p, $a, $b);
CoincidenciasInteraccionBridge::intentarTrasCoincidencia($p, $entry, $root);
$despues = RelacionEngine::seConocen($p, $a, $b);
ok($antes === $despues || $despues, 'interaccion no obligatoria; si ocurre, puede conocerse');

exit($failures > 0 ? 1 : 0);
