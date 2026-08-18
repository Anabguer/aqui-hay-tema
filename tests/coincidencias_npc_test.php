<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CoincidenciasEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\SchemaFields;

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

DomainBootstrap::resetForTests();
DomainBootstrap::boot();

$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'coin-test');
SchemaFields::ensure($partida);
$ph = $service->crearResidentePlaceholderDev($partida);
$phId = $ph['residente']['catalog_id'];
$qa = 'per_qa_valid';

// Programar un encuentro en dia=1 hora=19 para forzar coincidencia en PresenciaEngine.
$enc = $service->programarEncuentro($partida, [$qa, $phId], 1, 19, 'conocerse');
ok($enc['ok'] ?? false, 'encuentro programado dia1 hora19');

// Detectar coincidencias en ese slot.
$nuevas = CoincidenciasEngine::detectarYRegistrar($partida, $root, 1, 19);
ok(count($nuevas) >= 1, 'detecta coincidencia en slot del encuentro');
ok(isset($nuevas[0]['key']), 'coincidencia tiene key');
ok((string) ($nuevas[0]['dia'] ?? '') === '1' && (string) ($nuevas[0]['hora'] ?? '') === '19', 'dia y hora correctos');
ok(in_array($qa, $nuevas[0]['residentes'] ?? [], true) && in_array($phId, $nuevas[0]['residentes'] ?? [], true), 'ambos residentes en coincidencia');

// Idempotencia: segunda detección no duplica.
$nuevas2 = CoincidenciasEngine::detectarYRegistrar($partida, $root, 1, 19);
ok(count($nuevas2) === 0, 'no duplica coincidencia ya registrada');

$totalCoin = count($partida['historial_coincidencias'] ?? []);
ok($totalCoin === 1, 'historial tiene exactamente 1 coincidencia');

// Dominio: debe haber emitido COINCIDENCIA_RESIDENTES.
$eventTypes = array_column($partida['domain_events'] ?? [], 'evento');
ok(in_array(DomainEvents::COINCIDENCIA_RESIDENTES, $eventTypes, true), 'evento COINCIDENCIA_RESIDENTES emitido');

// detectarEnIntervalo: al avanzar 24h debe detectar la coincidencia del día siguiente si hay encuentro.
$relojAntes = $partida['reloj'];
$nuevasIntervalo = CoincidenciasEngine::detectarEnIntervalo($partida, $root, $relojAntes, 2);
// No esperamos coincidencias nuevas (el encuentro ya está terminado o no hay uno nuevo en ese rango).
ok(is_array($nuevasIntervalo), 'detectarEnIntervalo devuelve array');

// Coincidir != interactuar: historial_coincidencias existe pero no implica relación.
$relSocial = $partida['relaciones_sociales'] ?? [];
ok(empty($relSocial), 'coincidir no crea relación social automáticamente');

// horasRelevantes: devuelve lista de pares.
$pares = CoincidenciasEngine::horasRelevantes($partida, ['dia_pueblo' => 1, 'hora_actual' => 0], 3);
ok(is_array($pares), 'horasRelevantes devuelve array');
foreach ($pares as $par) {
    ok(count($par) === 2 && is_int($par[0]) && is_int($par[1]), 'par [dia, hora]');
}

exit($failures > 0 ? 1 : 0);
