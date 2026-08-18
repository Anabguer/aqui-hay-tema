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
    ok(($r['save_bytes'] ?? 0) < 5_000_000, "{$days}d save < 5MB (bytes={$r['save_bytes']})");
    echo "  {$days}d: {$r['ms_total']}ms, save={$r['save_bytes']}B, eventos={$r['eventos_dominio']}\n";
}

exit($failures > 0 ? 1 : 0);
