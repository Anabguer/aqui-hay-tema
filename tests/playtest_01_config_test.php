<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;

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

$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'playtest-01');

ok(($partida['meta']['config_id'] ?? '') === 'playtest_01', 'config_id playtest_01');
ok(($partida['meta']['seed'] ?? '') === 'playtest-01', 'seed playtest-01');
ok(($partida['meta']['schema_version'] ?? 0) === 2, 'schema v2');
ok(isset($partida['residentes']['per_p001']), 'incluye a Carmen');
ok(isset($partida['residentes']['per_p002']), 'incluye a José');
ok(count($partida['residentes']) === 8, 'exactamente ocho residentes');
ok(in_array('lug_cafeteria', $partida['celeste']['lugares_desbloqueados'] ?? [], true), 'cafetería operativa');
ok(in_array('lug_parque', $partida['celeste']['lugares_desbloqueados'] ?? [], true), 'parque operativo');
ok(in_array('lug_biblioteca', $partida['celeste']['lugares_desbloqueados'] ?? [], true), 'biblioteca operativa');
ok(($partida['residentes']['per_p001']['identidad_publica']['nombre'] ?? '') === 'Carmen', 'nombre Carmen');
ok(!isset($partida['residentes']['per_qa_valid']), 'sin QA Valid');
ok(empty($partida['residentes']['per_p001']['_placeholder']), 'Carmen es ficha de catálogo');

$ids = array_keys($partida['residentes']);
ok(!in_array('per_i02', $ids, true) && !in_array('per_i10', $ids, true), 'no mete B/C ni I02/I10');

exit($failures > 0 ? 1 : 0);
