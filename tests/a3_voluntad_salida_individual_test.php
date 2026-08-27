<?php
declare(strict_types=1);

/**
 * A3 — Voluntad barata para salidas individuales autónomas (H3).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\Voluntad\VoluntadSalidaIndividual;

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

DomainBootstrap::boot();
$svc = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$ops = ['lug_cafeteria', 'lug_parque', 'lug_biblioteca'];

function fijarEmo(array &$p, string $id, string $emo): void
{
    $p['residentes'][$id]['runtime']['estado_emocional']['id'] = $emo;
}

function fixture(PartidaService $svc): array
{
    $p = $svc->nuevaPartida('juego_v1', 'a3-voluntad-fixture');
    return ['partida' => $p, 'ids' => array_keys($p['residentes'])];
}

$f = fixture($svc);
$rid = (string) $f['ids'][0];

// ---------- emoción: alegre > triste > enfadado ----------
$fA = $f;
fijarEmo($fA['partida'], $rid, EstadoEmocional::ALEGRE);
$sA = VoluntadSalidaIndividual::desglose($fA['partida'], $rid, $ops, $catalog, $cal)['score'];

$fT = $f;
fijarEmo($fT['partida'], $rid, EstadoEmocional::TRISTE);
$sT = VoluntadSalidaIndividual::desglose($fT['partida'], $rid, $ops, $catalog, $cal)['score'];

$fE = $f;
fijarEmo($fE['partida'], $rid, EstadoEmocional::ENFADADO);
$sE = VoluntadSalidaIndividual::desglose($fE['partida'], $rid, $ops, $catalog, $cal)['score'];

ok($sA > $sT, "alegre ($sA) puntua mas que triste ($sT)");
ok($sT > $sE, "triste ($sT) puntua mas que enfadado ($sE)");

// ---------- social medio del pueblo ----------
$fSoc = fixture($svc);
[$r1, $r2, $r3] = array_map('strval', $fSoc['ids']);
RelacionEngine::ajustarSocialHacia($fSoc['partida'], $r1, $r2, 50, $cal);
RelacionEngine::ajustarSocialHacia($fSoc['partida'], $r1, $r3, 40, $cal);
$sAlto = VoluntadSalidaIndividual::desglose($fSoc['partida'], $r1, $ops, $catalog, $cal)['score'];
$sBajo = VoluntadSalidaIndividual::desglose($f['partida'], $rid, $ops, $catalog, $cal)['score'];
ok($sAlto > $sBajo, "social medio alto ($sAlto) supera baseline ($sBajo)");

// ---------- hobby match con lugares disponibles ----------
$perfil = $f['partida']['residentes'][$rid];
$hobbies = $perfil['perfil']['hobbies'] ?? ($perfil['hobbies'] ?? []);
$match = VoluntadSalidaIndividual::tieneHobbyMatch($f['partida'], $rid, $ops, $catalog);
if ($hobbies !== [] && $match) {
    $sinMatch = VoluntadSalidaIndividual::desglose($f['partida'], $rid, ['lug_inexistente'], $catalog, $cal);
    $conMatch = VoluntadSalidaIndividual::desglose($f['partida'], $rid, $ops, $catalog, $cal);
    ok(($conMatch['mod_hobby_match'] ?? 0) > ($sinMatch['mod_hobby_match'] ?? 0), 'hobby match suma bonus cuando hay lugar compatible');
} else {
    ok(true, 'hobby match: fixture sin hobby accionable en ops (skip comparativa)');
}

// ---------- evaluar determinista ----------
$rng1 = new RngService('a3-det', 4242);
$rng2 = new RngService('a3-det', 4242);
$ev1 = VoluntadSalidaIndividual::evaluar($fE['partida'], $rid, $ops, $catalog, $cal, $rng1);
$ev2 = VoluntadSalidaIndividual::evaluar($fE['partida'], $rid, $ops, $catalog, $cal, $rng2);
ok($ev1 === $ev2, 'evaluar determinista con misma seed');

// ---------- rechazo no consume cupo (integracion minima) ----------
$mSalida = new ReflectionMethod(MotorVidaDiaria::class, 'quizasSalidaIndividual');
$mSalida->setAccessible(true);
$pInt = $fE['partida'];
$pInt['reloj'] = ['dia_pueblo' => 3, 'hora_actual' => 12];
$pInt['npc_autonomo']['historial_eventos'] = [];
foreach ($pInt['residentes'] as &$res) {
    $res['runtime']['estado_emocional']['id'] = EstadoEmocional::ENFADADO;
}
unset($res);
$antes = count($pInt['npc_autonomo']['historial_eventos']);
$rechazos = 0;
for ($i = 0; $i < 30; $i++) {
    $rngI = new RngService('a3-cupo', 9000 + $i * 131);
    $clon = unserialize(serialize($pInt));
    $resSalida = $mSalida->invokeArgs(null, [&$clon, $catalog, $cal, $rngI, null]);
    if ($resSalida === null) {
        $rechazos++;
        ok(count($clon['npc_autonomo']['historial_eventos']) === $antes, 'rechazo no anade historial (cupo intacto)');
    }
}
ok($rechazos > 0, 'hubo al menos un rechazo en muestra enfadada');

echo "\nA3 voluntad-salida-individual: " . ($failures === 0 ? 'TODO OK' : "$failures FALLOS") . "\n";
exit($failures > 0 ? 1 : 0);
