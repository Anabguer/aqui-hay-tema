<?php
declare(strict_types=1);

/**
 * Título MENTES (tono CSS) coherente con afinidad del tema — no con resultado final del encuentro.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroExperiencia;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\MentesTemas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;

$root = dirname(__DIR__);
$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

ok(MentesTemas::tonoMomentoDeAfinidad('afin') === 'bien', 'CASO 5 map: afin → bien');
ok(MentesTemas::tonoMomentoDeAfinidad('neutro') === 'neutral', 'CASO 6 map: neutro → neutral');
ok(MentesTemas::tonoMomentoDeAfinidad('aversion') === 'mal', 'CASO 7 map: aversión → mal');

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
$catalog = new Catalog($root);
$cal = CalibracionConfig::load($root);
$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('test_fixtures_v0', 'mentes-tono-' . microtime(true));
$ph1 = $svc->crearResidentePlaceholderDev($partida);
$ph2 = $svc->crearResidentePlaceholderDev($partida);
$rompe = (string) $ph1['residente']['catalog_id'];
$interlocutor = (string) $ph2['residente']['catalog_id'];
$hora = encontrarHoraLibre($partida, [$rompe, $interlocutor], 1);
$r = $svc->programarEncuentro($partida, [$rompe, $interlocutor], 1, $hora, 'conocerse', 'lug_cafeteria');
if (!($r['ok'] ?? false)) {
    fwrite(STDERR, "no programa encuentro\n");
    exit(1);
}
while ((int) $partida['reloj']['hora_actual'] < $hora) {
    $svc->avanzarReloj($partida, 1);
}
EncuentroLifecycle::sincronizarConReloj($partida, null, $catalog);
$encId = (string) ($partida['encuentros'][0]['id'] ?? '');

$perfilB = PerfilPartida::deOLegacy($partida, $interlocutor, $catalog);
$hobbyB = '';
foreach ($perfilB['hobbies'] ?? [] as $hh) {
    if (is_string($hh) && $hh !== '') {
        $hobbyB = $hh;
        break;
    }
}
if ($hobbyB === '') {
    $hobbyB = 'cine';
}
DiscoveryReveal::registrarJugador($partida, $interlocutor, ConocimientoNpc::campoHobby($hobbyB), $hobbyB, 'test');
$afinidad = MentesTemas::evaluarAfinidad($partida, $interlocutor, $hobbyB, $catalog);

$ej = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::HOBBY, [
    'objetivo' => $rompe,
    'hobby_id' => $hobbyB,
], $catalog);
ok(($ej['ok'] ?? false), 'ejecutar MENTES ok');
$int = is_array($ej['intervencion'] ?? null) ? $ej['intervencion'] : [];
$tono = (string) ($int['tono'] ?? '');
$texto = (string) ($int['texto'] ?? '');
$afinGuardada = (string) ($int['afinidad_tema'] ?? '');

if ($afinidad === 'afin') {
    ok($tono === 'bien', 'CASO 5: tono bien con tema afín');
    ok($texto !== '' && stripos($texto, 'sin mucho efecto') === false, 'CASO 5: cuerpo no neutro');
    ok(
        stripos($texto, 'enganch') !== false
        || stripos($texto, 'inter') !== false
        || stripos($texto, 'curios') !== false
        || stripos($texto, 'Buen') !== false
        || stripos($texto, 'tira') !== false,
        'CASO 5: cuerpo positivo compatible (' . $texto . ')'
    );
} elseif ($afinidad === 'aversion') {
    ok($tono === 'mal', 'CASO 7: tono mal con aversión');
} else {
    ok($tono === 'neutral', 'CASO 6: tono neutral con tema neutro');
}

// CASO 8: tema afín pero experiencia final mala (relación muy tensa)
$partidaMala = $partida;
$partidaMala['relaciones']['social'][$rompe][$interlocutor] = ['valor' => -50, 'tipo' => 'conocido'];
$partidaMala['relaciones']['social'][$interlocutor][$rompe] = ['valor' => -45, 'tipo' => 'conocido'];
$enc = EncuentroIntervencion::buscar($partidaMala, $encId);
ok($enc !== null, 'encuentro post-intervención');
$rng = RngService::fromPartida($partidaMala);
$exp = EncuentroExperiencia::resolver($partidaMala, $enc, $catalog, $rng, $cal);
$resFinal = (string) ($exp['por_participante'][$interlocutor]['resultado'] ?? 'normal');
$tonoMomento = (string) ($enc['intervencion_celeste']['tono'] ?? '');
if ($afinGuardada === 'afin') {
    ok($tonoMomento === 'bien', 'CASO 8: momento MENTES sigue positivo');
    ok(in_array($resFinal, ['mal', 'muy_mal', 'normal', 'bien', 'muy_bien'], true), 'CASO 8: experiencia final resuelta');
    ok($tonoMomento !== $resFinal, 'CASO 8: tono momento no es el resultado final del encuentro');
}

echo $fail === 0 ? "\nmentes_tono_momento_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
