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

$a = SimulationRunner::runFlujoLargoPlay($root, 30, 'flujo-play-30');
ok($a['ok'] ?? false, 'flujo largo 30d ok');
ok(empty($a['invariantes_rotas'] ?? []), 'sin invariantes rotas: ' . implode(',', $a['invariantes_rotas'] ?? []));
ok(($a['encuentros_programados'] ?? 0) >= 2, 'programó al menos 2 encuentros');
ok(($a['encuentros_cancelados'] ?? 0) >= 1, 'canceló al menos 1');
ok($a['agenda_liberada_tras_cancel'] ?? false, 'cancelación libera agenda');
ok($a['relacion_placeholder_ok'] ?? false, 'relación placeholder coherente tras resolver');
ok(($a['save_bytes'] ?? 0) > 0, 'save escrito');
ok(($a['audit_trail_size'] ?? 0) <= 200, 'audit respeta cap');
ok(($a['eventos_dominio'] ?? 0) <= 200, 'domain_events respeta cap');

$b = SimulationRunner::runFlujoLargoPlay($root, 30, 'flujo-play-30');
ok(($b['ok'] ?? false), 'segunda corrida misma seed ok');
ok(($a['rng_state'] ?? 0) === ($b['rng_state'] ?? -1), 'RNG reproducible (mismo state)');
ok(($a['reloj']['dia_pueblo'] ?? 0) === ($b['reloj']['dia_pueblo'] ?? -1), 'mismo reloj final');
ok(($a['encuentros_por_estado'] ?? []) === ($b['encuentros_por_estado'] ?? ['x']), 'mismos conteos de estado');

if (!empty($a['invariantes_rotas'])) {
    echo "  invariantes: " . implode(', ', $a['invariantes_rotas']) . "\n";
}
echo "  30d: {$a['ms_total']}ms programados={$a['encuentros_programados']} resueltos={$a['encuentros_resueltos']} cancelados={$a['encuentros_cancelados']}\n";

exit($failures > 0 ? 1 : 0);
