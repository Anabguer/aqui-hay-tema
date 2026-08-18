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
ok(isset($partida['residentes']['per_i03']), 'incluye a Rocío');
ok(isset($partida['residentes']['per_qa_valid']), 'incluye QA Valid (NO CANON)');
ok(count($partida['residentes']) === 2, 'exactamente dos residentes');
ok(in_array('lug_cafeteria', $partida['celeste']['lugares_desbloqueados'] ?? [], true), 'cafetería operativa');
ok(!in_array('lug_parque', $partida['celeste']['lugares_desbloqueados'] ?? [], true), 'parque no operativo día 1');
ok(($partida['residentes']['per_i03']['identidad_publica']['nombre'] ?? '') === 'Rocío', 'nombre Rocío');
ok(($partida['residentes']['per_qa_valid']['identidad_publica']['nombre'] ?? '') === 'QA Valid', 'QA inequívoco');
ok(empty($partida['residentes']['per_qa_valid']['_placeholder']), 'QA es ficha de catálogo, no placeholder sintético');

$ids = array_keys($partida['residentes']);
ok(!in_array('per_i02', $ids, true) && !in_array('per_i10', $ids, true), 'no mete B/C ni I02/I10');

exit($failures > 0 ? 1 : 0);
