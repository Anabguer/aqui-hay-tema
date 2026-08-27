<?php
declare(strict_types=1);

/**
 * MENTES iteración 2 — conocimiento jugable, temas múltiples, sin regalar respuesta.
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
use AquiHayTema\Engine\MentesTemas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;

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

/**
 * @return array{0: PartidaService, 1: array, 2: string, 3: string, 4: string, 5: Catalog}
 */
function setupEnc(): array
{
    global $root;
    DomainBootstrap::boot();
    $svc = new PartidaService($root);
    $partida = $svc->nuevaPartida('test_fixtures_v0', 'mentes-i2-' . microtime(true));
    $ph1 = $svc->crearResidentePlaceholderDev($partida);
    $ph2 = $svc->crearResidentePlaceholderDev($partida);
    $a = (string) $ph1['residente']['catalog_id'];
    $b = (string) $ph2['residente']['catalog_id'];
    $hora = encontrarHoraLibre($partida, [$a, $b], 1);
    $r = $svc->programarEncuentro($partida, [$a, $b], 1, $hora, 'conocerse', 'lug_cafeteria');
    if (!($r['ok'] ?? false)) {
        throw new RuntimeException('no programa encuentro');
    }
    while ((int) $partida['reloj']['hora_actual'] < $hora) {
        $svc->avanzarReloj($partida, 1);
    }
    EncuentroLifecycle::sincronizarConReloj($partida, null, $svc->getCatalog());
    $encId = (string) ($partida['encuentros'][0]['id'] ?? '');
    return [$svc, $partida, $encId, $a, $b, $svc->getCatalog()];
}

DomainBootstrap::boot();
[$svc, $partida, $encId, $rompe, $interlocutor, $catalog] = setupEnc();
$enc = EncuentroIntervencion::buscar($partida, $encId);
if ($enc === null) {
    fwrite(STDERR, "sin encuentro\n");
    exit(1);
}

$cal = CalibracionConfig::load($catalog->getRoot());
$generales = CalibracionConfig::get($cal, 'mentes.temas_generales', []);
ok(is_array($generales) && count($generales) >= 3, 'calibracion: temas generales');

$temasRompe = MentesTemas::temasElegibles($partida, $enc, $rompe, $catalog);
ok(count($temasRompe) >= 3, '3 varios temas legítimos sin descubrir hobbies');
$idsTema = array_map(static function (array $t): string {
    return (string) ($t['id'] ?? '');
}, $temasRompe);
ok(in_array('cine', $idsTema, true), '3 incluye tema general cine');
ok(count($temasRompe) > 1, '4 motor no deja solo la respuesta correcta');

$perfilB = PerfilPartida::deOLegacy($partida, $interlocutor, $catalog);
$hobbyB = '';
foreach ($perfilB['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '') {
        $hobbyB = $hh;
        break;
    }
}
if ($hobbyB !== '') {
    DiscoveryReveal::registrarJugador($partida, $interlocutor, ConocimientoNpc::campoHobby($hobbyB), $hobbyB, 'test');
    $temasConHobby = MentesTemas::temasElegibles($partida, $enc, $rompe, $catalog);
    $ids2 = array_map(static function (array $t): string {
        return (string) ($t['id'] ?? '');
    }, $temasConHobby);
    ok(in_array($hobbyB, $ids2, true), 'hobby descubierto entra en lista');
    ok(count($temasConHobby) >= count($temasRompe), 'lista no se reduce al hobby de B');
    foreach ($temasConHobby as $row) {
        $et = (string) ($row['etiqueta'] ?? '');
        ok(strpos($et, 'recomendado') === false && strpos($et, '✓') === false, '5 sin marcar respuesta correcta: ' . $et);
    }
}

$acciones = EncuentroIntervencion::accionesDisponibles($partida, $enc, $catalog);
$idsAcc = array_map(static function (array $r): string {
    return (string) ($r['id'] ?? '');
}, $acciones);
ok(!in_array('hablar', $idsAcc, true), '13 Animar la conversación no en acciones visibles');
$hobbyRow = null;
foreach ($acciones as $row) {
    if (($row['id'] ?? '') === 'hobby') {
        $hobbyRow = $row;
    }
}
ok($hobbyRow !== null && !empty($hobbyRow['kickers_rompe']), '2 kickers rompe hielo en API');
ok(!empty($hobbyRow['temas_por_objetivo'][$rompe]), '1 temas por rompe hielo');

$rng = RngService::fromPartida($partida);
$k1 = MentesTemas::kickerRompeHielo($partida, $enc, $rompe, $rng);
$k2 = MentesTemas::kickerRompeHielo($partida, $enc, $rompe, $rng);
ok($k1 !== '' && strpos($k1, '…') !== false || strpos($k1, '?') !== false, '2 kicker no vacío');
$bankOk = is_file($root . '/src/Engine/MentesTemas.php')
    && strpos(file_get_contents($root . '/src/Engine/MentesTemas.php'), 'afin_bien') !== false;
ok($bankOk, '12 banco copy con variantes');

if ($hobbyB !== '') {
    ok(MentesTemas::evaluarAfinidad($partida, $interlocutor, $hobbyB, $catalog) === 'afin', '5 tema afinidad B positivo');
}
ok(MentesTemas::evaluarAfinidad($partida, $interlocutor, 'cine', $catalog) === 'neutro'
    || MentesTemas::evaluarAfinidad($partida, $interlocutor, 'cine', $catalog) === 'afin', '6 tema neutro o afin sin penalizar por defecto');

$perfilNeg = PerfilPartida::deOLegacy($partida, $interlocutor, $catalog);
$prefs = is_array($perfilNeg['preferencias'] ?? null) ? $perfilNeg['preferencias'] : [];
$neg = is_array($prefs['hobbies_neg'] ?? null) ? $prefs['hobbies_neg'] : [];
if ($neg !== []) {
    $hidNeg = (string) $neg[0];
    ok(MentesTemas::evaluarAfinidad($partida, $interlocutor, $hidNeg, $catalog) === 'aversion', '7 incompatible solo con hobbies_neg');
} else {
    ok(true, '7 sin hobbies_neg en fixture (skip aversión)');
}

// Hobby oculto al jugador no aparece
if ($hobbyB !== '') {
    [$svcO, $partidaO, $encIdO, $rompeO, $interlocutorO, $catalogO] = setupEnc();
    $encO = EncuentroIntervencion::buscar($partidaO, $encIdO);
    $sinDescubrir = MentesTemas::temasElegibles($partidaO, $encO, $rompeO, $catalogO);
    $idsOc = array_map(static function (array $t): string {
        return (string) ($t['id'] ?? '');
    }, $sinDescubrir);
    ok(!in_array($hobbyB, $idsOc, true), '9 no revelar hobby de B sin descubrimiento del jugador');
}

$temaEjecutar = $idsTema[0] ?? 'cine';
$r = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::HOBBY, [
    'objetivo' => $rompe,
    'hobby_id' => $temaEjecutar,
], $catalog);
ok(($r['ok'] ?? false), '14 ejecutar no rompe lifecycle');
$int = $r['intervencion'] ?? [];
ok(($int['rompe_hielo'] ?? '') === $rompe, '10 persistencia rompe_hielo');
ok(($int['interlocutor'] ?? '') === $interlocutor, '10 persistencia interlocutor');
ok(($int['tema_id'] ?? '') === $temaEjecutar, '10 persistencia tema');
ok(($int['afinidad_tema'] ?? '') !== '', '10 persistencia afinidad');
$txt = (string) ($int['texto'] ?? '');
ok($txt !== '' && strpos($txt, 'bonus') === false && strpos($txt, 'factor') === false, '11 copy narrativo sin técnico');
ok(strpos($txt, 'recibe la idea') === false, '11 sin recibe la idea');

$encPost = EncuentroIntervencion::buscar($partida, $encId);
ok($encPost !== null && !empty($encPost['intervencion_celeste']['usada']), '14 intervención registrada en encuentro');

if ($fail) {
    fwrite(STDERR, "mentes_iteracion2_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}
echo "mentes_iteracion2_test OK\n";
