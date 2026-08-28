<?php
declare(strict_types=1);

/* Regalos F1: anti-farming (cooldown por vecino, no consume al bloquear, repeticion). */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\RegaloEngine;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cal = regalo_cal();
$catalogo = regalo_catalogo();

$p = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['leer']])]),
]);
InventarioEngine::anadir($p, 'libro', 3, $catalogo);

// 1) primer regalo permitido
$r1 = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r1['ok'] && $r1['reaccion'] === RegaloEngine::LE_ENCANTA, 'primer regalo permitido y encanta');
ok(InventarioEngine::cantidad($p, 'libro') === 2, 'primer regalo consume 1');

// 2) segundo durante cooldown: bloqueado y NO consume
$r2 = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok(!$r2['ok'] && $r2['error'] === 'regalo_cooldown', 'segundo durante cooldown bloqueado');
ok(InventarioEngine::cantidad($p, 'libro') === 2, 'bloqueo por cooldown NO consume');
ok(count($p['bitacora_relaciones'] ?? []) === 1, 'bloqueo no registra hito');

// 3) cooldown registrado en MemoriaEventos familia regalo
$evs = array_filter($p['memoria_eventos'] ?? [], static fn($e) => ($e['familia'] ?? '') === 'regalo');
ok(count($evs) === 1, 'MemoriaEventos registra familia regalo');

// 4) tras expirar la ventana (20h) vuelve a permitir
$p['reloj']['dia_pueblo'] = 2;
$p['reloj']['hora_actual'] = 6; // 22h despues
$r3 = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r3['ok'] && $r3['reaccion'] === RegaloEngine::LE_GUSTA, 'tras expirar permite; objeto repetido degrada encanta->gusta');
ok($r3['repetido'] === true, 'repeticion detectada');
ok(InventarioEngine::cantidad($p, 'libro') === 1, 'segundo regalo valido consume 1');
ok(RegaloEngine::vecesObjeto($p, 'per_a', 'libro') === 2, 'bitacora acumula repeticiones');

// 5) cooldown es por vecino: otro vecino no esta bloqueado
$p['reloj'] = ['dia_pueblo' => 2, 'hora_actual' => 7];
$p['residentes']['per_b'] = [
    'identidad_publica' => ['nombre' => 'Beto'],
    'runtime' => ['perfil_partida' => regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_neg' => ['leer']])])],
];
$r4 = RegaloEngine::entregar($p, 'per_b', 'libro', $cal, $catalogo);
ok($r4['ok'] && $r4['reaccion'] === RegaloEngine::NO_LE_GUSTA, 'cooldown por vecino, no global');

// 6) sin ventana configurada nunca suprime (patron opt-in de MemoriaEventos)
$p5 = regalo_fixture_partida(['per_a' => regalo_perfil()]);
InventarioEngine::anadir($p5, 'libro', 2, $catalogo);
$calSin = $cal;
unset($calSin['cooldowns']['por_familia']['regalo']);
$ra = RegaloEngine::entregar($p5, 'per_a', 'libro', $calSin, $catalogo);
$rb = RegaloEngine::entregar($p5, 'per_a', 'libro', $calSin, $catalogo);
ok($ra['ok'] && $rb['ok'], 'sin ventana configurada no suprime (opt-in)');

exit($failures > 0 ? 1 : 0);
