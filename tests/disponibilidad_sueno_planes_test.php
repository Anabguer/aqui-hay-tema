<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\LugarAtributos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ResidenteRuntime;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function partidaBase(): array
{
    $p = [
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10, 'minuto_actual' => 0, 'dia_semana_base' => 'lunes'],
        'residentes' => [],
        'encuentros' => [],
        'celeste' => [],
        'npc_autonomo' => ['planes_pendientes' => []],
    ];
    $r = ResidenteRuntime::crearPlaceholderDev(1);
    $r['runtime']['ocupacion'] = 'jubilado';
    $p['residentes'][$r['catalog_id']] = $r;
    $r2 = ResidenteRuntime::crearPlaceholderDev(2);
    $r2['runtime']['ocupacion'] = 'jubilado';
    $p['residentes'][$r2['catalog_id']] = $r2;
    return $p;
}

// A) sueño 23–07, discoteca 22–00 → disponible
$p = partidaBase();
$ids = array_keys($p['residentes']);
$a = $ids[0];
$b = $ids[1];
ok(
    DisponibilidadEngine::franjaValida($p, [$a, $b], 1, 22, 'lug_discoteca'),
    'A: discoteca 22h válida pese a sueño 23h'
);
$slots = DisponibilidadEngine::slotsCompatibles($p, [$a, $b], 'conocerse', 1, 10, 1, 48, null, 'lug_discoteca');
$horas = array_map(static fn($s) => (int) ($s['hora'] ?? -1), $slots['slots'] ?? []);
ok(in_array(22, $horas, true), 'A: slots_compatibles ofrece 22h discoteca');

// B) sueño 23–07, plan 01–03 → no disponible si trabaja al día siguiente
$p = partidaBase();
$a = (string) array_key_first($p['residentes']);
$p['residentes'][$a]['runtime']['ocupacion'] = 'oficina';
$p['residentes'][$a]['runtime']['trabajo_dias'] = ['martes', 'miercoles', 'jueves'];
$p['residentes'][$a]['runtime']['trabajo_hora_inicio'] = 10;
$p['residentes'][$a]['runtime']['trabajo_hora_fin'] = 12;
$disp01 = AgendaEngine::estaDisponible($p, $a, 1, 1);
ok(!($disp01['disponible'] ?? true), 'B: hora 01h bloqueada por sueño al inicio (agenda estricta)');
ok(
    !DisponibilidadEngine::franjaValida($p, [$a], 1, 1, 'lug_discoteca'),
    'B: plan 01h inválido con trabajo mañana'
);

// C) plan 22–00 pero otro encuentro a las 23 → no disponible
$p = partidaBase();
$p['encuentros'][] = [
    'id' => 'enc_test_23',
    'tipo' => 'conocerse',
    'participantes' => [$a],
    'lugar' => 'lug_cafeteria',
    'hora' => 23,
    'dia' => 1,
    'duracion_horas' => 1,
    'estado' => 'programado',
    'reserva_agenda' => ['tipo' => 'encuentro'],
];
ok(
    !DisponibilidadEngine::franjaValida($p, [$a], 1, 22, 'lug_discoteca', 2),
    'C: discoteca 22h inválida con encuentro duro a las 23'
);
ok(
    EncuentroEngine::hayConflictoHorario($p, [$a], 1, 22, 2),
    'C: conflicto horario detectado 22–00 vs encuentro 23h'
);

// D) plan que cruza medianoche ocupa ambos días
$p = partidaBase();
$p['encuentros'][] = [
    'id' => 'enc_noche',
    'tipo' => 'conocerse',
    'participantes' => [$a],
    'lugar' => 'lug_bar',
    'hora' => 23,
    'dia' => 1,
    'duracion_horas' => 2,
    'estado' => 'programado',
    'reserva_agenda' => ['tipo' => 'encuentro'],
];
$agendaD1 = AgendaEngine::resolverDia($p, $a, 1);
ok($agendaD1['slots'][23]['ocupado'] ?? false, 'D: encuentro ocupa 23h día 1');
$agendaD2 = AgendaEngine::resolverDia($p, $a, 2);
ok($agendaD2['slots'][0]['ocupado'] ?? false, 'D: encuentro ocupa 0h día 2 tras medianoche');
ok(LugarAtributos::ocupaHora($p['encuentros'][0], 2, 0), 'D: ocupaHora detecta spillover día 2');

// E) actividad diurna intacta
$p = partidaBase();
ok(
    DisponibilidadEngine::franjaValida($p, [$a, $b], 1, 19, 'lug_cafeteria'),
    'E: cafeteria 19h sigue válida'
);
$slotsDia = DisponibilidadEngine::slotsCompatibles($p, [$a, $b], 'conocerse', 1, 10, 1, 24, null, 'lug_cafeteria');
ok(($slotsDia['ok'] ?? false) && count($slotsDia['slots'] ?? []) > 0, 'E: slots diurnos cafeteria disponibles');

// F) bar/cine nocturnos antes del sueño
$p = partidaBase();
ok(
    DisponibilidadEngine::franjaValida($p, [$a], 1, 22, 'lug_bar'),
    'F: bar 22h válido (cruza sueño habitual)'
);
ok(
    DisponibilidadEngine::franjaValida($p, [$a], 1, 22, 'lug_cine'),
    'F: cine 22h válido (3h hasta medianoche con sueño tolerado)'
);

exit($failures > 0 ? 1 : 0);
