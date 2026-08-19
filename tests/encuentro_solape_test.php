<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\LugarAtributos;
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
$partida = $service->nuevaPartida('test_fixtures_v0', 'solape-enc');
$ph = $service->crearResidentePlaceholderDev($partida);
$ida = 'per_qa_valid';
$idb = $ph['residente']['catalog_id'];

$attrCafe = LugarAtributos::de('lug_cafeteria');
ok((int) $attrCafe['horas'] >= 2, 'cafetería ocupa al menos 2 h');

$r1 = EncuentroEngine::programar($partida, [$ida], 1, 19, 'individual', 'lug_cafeteria');
ok($r1['ok'] ?? false, 'individual 19h en cafetería');
ok((int) ($r1['encuentro']['duracion_horas'] ?? 0) >= 2, 'duración aplicada al programar');

$r2 = EncuentroEngine::programar($partida, [$ida], 1, 20, 'individual', 'lug_cafeteria');
ok(!($r2['ok'] ?? true), 'mismo residente a las 20h rechazado (solape)');

$rMix = EncuentroEngine::programar($partida, [$ida, $idb], 1, 20, 'conocerse', 'lug_cafeteria');
ok(!($rMix['ok'] ?? true), 'conocerse que solapa con individual rechazado');

$r3 = EncuentroEngine::programar($partida, [$ida], 1, 21, 'individual', 'lug_cafeteria');
ok($r3['ok'] ?? false, '21h libre tras cafetería 19–21');

$p2 = $service->nuevaPartida('test_fixtures_v0', 'solape-inverso');
$ph2 = $service->crearResidentePlaceholderDev($p2);
$a = 'per_qa_valid';
$b = $ph2['residente']['catalog_id'];
$corto = EncuentroEngine::programar($p2, [$a, $b], 1, 21, 'conocerse', 'lug_cafeteria');
ok($corto['ok'] ?? false, 'cafetería 21h');
$largo = EncuentroEngine::programar($p2, [$a], 1, 20, 'individual', 'lug_cafeteria');
ok(!($largo['ok'] ?? true), 'individual 20h 2h que pisa las 21h rechazado');

exit($failures > 0 ? 1 : 0);
