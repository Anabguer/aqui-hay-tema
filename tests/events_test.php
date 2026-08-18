<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'evt-test');
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

ok(count($partida['audit_trail'] ?? []) > 0, 'partida_creada deja audit');
ok(count($partida['domain_events'] ?? []) > 0, 'partida_creada deja domain_event');

$enc = $service->programarEncuentro($partida, ['per_qa_valid', $ph['residente']['catalog_id']], 1, 19, 'conocerse');
ok($enc['ok'] ?? false, 'encuentro programado');

$tipos = array_column($partida['domain_events'] ?? [], 'evento');
ok(in_array(DomainEvents::ENCUENTRO_PROGRAMADO, $tipos, true), 'evento encuentro_programado');

$service->avanzarReloj($partida, 12);
$tipos2 = array_column($partida['domain_events'] ?? [], 'evento');
ok(in_array(DomainEvents::TIEMPO_AVANZADO, $tipos2, true), 'evento tiempo_avanzado');

exit($failures > 0 ? 1 : 0);
