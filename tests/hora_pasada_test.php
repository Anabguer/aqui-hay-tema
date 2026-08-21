<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\GameError;

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('playtest_01', 'hora-pasada-test');
$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$partida['reloj']['dia_pueblo'] = 23;
$partida['reloj']['hora_actual'] = 14;
$ids = [];
foreach ($partida['residentes'] ?? [] as $id => $r) {
    if (($r['presencia'] ?? '') === 'residente') {
        $ids[] = $id;
    }
}
$ids = array_slice($ids, 0, 2);
ok(count($ids) >= 2, 'al menos 2 residentes');
$lug = $partida['celeste']['lugares_desbloqueados'][0] ?? 'lug_cafeteria';
$rPast = PropuestaEncuentroEngine::proponer($partida, $ids, 23, 10, 'conocerse', $lug);
ok(($rPast['error'] ?? '') === GameError::HORA_PASADA, 'dia 23 10:00 rechazado');
$rOk = PropuestaEncuentroEngine::proponer($partida, $ids, 23, 16, 'conocerse', $lug);
ok($rOk['ok'] ?? false, 'dia 23 16:00 procesado');
exit($failures ? 1 : 0);
