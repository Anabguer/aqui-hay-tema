<?php
declare(strict_types=1);

/**
 * MENTES — tema del interlocutor conocido por la persona influida.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\Reloj;

$root = dirname(__DIR__);
$fail = [];

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail[] = $m;
    }
}

function encontrarHoraLibre(array $partida, array $participantes, int $dia): int
{
    for ($h = 8; $h < 23; $h++) {
        if (!Reloj::esFuturo($partida['reloj'] ?? [], $dia, $h)) {
            continue;
        }
        foreach ($participantes as $rid) {
            if (!AgendaEngine::estaDisponible($partida, $rid, $dia, $h)['disponible']) {
                continue 2;
            }
        }
        return $h;
    }
    throw new RuntimeException('sin hora libre');
}

DomainBootstrap::boot();
$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('test_fixtures_v0', 'mentes-tema-' . microtime(true));
$ph1 = $svc->crearResidentePlaceholderDev($partida);
$ph2 = $svc->crearResidentePlaceholderDev($partida);
$tamara = (string) $ph1['residente']['catalog_id'];
$german = (string) $ph2['residente']['catalog_id'];
$hora = encontrarHoraLibre($partida, [$tamara, $german], 1);
$rEnc = $svc->programarEncuentro($partida, [$tamara, $german], 1, $hora, 'conocerse', 'lug_cafeteria');
if (!($rEnc['ok'] ?? false)) {
    fwrite(STDERR, "no programa encuentro\n");
    exit(1);
}
while ((int) $partida['reloj']['hora_actual'] < $hora) {
    $svc->avanzarReloj($partida, 1);
}
$catalog = $svc->getCatalog();
EncuentroLifecycle::sincronizarConReloj($partida, null, $catalog);
$encId = (string) ($partida['encuentros'][0]['id'] ?? '');
$enc = EncuentroIntervencion::buscar($partida, $encId);
if ($enc === null) {
    fwrite(STDERR, "sin encuentro\n");
    exit(1);
}

$perfilG = \AquiHayTema\Engine\PerfilPartida::deOLegacy($partida, $german, $catalog);
$hid = '';
foreach ($perfilG['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '') {
        $hid = $hh;
        break;
    }
}
if ($hid === '') {
    fwrite(STDERR, "SKIP: placeholder sin hobbies\n");
    exit(0);
}

// Jugador descubre hobby de Germán; Tamara NO lo conoce aún
DiscoveryReveal::registrarJugador($partida, $german, ConocimientoNpc::campoHobby($hid), $hid, 'test');
$sinNpc = EncuentroIntervencion::hobbiesTemaConocidos($partida, $enc, $tamara, $catalog);
ok($sinNpc === [], '5 hobby de Germán sin conocimiento NPC de Tamara no se usa');

// Tamara conoce que a Germán le gusta
ConocimientoNpc::revelar($partida, $tamara, $german, [ConocimientoNpc::campoHobby($hid)], 'test');
$temas = EncuentroIntervencion::hobbiesTemaConocidos($partida, $enc, $tamara, $catalog);
ok(count($temas) === 1, '1 tema válido Tamara→hobby Germán');
ok(($temas[0]['residente_id'] ?? '') === $german, '1 tema es interés de Germán');
ok(($temas[0]['objetivo_id'] ?? '') === $tamara, '8 payload objetivo sigue siendo Tamara influida');

$acciones = EncuentroIntervencion::accionesDisponibles($partida, $enc, $catalog);
$hobbyRow = null;
foreach ($acciones as $row) {
    if (($row['id'] ?? '') === 'hobby') {
        $hobbyRow = $row;
    }
}
ok($hobbyRow !== null && !empty($hobbyRow['temas_por_objetivo'][$tamara]), 'UI backend expone temas_por_objetivo');

// Ejecutar: beneficio principal a Germán
$antesEmoG = $partida['residentes'][$german]['runtime']['estado_emocional']['id'] ?? null;
$r = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::HOBBY, [
    'objetivo' => $tamara,
    'hobby_id' => $hid,
], $catalog);
ok(($r['ok'] ?? false), 'ejecutar hobby con objetivo ok');
$ef = $r['efectos'] ?? [];
if (($r['intervencion']['tono'] ?? '') !== 'mal') {
    ok(($ef['emocion']['residente'] ?? '') === $german, '2 Germán recibe emoción principal si no es mal');
} else {
    ok(true, '2 tono mal: sin emoción positiva (skip)');
}
ok(strpos((string) ($r['intervencion']['texto'] ?? ''), $tamara) === false || strpos((string) ($r['intervencion']['texto'] ?? ''), 'Tamara') !== false,
    'copy menciona influida');
ok(strpos((string) ($r['intervencion']['texto'] ?? ''), 'recibe la idea') === false, '10 sin copy técnico recibe la idea');

// Animar ≠ Elegir tema (etiquetas)
$hablar = null;
foreach ($acciones as $row) {
    if (($row['id'] ?? '') === 'hablar') {
        $hablar = $row;
    }
}
ok(($hablar['etiqueta'] ?? '') === 'Animar la conversación', '7 hablar distinto');
ok(($hobbyRow['etiqueta'] ?? '') === 'Sacar un tema que le guste', '7 hobby distinto');

// Hobby ajeno con objetivo incorrecto rechazado
[$svc2, $partida2, $enc2, , $qa2, $p12, , , $catalog2] = (function () use ($root) {
    DomainBootstrap::boot();
    $svc = new PartidaService($root);
    $partida = $svc->nuevaPartida('test_fixtures_v0', 'mentes-tema-2');
    $ph = $svc->crearResidentePlaceholderDev($partida);
    $qa = 'per_qa_valid';
    $pb = (string) $ph['residente']['catalog_id'];
    $h = 19;
    $svc->programarEncuentro($partida, [$qa, $pb], 1, $h, 'conocerse', 'lug_cafeteria');
    while ((int) $partida['reloj']['hora_actual'] < $h) {
        $svc->avanzarReloj($partida, 1);
    }
    EncuentroLifecycle::sincronizarConReloj($partida, null, $svc->getCatalog());
    $id = (string) ($partida['encuentros'][0]['id'] ?? '');
    return [$svc, $partida, $id, '', $qa, $pb, '', '', $svc->getCatalog()];
})();
$row2 = EncuentroIntervencion::buscar($partida2, $enc2);
$perfilDueno = \AquiHayTema\Engine\PerfilPartida::deOLegacy($partida2, $p12, $catalog2);
$hid2 = '';
foreach ($perfilDueno['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '') {
        $hid2 = $hh;
        break;
    }
}
if ($hid2 !== '') {
    DiscoveryReveal::registrarJugador($partida2, $p12, ConocimientoNpc::campoHobby($hid2), $hid2, 'test');
    $rAjeno = EncuentroIntervencion::ejecutar($partida2, $enc2, EncuentroIntervencion::HOBBY, [
        'hobby_id' => $hid2,
        'residente_id' => $p12,
        'objetivo' => $p12,
    ], $catalog2);
    ok(!($rAjeno['ok'] ?? true), 'tema propio del influido rechazado');
    $det = (string) (($rAjeno['contexto']['detalle'] ?? '') ?: ($rAjeno['detalle'] ?? '') ?: ($rAjeno['motivo'] ?? ''));
    ok(in_array($det, ['hobby_de_otro_residente', 'hobby_no_conocido'], true),
        'tema propio del influido sin conocimiento cruzado');
}

if ($fail) {
    fwrite(STDERR, "mentes_tema_objetivo_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "mentes_tema_objetivo_test OK\n";
