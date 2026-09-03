<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\SenalRomantica;

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

/**
 * @return array{0: PartidaService, 1: array, 2: string, 3: string, 4: Catalog}
 */
function setupEncuentroEnCurso(): array
{
    global $root;
    DomainBootstrap::boot();
    $svc = new PartidaService($root);
    $partida = $svc->nuevaPartida('test_fixtures_v0', 'enc-int-' . time());
    $ph = $svc->crearResidentePlaceholderDev($partida);
    $a = 'per_qa_valid';
    $b = (string) $ph['residente']['catalog_id'];
    $enc = $svc->programarEncuentro($partida, [$a, $b], 1, 19, 'conocerse', 'lug_cafeteria');
    if (!($enc['ok'] ?? false)) {
        throw new RuntimeException('no programa encuentro');
    }
    while ((int) $partida['reloj']['hora_actual'] < 19) {
        $svc->avanzarReloj($partida, 1);
    }
    EncuentroLifecycle::sincronizarConReloj($partida, null, $svc->getCatalog());
    $catalog = $svc->getCatalog();
    $encRow = EncuentroIntervencion::buscar($partida, (string) ($enc['encuentro']['id'] ?? ''));
    if ($encRow === null || ($encRow['estado'] ?? '') !== 'en_curso') {
        throw new RuntimeException('encuentro no en curso');
    }
    return [$svc, $partida, $a, $b, $catalog];
}

[$svc, $partida, $a, $b, $catalog] = setupEncuentroEnCurso();
$encId = (string) ($partida['encuentros'][0]['id'] ?? '');
$cal = CalibracionConfig::load($root);

$acciones = EncuentroIntervencion::accionesDisponibles($partida, $partida['encuentros'][0], $catalog);
$ids = array_column($acciones, 'id');
ok(in_array('hablar', $ids, true), 'hablar listada');
$hablar = null;
foreach ($acciones as $row) {
    if (($row['id'] ?? '') === 'hablar') {
        $hablar = $row;
    }
}
ok(($hablar['disponible'] ?? false) === true, 'hablar disponible en encuentro organizado');

$broma = null;
foreach ($acciones as $row) {
    if (($row['id'] ?? '') === 'broma') {
        $broma = $row;
    }
}
if (!RelacionEngine::seConocen($partida, $a, $b)) {
    ok(($broma['disponible'] ?? false) === false, 'broma bloqueada sin conocerse');
} else {
    ok(($broma['disponible'] ?? false) === true, 'broma disponible si ya se conocen');
}

RelacionEngine::registrarContacto($partida, $a, $b, 'normal', $cal, 1, 3);
RelacionEngine::registrarContacto($partida, $b, $a, 'normal', $cal, 1, 3);
$acciones2 = EncuentroIntervencion::accionesDisponibles($partida, $partida['encuentros'][0], $catalog);
$broma2 = null;
foreach ($acciones2 as $row) {
    if (($row['id'] ?? '') === 'broma') {
        $broma2 = $row;
    }
}
ok(($broma2['disponible'] ?? false) === true, 'broma disponible tras conocerse');

$hobbyRow = null;
foreach ($acciones2 as $row) {
    if (($row['id'] ?? '') === 'hobby') {
        $hobbyRow = $row;
    }
}
$perfilPre = \AquiHayTema\Engine\PerfilPartida::deOLegacy($partida, $a, $catalog);
$tieneHobbyConocidoPre = false;
foreach ($perfilPre['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '' && DiscoveryReveal::jugadorSabeHobby($partida, $a, $hh)) {
        $tieneHobbyConocidoPre = true;
        break;
    }
}
if (!$tieneHobbyConocidoPre) {
    ok(($hobbyRow['disponible'] ?? false) === false, 'hobby bloqueado sin descubrimiento');
}

$perfil = \AquiHayTema\Engine\PerfilPartida::deOLegacy($partida, $a, $catalog);
$hid = (string) (($perfil['hobbies'][0] ?? '') ?: '');
if ($hid !== '') {
    DiscoveryReveal::registrarJugador($partida, $a, ConocimientoNpc::campoHobby($hid), $hid, 'test');
    $acciones3 = EncuentroIntervencion::accionesDisponibles($partida, $partida['encuentros'][0], $catalog);
    $hobby3 = null;
    foreach ($acciones3 as $row) {
        if (($row['id'] ?? '') === 'hobby') {
            $hobby3 = $row;
        }
    }
    ok(($hobby3['disponible'] ?? false) === true, 'hobby disponible con descubrimiento');
    /* FASE 1: hobbies ahora vienen en temas_por_objetivo. */
    $temasAll = [];
    foreach (($hobby3['temas_por_objetivo'] ?? []) as $lista) {
        foreach ($lista as $t) { $temasAll[] = $t; }
    }
    ok(count($temasAll) >= 1, 'hobby expone opciones conocidas');
}

$coq = null;
foreach ($acciones2 as $row) {
    if (($row['id'] ?? '') === 'coquetear') {
        $coq = $row;
    }
}
/* FASE 1: coquetear no está en ACCIONES_SOCIALES, no aparece en la lista. */
ok($coq === null, 'coquetear no aparece en FASE 1 (sin romance)');

RelacionEngine::setRomanceHacia($partida, $a, $b, SenalRomantica::umbralTilin($cal) + 2);
$accionesRom = EncuentroIntervencion::accionesDisponibles($partida, $partida['encuentros'][0], $catalog);
$coq2 = null;
$beso = null;
foreach ($accionesRom as $row) {
    if (($row['id'] ?? '') === 'coquetear') {
        $coq2 = $row;
    }
    if (($row['id'] ?? '') === 'beso') {
        $beso = $row;
    }
}
/* FASE 1: coquetear y beso no están en ACCIONES_SOCIALES. */
ok($coq2 === null, 'coquetear ausente con señal romántica (FASE 1)');
ok($beso === null, 'beso ausente sin contexto cita/pareja (FASE 1)');

$partida['encuentros'][0]['tipo'] = 'primera_cita';
$accionesCita = EncuentroIntervencion::accionesDisponibles($partida, $partida['encuentros'][0], $catalog);
$besoCita = null;
foreach ($accionesCita as $row) {
    if (($row['id'] ?? '') === 'beso') {
        $besoCita = $row;
    }
}
/* FASE 1: beso no está en ACCIONES_SOCIALES. */
ok($besoCita === null, 'beso ausente en primera cita (FASE 1)');

$antesCoti = count(array_filter(BuzonEngine::listar($partida), static fn($m) => ($m['clasificacion'] ?? '') === 'cotilleo'));
$rHablar = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::HABLAR, [], $catalog);
ok($rHablar['ok'] ?? false, 'hablar ejecuta ok');
ok(!empty($partida['encuentros'][0]['intervencion_celeste']['usada']), 'marca intervención usada');
ok(count($partida['encuentros'][0]['turnos'] ?? []) === 1, 'FASE 1: registra turno 1');
ok(is_int($partida['encuentros'][0]['barra_quedada'] ?? null), 'FASE 1: barra_quedada existe');
$despuesCoti = count(array_filter(BuzonEngine::listar($partida), static fn($m) => ($m['clasificacion'] ?? '') === 'cotilleo'));
ok($despuesCoti === $antesCoti, 'hablar trivial no genera cotilleo');

/* FASE 1: Segunda intervención ahora SÍ está permitida (múltiples turnos). */
$r2 = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::BROMA, [], $catalog);
ok(($r2['ok'] ?? false) === true, 'FASE 1: segunda intervención permitida');
ok(count($partida['encuentros'][0]['turnos'] ?? []) === 2, 'FASE 1: registra turno 2');

[$svc2, $partida2, $a2, $b2, $catalog2] = setupEncuentroEnCurso();
$encId2 = (string) ($partida2['encuentros'][0]['id'] ?? '');
$rFake = EncuentroIntervencion::ejecutar($partida2, $encId2, EncuentroIntervencion::HOBBY, ['hobby_id' => 'hobby_inventado'], $catalog2);
ok(!($rFake['ok'] ?? true), 'hobby inventado rechazado');

[$svc2, $partida2, $a2, $b2, $catalog2] = setupEncuentroEnCurso();
$encId2 = (string) ($partida2['encuentros'][0]['id'] ?? '');
RelacionEngine::registrarContacto($partida2, $a2, $b2, 'normal', $cal, 1, 3);
RelacionEngine::registrarContacto($partida2, $b2, $a2, 'normal', $cal, 1, 3);
$partida2['encuentros'][0]['tipo'] = 'primera_cita';
RelacionEngine::setRomanceHacia($partida2, $a2, $b2, SenalRomantica::umbralTilin($cal) + 3);
RelacionEngine::setRomanceHacia($partida2, $b2, $a2, SenalRomantica::umbralTilin($cal) + 3);
$partida2['rng']['seed'] = 424242;
/* FASE 1: beso no está en ACCIONES_SOCIALES, debe ser rechazado. */
$rBeso = EncuentroIntervencion::ejecutar($partida2, $encId2, EncuentroIntervencion::BESO, [], $catalog2);
ok(!($rBeso['ok'] ?? false), 'FASE 1: beso rechazado (no es acción social)');

echo $failures === 0 ? "encuentro_intervencion_test OK\n" : "encuentro_intervencion_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
