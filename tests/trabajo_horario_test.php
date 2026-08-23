<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AcontecimientoDiario;
use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\ResidenteRuntime;
use AquiHayTema\Engine\TrabajoHorario;

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

/** @return array<string, mixed> */
function partidaBase(): array
{
    $p = [
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10, 'minuto_actual' => 0, 'dia_semana_base' => 'lunes'],
        'residentes' => [],
        'encuentros' => [],
        'celeste' => [],
        'npc_autonomo' => ['planes_pendientes' => []],
        'rng' => ['seed' => 'test-trabajo-horario', 'state' => 1],
    ];
    $r = ResidenteRuntime::crearPlaceholderDev(1);
    $r['runtime']['ocupacion'] = 'oficina';
    $p['residentes'][$r['catalog_id']] = $r;
    return $p;
}

function fijarHorario(array &$p, string $id, array $dias, int $ini): void
{
    $dias = array_values(array_unique($dias));
    while (count($dias) < 3) {
        foreach (['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'] as $d) {
            if (!in_array($d, $dias, true)) {
                $dias[] = $d;
            }
            if (count($dias) >= 3) {
                break;
            }
        }
    }
    $p['residentes'][$id]['runtime']['trabajo_dias'] = array_slice($dias, 0, 3);
    $p['residentes'][$id]['runtime']['trabajo_hora_inicio'] = $ini;
    $p['residentes'][$id]['runtime']['trabajo_hora_fin'] = $ini + 2;
}

// 1) empleado = exactamente 3 días × 2h
$p = partidaBase();
$id = (string) array_key_first($p['residentes']);
TrabajoHorario::asegurarHorario($p, $id);
$rt = $p['residentes'][$id]['runtime'];
ok(is_array($rt['trabajo_dias'] ?? null) && count($rt['trabajo_dias']) === 3, 'empleado: 3 dias trabajo');
ok((int) ($rt['trabajo_hora_fin'] ?? 0) - (int) ($rt['trabajo_hora_inicio'] ?? 0) === 2, 'empleado: jornada 2h');

$bloques = 0;
for ($d = 1; $d <= 7; $d++) {
    $agenda = AgendaEngine::resolverDia($p, $id, $d);
    for ($h = 0; $h < 24; $h++) {
        if (($agenda['slots'][$h]['tipo'] ?? '') === 'trabajo') {
            $bloques++;
        }
    }
}
ok($bloques === 6, 'empleado: 3 dias x 2h en la semana');

// 2) desempleado = 0 horas de trabajo
$p = partidaBase();
$id = (string) array_key_first($p['residentes']);
$p['residentes'][$id]['runtime']['ocupacion'] = 'desempleado';
TrabajoHorario::limpiarHorario($p['residentes'][$id]['runtime']);
$trabajoSlots = 0;
for ($h = 0; $h < 24; $h++) {
    $slot = AgendaEngine::resolverDia($p, $id, 1)['slots'][$h];
    if (($slot['tipo'] ?? '') === 'trabajo') {
        $trabajoSlots++;
    }
}
ok($trabajoSlots === 0, 'desempleado: sin bloques trabajo');

// 3) perder trabajo libera agenda
$p = partidaBase();
$id = (string) array_key_first($p['residentes']);
fijarHorario($p, $id, ['lunes', 'martes', 'miercoles'], 14);
$store = (new Catalog($root))->store();
$cal = CalibracionConfig::load($root);
$antes = AgendaEngine::estaDisponible($p, $id, 1, 14);
ok(!($antes['disponible'] ?? true), 'antes de perder: hora laboral ocupada');
AcontecimientoDiario::ejecutar($p, 'perder_trabajo', [$id], $store, $cal);
$despues = AgendaEngine::estaDisponible($p, $id, 1, 14);
ok($despues['disponible'] ?? false, 'tras perder trabajo: hora liberada');

// 4) encontrar trabajo crea horario
$p = partidaBase();
$id = (string) array_key_first($p['residentes']);
$p['residentes'][$id]['runtime']['ocupacion'] = 'desempleado';
TrabajoHorario::limpiarHorario($p['residentes'][$id]['runtime']);
$r = AcontecimientoDiario::ejecutar($p, 'encontrar_trabajo', [$id], $store, $cal);
ok($r['ok'] ?? false, 'encontrar_trabajo ejecuta');
$rt2 = $p['residentes'][$id]['runtime'];
ok(TrabajoHorario::empleado($rt2['ocupacion'] ?? null), 'encontrar_trabajo asigna ocupacion');
ok(is_array($rt2['trabajo_dias'] ?? null) && count($rt2['trabajo_dias']) === 3, 'encontrar_trabajo crea 3 dias');

// 5) trabaja manana -> plan que termina >23 rechazado
$p = partidaBase();
$id = (string) array_key_first($p['residentes']);
fijarHorario($p, $id, ['jueves'], 10);
// miercoles dia 3 si dia 1 es lunes
ok(
    !(AgendaEngine::estaDisponibleIntervalo($p, $id, 3, 22, 2, true)['disponible'] ?? true),
    'trabaja manana: 22-00 rechazado'
);
ok(
    AgendaEngine::estaDisponibleIntervalo($p, $id, 3, 21, 2, true)['disponible'] ?? false,
    'trabaja manana: 21-23 aceptado'
);

// 6) libra manana -> sueño no impide plan nocturno del jugador
$p = partidaBase();
$id = (string) array_key_first($p['residentes']);
$p['residentes'][$id]['runtime']['ocupacion'] = 'jubilado';
TrabajoHorario::limpiarHorario($p['residentes'][$id]['runtime']);
ok(
    DisponibilidadEngine::franjaValida($p, [$id], 1, 22, 'lug_discoteca', 2),
    'libra manana: discoteca 22h valida pese a sueño'
);

exit($failures > 0 ? 1 : 0);
