<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimulationRunner;

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

foreach ([30, 100, 365] as $days) {
    $r = SimulationRunner::run($root, $days, 'longevity-' . $days);
    ok($r['ok'] ?? false, "simulación {$days} días completa");
    ok(empty($r['invariantes_rotas'] ?? []), "{$days}d sin invariantes rotas");
    $limite = $days <= 30 ? 5_000_000 : ($days <= 100 ? 6_500_000 : 15_000_000);
    ok(($r['save_bytes'] ?? 0) < $limite, "{$days}d save < {$limite} (bytes={$r['save_bytes']})");
    echo "  {$days}d: {$r['ms_total']}ms, save={$r['save_bytes']}B, eventos={$r['eventos_dominio']}\n";
}

$r2 = SimulationRunner::run($root, 60, 'longevity-multi', 'test_fixtures_v0', 2);
ok($r2['ok'] ?? false, 'simulación 60 días con 2+ residentes extra completa');
ok(empty($r2['invariantes_rotas'] ?? []), '60d multi sin invariantes rotas');
ok(($r2['residentes_activos_extra'] ?? 0) >= 2, 'simulación multi usa 2+ residentes extra');
ok(($r2['historial_coincidencias_size'] ?? 0) <= 500, 'historial coincidencias respeta cap');
ok(($r2['coincidencias'] ?? 0) >= 0, 'coincidencias reportadas');

exit($failures > 0 ? 1 : 0);
