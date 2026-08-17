<?php
declare(strict_types=1);

/**
 * Smoke tests integración
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\BloqueA;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Reloj;

$root = dirname(__DIR__);
$failures = 0;

function assertTrue(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    } else {
        echo "OK: {$msg}\n";
    }
}

$service = new PartidaService($root);
$partida = $service->nuevaPartida('debug_v0', 'smoke-test');

assertTrue((int) ($partida['meta']['schema_version'] ?? 0) === 2, 'schema v2');
assertTrue(isset($partida['rng']['state']), 'rng en partida nueva');
assertTrue(count($partida['bloque_a']['viviendas']) === 16, 'bloque A 16');
assertTrue(isset($partida['residentes']['per_i03']), 'Rocío presente');

$ph = $service->crearResidentePlaceholderDev($partida);
$agenda = AgendaEngine::resolverDia($partida, 'per_i03', 1);
assertTrue(count($agenda['slots']) === 24, 'agenda 24 slots');

$enc = EncuentroEngine::programar($partida, ['per_i03', $ph['residente']['catalog_id']], 1, 19, 'conocerse');
assertTrue($enc['ok'] ?? false, 'encuentro programado 19h');

Reloj::avanzarHoras($partida, 12);
EncuentroLifecycle::sincronizarConReloj($partida, $service->getLogger());
$terminado = false;
foreach ($partida['encuentros'] as $e) {
    if (($e['estado'] ?? '') === 'terminado') {
        $terminado = true;
        assertTrue(isset($e['resultado']['delta_social']), 'resultado social placeholder');
    }
}
assertTrue($terminado, 'encuentro resuelto al avanzar reloj');

$service->guardar($partida);
$id = $partida['meta']['partida_id'];
$cargada = $service->cargar($id);
assertTrue($cargada['meta']['partida_id'] === $id, 'persistencia');

$rngBefore = $partida['rng']['state'];
$cargada2 = $service->cargar($id);
assertTrue($cargada2['rng']['state'] === $rngBefore, 'rng preservado en save');

echo $failures === 0 ? "\nSMOKE OK\n" : "\nSMOKE FAIL {$failures}\n";
exit($failures > 0 ? 1 : 0);
