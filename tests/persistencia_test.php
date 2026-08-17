<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BloqueA;
use AquiHayTema\Engine\PartidaRepository;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;

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
$repo = new PartidaRepository($root);

$p1 = $service->nuevaPartida('debug_v0', 'persist-a');
$id = $p1['meta']['partida_id'];
$rng1 = $p1['rng']['state'];
ok($repo->existe($id), 'partida creada en disco');

$p2 = $service->cargar($id);
ok($p2['rng']['state'] === $rng1, 'rng preservado tras cargar');

$service->guardar($p2);
$p3 = $service->cargar($id);
ok($p3['meta']['partida_id'] === $id, 'guardar/cargar id');

try {
    $service->cargar('part_no_existe_xyz');
    ok(false, 'debe fallar partida inexistente');
} catch (Throwable) {
    ok(true, 'partida inexistente lanza error');
}

// Save corrupto sin .bak debe fallar
$p4 = $service->nuevaPartida('debug_v0', 'persist-bak');
$id4 = $p4['meta']['partida_id'];
$service->guardar($p4);
$path4 = $repo->pathFor($id4);
@unlink($path4 . '.bak');
file_put_contents($path4, '{json invalido');
try {
    $service->cargar($id4);
    ok(false, 'json inválido sin bak debe fallar');
} catch (Throwable $e) {
    ok(str_contains($e->getMessage(), 'corrupto') || str_contains($e->getMessage(), 'save'), 'save corrupto detectado');
}

// Con .bak válido debe recuperar
$service->guardar($p4);
$service->guardar($p4);
file_put_contents($path4, '{json invalido');
$recovered = $service->cargar($id4);
ok($recovered['meta']['partida_id'] === $id4, 'recuperación desde .bak');

exit($failures > 0 ? 1 : 0);
