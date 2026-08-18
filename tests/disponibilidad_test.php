<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'disp-test');
$ph = $service->crearResidentePlaceholderDev($partida);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$r = DisponibilidadEngine::slotsCompatibles($partida, ['per_qa_valid', $ph['residente']['catalog_id']], 'conocerse');
ok($r['ok'] ?? false, 'slots compatibles ok');
ok(count($r['slots'] ?? []) > 0, 'devuelve al menos un slot');

$enc = $service->programarEncuentro($partida, ['per_qa_valid', $ph['residente']['catalog_id']], 1, 19, 'conocerse');
ok($enc['ok'] ?? false, 'programar 19h');

$r2 = DisponibilidadEngine::slotsCompatibles($partida, ['per_qa_valid', $ph['residente']['catalog_id']], 'conocerse', 1, 19, 1, 24);
$slot19 = array_filter($r2['slots'] ?? [], static fn($s) => (int) $s['dia'] === 1 && (int) $s['hora'] === 19);
ok(count($slot19) === 0, '19h ya reservada excluida de slots');

exit($failures > 0 ? 1 : 0);
