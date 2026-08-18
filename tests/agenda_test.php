<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\Reloj;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'agenda-test');
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

$ag = AgendaEngine::resolverDia($partida, 'per_qa_valid', 1);
ok($ag['slots'][8]['ocupado'] === false, 'autonomo libre 8h laborable');

$enc = EncuentroEngine::programar($partida, ['per_qa_valid', $ph['residente']['catalog_id']], 1, 19, 'conocerse');
ok($enc['ok'] ?? false, 'encuentro 19h');

$ag2 = AgendaEngine::resolverDia($partida, 'per_qa_valid', 1);
ok($ag2['slots'][19]['tipo'] === 'encuentro', 'agenda muestra encuentro');

Reloj::avanzarHoras($partida, 24);
ok((int) $partida['reloj']['dia_pueblo'] === 2, 'cambio de día');

$ag3 = AgendaEngine::resolverDia($partida, 'per_qa_valid', 2);
ok(isset($ag3['slots'][5]), 'madrugada slot 5 existe');

exit($failures > 0 ? 1 : 0);
