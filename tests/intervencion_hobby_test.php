<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $failures++; }
}

DomainBootstrap::boot();
$svc = new PartidaService($root);

function freshEncuentro(PartidaService $svc): array
{
    $partida = $svc->nuevaPartida('test_fixtures_v0', 'enc-test');
    $ph = $svc->crearResidentePlaceholderDev($partida);
    $a = 'per_qa_valid';
    $b = (string) $ph['residente']['catalog_id'];
    $enc = $svc->programarEncuentro($partida, [$a, $b], 1, 19, 'conocerse', 'lug_cafeteria');
    if (!($enc['ok'] ?? false)) {
        throw new RuntimeException('no programa: ' . json_encode($enc));
    }
    while ((int) $partida['reloj']['hora_actual'] < 19) { $svc->avanzarReloj($partida, 1); }
    EncuentroLifecycle::sincronizarConReloj($partida, null, $svc->getCatalog());
    $catalog = $svc->getCatalog();
    $encId = (string) ($enc['encuentro']['id'] ?? '');
    $encRow = EncuentroIntervencion::buscar($partida, $encId);
    if ($encRow === null || ($encRow['estado'] ?? '') !== 'en_curso') {
        throw new RuntimeException('encuentro no en curso');
    }
    return [$partida, $a, $b, $catalog, $encId];
}

echo "\n--- A: cine aceptado + fake rechazado ---\n";
[$partida, $a, $b, $catalog, $encId] = freshEncuentro($svc);
$enc = EncuentroIntervencion::buscar($partida, $encId);
$acciones = EncuentroIntervencion::accionesDisponibles($partida, $enc, $catalog);
$hobbyAcc = null;
foreach ($acciones as $row) { if (($row['id'] ?? '') === 'hobby') $hobbyAcc = $row; }
ok($hobbyAcc !== null, 'hobby present');
$temasAll = [];
foreach (($hobbyAcc['temas_por_objetivo'] ?? []) as $lista) { foreach ($lista as $t) $temasAll[] = $t; }
ok(in_array('cine', array_column($temasAll, 'id'), true), 'cine in temasElegibles');
$rFake = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::HOBBY, ['hobby_id' => 'hobby_inventado_999'], $catalog);
ok(!($rFake['ok'] ?? true), 'fake rejected');
$tp = count($partida['encuentros'][0]['turnos'] ?? []);
$rC = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::HOBBY, ['hobby_id' => 'cine'], $catalog);
ok(($rC['ok'] ?? false) === true, 'cine accepted by backend');
ok(count($partida['encuentros'][0]['turnos'] ?? []) === $tp + 1, 'turn registered');
$rC2 = EncuentroIntervencion::ejecutar($partida, $encId, EncuentroIntervencion::HOBBY, ['hobby_id' => 'cine'], $catalog);
ok(($rC2['ok'] ?? false) === true, 'second cine ok');

echo "\n--- B: VALIDACION_FALLIDA no consume ---\n";
[$p2, $a2, $b2, $c2, $e2] = freshEncuentro($svc);
$rB = EncuentroIntervencion::ejecutar($p2, $e2, EncuentroIntervencion::HOBBY, ['hobby_id' => 'no_existe'], $c2);
ok(($rB['ok'] ?? true) === false, 'error returned');
ok(($rB['error'] ?? '') === 'VALIDACION_FALLIDA', 'error code VALIDACION_FALLIDA');
ok(EncuentroIntervencion::puedeIntervenir($p2, EncuentroIntervencion::buscar($p2, $e2)), 'intervention still available');
ok(empty($p2['encuentros'][0]['intervencion_celeste']['usada']), 'usada not set');
ok(count($p2['encuentros'][0]['turnos'] ?? []) === 0, 'no turns registered');

echo "\n--- C: hablar despues de fallo ---\n";
[$p3, $a3, $b3, $c3, $e3] = freshEncuentro($svc);
EncuentroIntervencion::ejecutar($p3, $e3, EncuentroIntervencion::HOBBY, ['hobby_id' => 'x'], $c3);
$rO = EncuentroIntervencion::ejecutar($p3, $e3, EncuentroIntervencion::HABLAR, [], $c3);
ok(($rO['ok'] ?? false) === true, 'hablar ok after fail');
ok(count($p3['encuentros'][0]['turnos'] ?? []) === 1, '1 turn');

echo "\n--- D+E: agotar turnos ---\n";
[$p4, $a4, $b4, $c4, $e4] = freshEncuentro($svc);
$max = EncuentroIntervencion::turnosMax($p4);
for ($i = 0; $i < $max; $i++) {
    ok((EncuentroIntervencion::ejecutar($p4, $e4, EncuentroIntervencion::HABLAR, [], $c4)['ok'] ?? false), "turno $i");
}
ok(count($p4['encuentros'][0]['turnos'] ?? []) === $max, 'max reached');
ok(!(EncuentroIntervencion::ejecutar($p4, $e4, EncuentroIntervencion::HABLAR, [], $c4)['ok'] ?? false), 'blocked after max');

echo "\n--- F: 1 click = 1 turn ---\n";
[$p5, $a5, $b5, $c5, $e5] = freshEncuentro($svc);
EncuentroIntervencion::ejecutar($p5, $e5, EncuentroIntervencion::HABLAR, [], $c5);
ok(count($p5['encuentros'][0]['turnos'] ?? []) === 1, 'exactly 1 turn');
EncuentroIntervencion::ejecutar($p5, $e5, EncuentroIntervencion::BROMA, [], $c5);
ok(count($p5['encuentros'][0]['turnos'] ?? []) === 2, 'exactly 2 turns');

echo $failures === 0 ? "\nintervencion_hobby_test ALL PASS\n" : "\nFAIL ($failures)\n";
exit($failures > 0 ? 1 : 0);
