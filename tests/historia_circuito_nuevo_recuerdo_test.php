<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\TutorialPrimerosPasos;

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

function cleanup(string $partidaId): void
{
    global $root;
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId) ?? $partidaId;
    $consumed = HistoriaPuebloEngine::consumedPath($root, $partidaId);
    if (is_file($consumed)) {
        @unlink($consumed);
    }
    $game = $root . '/data/partidas/' . $safe . '.json';
    if (is_file($game)) {
        @unlink($game);
    }
}

function pending(array $partida, string $root, string $partidaId): array
{
    return array_column(
        HistoriaPuebloEngine::celebracionesPendientes($partida, $root, $partidaId),
        'hito_id'
    );
}

function simularFinaleTutorial(array &$partida): void
{
    $partida['tutorial']['jugable_completado'] = true;
    TutorialPrimerosPasos::marcarFinaleVisto($partida);
}

$service = new PartidaService($root);

echo "\n=== 1. Partida nueva (tutorial): sin recuerdo hasta finale ===\n";
$p = $service->nuevaPartida('juego_v1', 'hp-circuito-1');
$pid = $p['meta']['partida_id'];
ok(count($p['historia_pueblo'] ?? []) === 0, '1.1 sin hito antes del finale');
ok(count(pending($p, $root, $pid)) === 0, '1.2 sin celebración pendiente antes del finale');

simularFinaleTutorial($p);
$service->guardar($p);
ok(HistoriaPuebloEngine::existe(
    $p,
    HistoriaPuebloEngine::clave(HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, array_slice(array_keys($p['residentes']), 0, 3))
), '1.3 hito registrado al terminar tutorial');
$pend1 = pending($p, $root, $pid);
ok(in_array(HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, $pend1, true), '1.4 1 celebración pendiente');
$regalitos = $p['regalito_recompensas'] ?? [];
$claveRegalo = 'historia:' . HistoriaPuebloEngine::clave(
    HistoriaPuebloEngine::HITO_EMPEZO_COTARRO,
    array_slice(array_keys($p['residentes']), 0, 3)
);
ok(isset($regalitos[$claveRegalo]), '1.5 1 regalito al registrar');

echo "\n=== 2. ACK → 0 pendientes ===\n";
HistoriaPuebloEngine::ack($p, HistoriaPuebloEngine::HITO_EMPEZO_COTARRO);
$consumed = HistoriaPuebloEngine::loadConsumed($root, $pid);
if (!in_array(HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, $consumed, true)) {
    $consumed[] = HistoriaPuebloEngine::HITO_EMPEZO_COTARRO;
    HistoriaPuebloEngine::saveConsumed($root, $pid, $consumed);
}
$service->guardarRapido($p);
ok(HistoriaPuebloEngine::estaConsumida($p, HistoriaPuebloEngine::HITO_EMPEZO_COTARRO), '2.1 ACK consumida');
ok(count(pending($p, $root, $pid)) === 0, '2.2 0 pendientes tras ACK');

echo "\n=== 3. F5 / reload → sigue 0 pendientes, 0 regalos nuevos ===\n";
unset($p);
$p2 = $service->cargarParaRefresh($pid);
ok(count(pending($p2, $root, $pid)) === 0, '3.1 F5: 0 pendientes');
$nRegalos = count($p2['regalito_recompensas'] ?? []);
$service->cargarParaRefresh($pid);
ok(count($p2['regalito_recompensas'] ?? []) === $nRegalos, '3.2 F5: 0 regalos nuevos');

echo "\n=== 4. Avance temporal → primer recuerdo no reaparece ===\n";
$service->avanzarReloj($p2, 4);
$service->guardar($p2);
$p3 = $service->cargarParaRefresh($pid);
ok(count(pending($p3, $root, $pid)) === 0, '4.1 tras avanzar tiempo: 0 pendientes');

echo "\n=== 5. Segundo hito (hito_02 / se_conocieron) ===\n";
$res = array_slice(array_keys($p3['residentes']), 0, 2);
RelacionBitacora::registrar($p3, RelacionBitacora::SE_CONOCIERON, $res);
$service->guardar($p3);
$pend2 = pending($p3, $root, $pid);
ok(in_array('hito_02', $pend2, true), '5.1 hito_02 pendiente tras se_conocieron');
$clave2 = 'historia:' . HistoriaPuebloEngine::clave('hito_02', $res);
ok(isset($p3['regalito_recompensas'][$clave2]), '5.2 1 regalito hito_02');

HistoriaPuebloEngine::ack($p3, 'hito_02');
$consumed2 = HistoriaPuebloEngine::loadConsumed($root, $pid);
if (!in_array('hito_02', $consumed2, true)) {
    $consumed2[] = 'hito_02';
    HistoriaPuebloEngine::saveConsumed($root, $pid, $consumed2);
}
$service->guardarRapido($p3);
unset($p3);
$p4 = $service->cargarParaRefresh($pid);
ok(count(pending($p4, $root, $pid)) === 0, '5.3 ACK+F5 segundo hito: 0 pendientes');

echo "\n=== 6. Race: guardado concurrente no revive consumida ===\n";
$p5 = $service->cargarParaRefresh($pid);
foreach ($p5['historia_pueblo'] as &$e) {
    if (($e['hito_id'] ?? '') === HistoriaPuebloEngine::HITO_EMPEZO_COTARRO) {
        $e['celebracion_estado'] = 'pendiente';
    }
}
unset($e);
$service->guardar($p5);
$p6 = $service->cargarParaRefresh($pid);
ok(!in_array(HistoriaPuebloEngine::HITO_EMPEZO_COTARRO, pending($p6, $root, $pid), true),
    '6.1 monotonic merge: cotarro no revive tras guardado stale');

cleanup($pid);

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
