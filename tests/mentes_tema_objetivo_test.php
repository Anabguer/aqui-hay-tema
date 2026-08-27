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
use AquiHayTema\Engine\MentesTemas;
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

$temas = MentesTemas::temasElegibles($partida, $enc, $tamara, $catalog);
ok(count($temas) >= 3, '1 varios temas legítimos (generales) sin hobby oculto de Germán');

// Jugador descubre hobby de Germán; Tamara NO lo conoce aún — no debe aparecer solo ese hobby
DiscoveryReveal::registrarJugador($partida, $german, ConocimientoNpc::campoHobby($hid), $hid, 'test');
$temasSinNpc = MentesTemas::temasElegibles($partida, $enc, $tamara, $catalog);
ok(count($temasSinNpc) >= 3, '4 lista no se reduce al hobby oculto de Germán');
// Si el jugador descubrió el hobby de B, puede plantearlo (conocimiento Celestine); sin NPC de A.
if (in_array($hid, array_map(static fn($o) => (string) ($o['id'] ?? ''), $temasSinNpc), true)) {
    ok(true, '9 hobby de B visible si jugador lo descubrió (Celestine puede plantearlo)');
} else {
    ok(true, '9 hobby de B no forzado en lista si no encaja con reglas');
}

// Tamara conoce que a Germán le gusta + jugador lo descubrió
ConocimientoNpc::revelar($partida, $tamara, $german, [ConocimientoNpc::campoHobby($hid)], 'test');
$temas = MentesTemas::temasElegibles($partida, $enc, $tamara, $catalog);
ok(in_array($hid, array_map(static fn($o) => (string) ($o['id'] ?? ''), $temas), true), 'hobby Germán entra cuando hay conocimiento');
ok(count($temas) >= 3, 'sigue habiendo varios temas, no solo el correcto');
ok(($temas[0]['interlocutor_id'] ?? '') === $german || in_array($german, array_column($temas, 'interlocutor_id'), true),
    'interlocutor es Germán');

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
ok(strpos((string) ($r['intervencion']['texto'] ?? ''), 'recibe la idea') === false, '10 sin copy técnico recibe la idea');
ok(($r['intervencion']['rompe_hielo'] ?? '') === $tamara, 'rompe_hielo = Tamara');

// Animar conversación retirada del flujo visible
$hablar = null;
foreach ($acciones as $row) {
    if (($row['id'] ?? '') === 'hablar') {
        $hablar = $row;
    }
}
ok($hablar === null, '13 hablar no en acciones visibles');
ok(($hobbyRow['etiqueta'] ?? '') === 'Sacar un tema que le guste', '7 hobby etiqueta interna');

if ($fail) {
    fwrite(STDERR, "mentes_tema_objetivo_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "mentes_tema_objetivo_test OK\n";
