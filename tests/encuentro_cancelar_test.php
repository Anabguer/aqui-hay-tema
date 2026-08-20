<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\EncuentroEngine;
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
$partida = $service->nuevaPartida('test_fixtures_v0', 'cancel-enc');
$ph = $service->crearResidentePlaceholderDev($partida);
$ida = 'per_qa_valid';
$idb = $ph['residente']['catalog_id'];

$enc = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse');
ok($enc['ok'] ?? false, 'encuentro programado para cancelar');
$encId = $enc['encuentro']['id'] ?? '';

$dispAntes = AgendaEngine::estaDisponible($partida, $ida, 1, 19);
ok(!($dispAntes['disponible'] ?? true), 'agenda ocupada mientras está programado');

$cancel = $service->cancelarEncuentro($partida, $encId);
ok($cancel['ok'] ?? false, 'cancelar programado ok');
ok(($cancel['encuentro']['estado'] ?? '') === 'cancelado', 'estado cancelado');

$dispDespues = AgendaEngine::estaDisponible($partida, $ida, 1, 19);
ok($dispDespues['disponible'] ?? false, 'agenda liberada tras cancelar');
$dispB = AgendaEngine::estaDisponible($partida, $idb, 1, 19);
ok($dispB['disponible'] ?? false, 'agenda del otro participante también libre');

$tipos = array_map(static fn($e) => $e['evento'] ?? '', $partida['domain_events'] ?? []);
ok(in_array(DomainEvents::ENCUENTRO_CANCELADO, $tipos, true), 'evento ENCUENTRO_CANCELADO');

$service->guardar($partida);
$pid = $partida['meta']['partida_id'];
$recargada = $service->cargar($pid);
$canceladoReload = null;
foreach ($recargada['encuentros'] ?? [] as $e) {
    if (($e['id'] ?? '') === $encId) {
        $canceladoReload = $e['estado'];
    }
}
ok($canceladoReload === 'cancelado', 'persistencia: cancelado tras reload');
$dispReload = AgendaEngine::estaDisponible($recargada, $ida, 1, 19);
ok($dispReload['disponible'] ?? false, 'persistencia: agenda sigue libre tras reload');
$activos = EncuentroEngine::listarActivos($recargada);
foreach ($activos as $a) {
    ok(($a['id'] ?? '') !== $encId, 'cancelado no aparece como activo');
}

$partida = $recargada;
$reprog = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'amistad');
ok($reprog['ok'] ?? false, 'se puede volver a programar la misma hora');

$partida['celeste']['lugares_desbloqueados'][] = 'lug_parque';
$enc2 = $service->programarEncuentro($partida, [$ida, $idb], 1, 21, 'conocerse', 'lug_parque');
ok($enc2['ok'] ?? false, 'segundo encuentro 21h parque');
$service->avanzarReloj($partida, 16);
$estTerm = null;
foreach ($partida['encuentros'] as $e) {
    if ($e['id'] === ($enc2['encuentro']['id'] ?? '')) {
        $estTerm = $e['estado'];
    }
}
ok($estTerm === 'terminado', 'encuentro 21h terminado');
$enc2Id = (string) ($enc2['encuentro']['id'] ?? '');
$noCancelTerm = $service->cancelarEncuentro($partida, $enc2Id !== '' ? $enc2Id : 'enc_faltante');
ok(!($noCancelTerm['ok'] ?? true), 'no cancelar terminado');
ok(($noCancelTerm['error'] ?? '') === 'TRANSICION_INVALIDA', 'error TRANSICION_INVALIDA al cancelar terminado');

$inex = $service->cancelarEncuentro($partida, 'enc_no_existe');
ok(!($inex['ok'] ?? true), 'no cancelar inexistente');
ok(($inex['error'] ?? '') === 'encuentro_no_encontrado', 'error encuentro_no_encontrado');

exit($failures > 0 ? 1 : 0);
