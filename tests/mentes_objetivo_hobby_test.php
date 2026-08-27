<?php declare(strict_types=1);

/**
 * MENTES — objetivo, interlocutor, hobby y copy coherentes.
 * Cubre la intención: Celestine influye en A para que saque un tema que interese a B.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PerfilPartida;

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

function encontrarHoraLibre(array $partida, array $participantes, int $dia): int
{
    $reloj = $partida['reloj'] ?? [];
    for ($h = 8; $h < 23; $h++) {
        if (!\AquiHayTema\Engine\Reloj::esFuturo($reloj, $dia, $h)) {
            continue;
        }
        $libre = true;
        foreach ($participantes as $rid) {
            $d = AgendaEngine::estaDisponible($partida, $rid, $dia, $h);
            if (!$d['disponible']) {
                $libre = false;
                break;
            }
        }
        if ($libre) {
            return $h;
        }
    }
    throw new RuntimeException('no hay hora libre');
}

/**
 * @return array{0: PartidaService, 1: array, 2: string, 3: string, 4: string, 5: Catalog}
 */
function setupEncuentro(): array
{
    global $root;
    DomainBootstrap::boot();
    $svc = new PartidaService($root);
    $partida = $svc->nuevaPartida('test_fixtures_v0', 'mentes-' . microtime(true));
    $ph = $svc->crearResidentePlaceholderDev($partida);
    $qa = 'per_qa_valid';
    $otro = (string) $ph['residente']['catalog_id'];
    $hora = encontrarHoraLibre($partida, [$qa, $otro], 1);
    $r = $svc->programarEncuentro($partida, [$qa, $otro], 1, $hora, 'conocerse', 'lug_cafeteria');
    if (!($r['ok'] ?? false)) {
        throw new RuntimeException('no programa encuentro');
    }
    while ((int) $partida['reloj']['hora_actual'] < $hora) {
        $svc->avanzarReloj($partida, 1);
    }
    EncuentroLifecycle::sincronizarConReloj($partida, null, $svc->getCatalog());
    $encId = (string) ($partida['encuentros'][0]['id'] ?? '');
    return [$svc, $partida, $encId, $qa, $otro, $svc->getCatalog()];
}

[$svc, $partida, $encId, $influido, $interlocutor, $catalog] = setupEncuentro();
$enc = EncuentroIntervencion::buscar($partida, $encId);
if ($enc === null) {
    throw new RuntimeException('encuentro no encontrado');
}

$perfilInfluido = PerfilPartida::deOLegacy($partida, $influido, $catalog);
$perfilInter = PerfilPartida::deOLegacy($partida, $interlocutor, $catalog);
$hobbyInter = '';
$hobbyInfluido = '';
foreach ($perfilInter['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '') {
        $hobbyInter = $hh;
        break;
    }
}
foreach ($perfilInfluido['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '' && $hh !== $hobbyInter) {
        $hobbyInfluido = $hh;
        break;
    }
}
if ($hobbyInter === '') {
    echo "SKIP: placeholder sin hobbies de interlocutor\n";
    exit(0);
}

/* NPC influido conoce hobby del interlocutor; jugador lo ha descubierto de B */
ConocimientoNpc::revelar($partida, $influido, $interlocutor, [ConocimientoNpc::campoHobby($hobbyInter)], 'test');
DiscoveryReveal::registrarJugador($partida, $interlocutor, ConocimientoNpc::campoHobby($hobbyInter), $hobbyInter, 'test');
if ($hobbyInfluido !== '') {
    DiscoveryReveal::registrarJugador($partida, $influido, ConocimientoNpc::campoHobby($hobbyInfluido), $hobbyInfluido, 'test');
}

$temas = EncuentroIntervencion::hobbiesTemaConocidos($partida, $enc, $influido, $catalog);
$idsTema = array_map(static fn($o) => (string) ($o['id'] ?? ''), $temas);
ok(in_array($hobbyInter, $idsTema, true), '1. tema corresponde a hobby conocido del interlocutor');
ok(count($temas) >= 1 && (string) ($temas[0]['residente_id'] ?? '') === $interlocutor,
    '1b. residente_id del tema es el interlocutor');

/* hobby del influido NO debe salir como tema para influir al interlocutor */
if ($hobbyInfluido !== '') {
    ok(!in_array($hobbyInfluido, $idsTema, true), '3. hobby propio del influido no aparece como tema');
}

$partida['rng']['seed'] = 777001;
$r = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::HOBBY, [
    'objetivo' => $influido,
    'hobby_id' => $hobbyInter,
], $catalog);
ok(($r['ok'] ?? false), '8. hobby con objetivo ejecuta ok');
ok((string) ($r['intervencion']['objetivo'] ?? '') === $influido, '8b. payload objetivo = influido');
ok((string) ($r['intervencion']['beneficiario'] ?? '') === $interlocutor, '2. beneficiario principal = interlocutor');
ok((string) ($r['intervencion']['interlocutor'] ?? '') === $interlocutor, 'interlocutor persistido');

$texto = (string) ($r['intervencion']['texto'] ?? '');
ok(strpos($texto, 'sugerido') !== false || strpos($texto, 'tema') !== false, 'copy causa-efecto presente');
ok(strpos($texto, 'bonus') === false && strpos($texto, 'modificador') === false, 'copy sin jerga tecnica');

$efectos = is_array($r['efectos'] ?? null) ? $r['efectos'] : [];
$emo = is_array($efectos['emocion'] ?? null) ? $efectos['emocion'] : [];
ok((string) ($emo['residente'] ?? '') === $interlocutor, '2b. emocion al interlocutor (beneficiario)');

/* hobby del influido rechazado cuando objetivo apunta al interlocutor */
[$svc2, $partida2, $encId2, $influido2, $interlocutor2, $catalog2] = setupEncuentro();
$enc2 = EncuentroIntervencion::buscar($partida2, $encId2);
$perfilI2 = PerfilPartida::deOLegacy($partida2, $influido2, $catalog2);
$hidPropio = '';
foreach ($perfilI2['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '') {
        $hidPropio = $hh;
        DiscoveryReveal::registrarJugador($partida2, $influido2, ConocimientoNpc::campoHobby($hidPropio), $hidPropio, 'test');
        break;
    }
}
if ($hidPropio !== '') {
    $rMal = EncuentroIntervencion::ejecutar($partida2, $encId2, EncuentroIntervencion::HOBBY, [
        'objetivo' => $interlocutor2,
        'hobby_id' => $hidPropio,
        'residente_id' => $influido2,
    ], $catalog2);
    ok(!($rMal['ok'] ?? true), 'tema del influido rechazado al influir al interlocutor');
}

/* hobby no conocido -> fallback seguro */
[$svc3, $partida3, $encId3, , , $catalog3] = setupEncuentro();
$rFake = EncuentroIntervencion::ejecutar($partida3, $encId3, EncuentroIntervencion::HOBBY, [
    'objetivo' => $influido,
    'hobby_id' => 'hobby_inventado_xyz',
], $catalog3);
ok(!($rFake['ok'] ?? true), '5. hobby no conocido rechazado');

/* hobby compartido: ambos pueden beneficiarse si influido tambien lo tiene */
[$svc4, $partida4, $encId4, $influido4, $interlocutor4, $catalog4] = setupEncuentro();
$perfilA4 = PerfilPartida::deOLegacy($partida4, $influido4, $catalog4);
$perfilB4 = PerfilPartida::deOLegacy($partida4, $interlocutor4, $catalog4);
$compartido = '';
foreach ($perfilB4['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '' && in_array($hh, $perfilA4['hobbies'] ?? [], true)) {
        $compartido = $hh;
        break;
    }
}
if ($compartido !== '') {
    ConocimientoNpc::revelar($partida4, $influido4, $interlocutor4, [ConocimientoNpc::campoHobby($compartido)], 'test');
    DiscoveryReveal::registrarJugador($partida4, $interlocutor4, ConocimientoNpc::campoHobby($compartido), $compartido, 'test');
    $partida4['rng']['seed'] = 424242;
    $rComp = EncuentroIntervencion::ejecutar($partida4, $encId4, EncuentroIntervencion::HOBBY, [
        'objetivo' => $influido4,
        'hobby_id' => $compartido,
    ], $catalog4);
    if (($rComp['intervencion']['tono'] ?? '') === 'bien') {
        $ef4 = is_array($rComp['efectos'] ?? null) ? $rComp['efectos'] : [];
        ok(isset($ef4['emocion_compartida']) || isset($ef4['emocion']), '4. hobby compartido puede beneficiar');
    } else {
        ok(true, '4. hobby compartido: tono no bien, skip emocion compartida');
    }
} else {
    ok(true, '4. SKIP: sin hobby compartido en fixtures');
}

/* Animar conversacion != Elegir tema: factores y copy distintos */
[$svc5, $partida5, $encId5, $influido5, , $catalog5] = setupEncuentro();
$partida5['rng']['seed'] = 123456;
$rHablar = EncuentroIntervencion::ejecutar($partida5, $encId5, EncuentroIntervencion::HABLAR, [
    'objetivo' => $influido5,
], $catalog5);
ok(($rHablar['ok'] ?? false), '6. animar conversacion ejecuta');
$txtH = (string) ($rHablar['intervencion']['texto'] ?? '');
ok(strpos($txtH, 'empujoncito') !== false, '6b. hablar copy de empujoncito');
ok(strpos($txtH, 'tema de') === false, '6c. hablar no menciona tema concreto');

echo $failures === 0 ? "mentes_objetivo_hobby_test OK\n" : "mentes_objetivo_hobby_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
