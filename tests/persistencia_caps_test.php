<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AuditTrail;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PersistenciaCaps;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'caps-test');
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

PersistenciaCaps::mergeIntoPartida($partida, $root);
$partida['persistencia']['audit_trail_cap'] = 5;
$partida['audit_trail'] = [];

for ($i = 0; $i < 12; $i++) {
    AuditTrail::record($partida, 'test_evt_' . $i, ['qa'], 'test', 'cap');
}

ok(count($partida['audit_trail']) === 5, 'audit cap 5');
ok(!empty($partida['audit_trail_archivo']), 'archivo de audit al recortar');
$caidos = array_sum(array_column($partida['audit_trail_archivo'], 'count'));
ok($caidos === 7, 'resumen cuenta entradas caídas');

$ph = $service->crearResidentePlaceholderDev($partida);
RelacionEngine::upsertSocial($partida, 'per_qa_valid', $ph['residente']['catalog_id'], 'conocidos', 1, true);
ok(count($partida['historial_relaciones'] ?? []) >= 1, 'historial relación no se borra');

$service->guardar($partida);
$c = $service->cargar($partida['meta']['partida_id']);
ok(isset($c['persistencia']['audit_trail_cap']), 'caps persisten en save');

exit($failures > 0 ? 1 : 0);
