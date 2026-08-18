<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'inv-test');
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$ph = $service->crearResidentePlaceholderDev($partida);
$ida = 'per_qa_valid';
$idb = $ph['residente']['catalog_id'];

$r1 = EncuentroEngine::programar($partida, [$ida, $idb], 1, 19, 'conocerse');
ok($r1['ok'] ?? false, 'encuentro programado');

$r2 = EncuentroEngine::programar($partida, [$ida, $idb], 1, 19, 'amistad');
ok(!($r2['ok'] ?? true), 'doble reserva rechazada');

ok(EncuentroEngine::transicionValida('programado', 'en_curso'), 'transicion valida');
ok(!EncuentroEngine::transicionValida('terminado', 'en_curso'), 'transicion invalida');

RelacionEngine::upsertSocial($partida, $ida, $idb, 'conocidos', 1, true);
RelacionEngine::upsertRomance($partida, $ida, $idb, ['vinculo' => 5]);
$rel = RelacionEngine::obtenerEntre($partida, $ida, $idb);
ok($rel['social'] !== null && $rel['romance'] !== null, 'social y romance independientes');

exit($failures > 0 ? 1 : 0);
