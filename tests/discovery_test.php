<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'disc-test');
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

ok(DiscoveryEngine::estado($partida, 'per_qa_valid', 'vida.hobby_principal') === DiscoveryEngine::DESCONOCIDO, 'campo desconocido por defecto');

$entry = DiscoveryEngine::registrar($partida, 'per_qa_valid', 'vida.hobby_principal', 'pasear', 'test_fixture', 'corr_test');
ok($entry['estado'] === DiscoveryEngine::DESCUBIERTO, 'registro descubierto');
ok(DiscoveryEngine::estado($partida, 'per_qa_valid', 'vida.hobby_principal') === DiscoveryEngine::DESCUBIERTO, 'estado tras registrar');
ok(count(DiscoveryEngine::listarPorResidente($partida, 'per_qa_valid')) === 1, 'listar por residente');

exit($failures > 0 ? 1 : 0);
