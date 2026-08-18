<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelojOperations;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'play-flujo');
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

$slots = DisponibilidadEngine::slotsCompatibles($partida, [$ida, $idb], 'conocerse');
ok($slots['ok'] ?? false, 'slots_compatibles devuelve ok');
ok(count($slots['slots'] ?? []) > 0, 'hay al menos un slot compatible');

$primer = $slots['slots'][0];
$enc = $service->programarEncuentro(
    $partida,
    [$ida, $idb],
    (int) $primer['dia'],
    (int) $primer['hora'],
    'conocerse',
    'lug_cafeteria'
);
ok($enc['ok'] ?? false, 'programar desde slot compatible');
ok(($enc['encuentro']['estado'] ?? '') === 'programado', 'encuentro queda programado');

$bloqueo = DisponibilidadEngine::slotsCompatibles(
    $partida,
    [$ida, $idb],
    'conocerse',
    (int) $primer['dia'],
    (int) $primer['hora'],
    1,
    24
);
$slotOcupado = array_filter($bloqueo['slots'] ?? [], static fn($s) => (int) $s['dia'] === (int) $primer['dia'] && (int) $s['hora'] === (int) $primer['hora']);
ok(count($slotOcupado) === 0, 'hora del encuentro programado excluida de slots');

$rechazo = $service->programarEncuentro($partida, [$ida, $idb], (int) $primer['dia'], (int) $primer['hora'], 'amistad');
ok(!($rechazo['ok'] ?? true), 'doble reserva rechazada en misma hora');

$reloj = new RelojOperations($root);
$horasHasta = max(1, ((int) $primer['dia'] - (int) $partida['reloj']['dia_pueblo']) * 24
    + ((int) $primer['hora'] - (int) $partida['reloj']['hora_actual']) + 2);
$adv = $reloj->avanzar($partida, $horasHasta);
ok(($adv['encuentros_resueltos'] ?? 0) >= 1, 'avanzar reloj resuelve encuentro');

$sync = EncuentroLifecycle::sincronizarConReloj($partida);
$terminado = false;
foreach ($partida['encuentros'] as $e) {
    if (($e['id'] ?? '') === ($enc['encuentro']['id'] ?? '')) {
        $terminado = ($e['estado'] ?? '') === 'terminado';
    }
}
ok($terminado, 'encuentro pasa a terminado tras el tiempo');

$ficha = $service->fichaResidente($partida, $ida);
ok(isset($ficha['discovery']['campos']), 'ficha expone discovery para play');
ok($ficha['ultimo_encuentro'] !== null || $terminado, 'ficha refleja último encuentro o estado terminado');

exit($failures > 0 ? 1 : 0);
