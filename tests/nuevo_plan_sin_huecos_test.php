<?php
declare(strict_types=1);
/**
 * Reproduce y valida fix Nuevo Plan sin huecos — escenario día 1 ~19:00, encuentros activos.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\ComplejoCatalog;
use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\ResidenteRuntime;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function mkPartidaConEncuentros(): array
{
    $partida = [
        'reloj' => [
            'dia_pueblo' => 1,
            'hora_actual' => 19,
            'minuto_actual' => 0,
            'dia_semana_base' => 'viernes',
            'fecha_ancla' => '2026-08-28',
            'zona' => 'Europe/Madrid',
        ],
        'residentes' => [],
        'encuentros' => [],
        'celeste' => [],
        'npc_autonomo' => ['planes_pendientes' => []],
    ];
    $ids = [];
    for ($i = 1; $i <= 3; $i++) {
        $r = ResidenteRuntime::crearPlaceholderDev($i);
        $r['presencia'] = 'residente';
        $r['runtime']['ocupacion'] = 'oficina';
        $partida['residentes'][(string) $r['catalog_id']] = $r;
        $ids[] = (string) $r['catalog_id'];
    }
    [$a, $b, $c] = $ids;
    $partida['encuentros'][] = [
        'id' => 'enc_1',
        'tipo' => 'conocerse',
        'participantes' => [$a, $b],
        'lugar' => 'lug_cafeteria',
        'hora' => 18,
        'dia' => 1,
        'duracion_horas' => 2,
        'estado' => 'en_curso',
        'intencion' => 'celeste_organizado',
    ];
    $partida['encuentros'][] = [
        'id' => 'enc_2',
        'tipo' => 'individual',
        'participantes' => [$c],
        'lugar' => 'lug_parque',
        'hora' => 17,
        'dia' => 1,
        'duracion_horas' => 3,
        'estado' => 'en_curso',
        'intencion' => 'celeste_organizado',
    ];
    return [$partida, $a, $b, $c];
}

[$partida, $a, $b, $c] = mkPartidaConEncuentros();
$parejaLibre = [$b, $c];
$lugar = 'lug_cafeteria';

ok(!Reloj::esFuturo($partida['reloj'], 1, 19), '19:00 en punto no es futura (HORA_PASADA estricta)');
ok(!ComplejoCatalog::estaAbierto($lugar, 20), 'cafetería cierra a las 20h');

// Antes del fix UI: max_dias=1 dejaba hoy sin huecos en cafetería
$rUiViejo = DisponibilidadEngine::slotsCompatibles(
    $partida,
    $parejaLibre,
    'conocerse',
    1,
    null,
    1,
    48,
    null,
    $lugar
);
$slotsHoyViejo = array_filter($rUiViejo['slots'] ?? [], static fn($s) => (int) ($s['dia'] ?? 0) === 1);
ok(count($slotsHoyViejo) === 0, 'cafetería hoy a las 19h: sin huecos (lugar cierra + encuentros)');

// Fix UI: horizonte 7 días encuentra mañana u otra franja
$rUiNuevo = DisponibilidadEngine::slotsCompatibles(
    $partida,
    $parejaLibre,
    'conocerse',
    1,
    null,
    7,
    48,
    null,
    $lugar
);
ok(($rUiNuevo['total'] ?? 0) > 0, 'horizonte 7 días: encuentra hueco futuro');
ok(isset($rUiNuevo['primera_compatible']), 'primera_compatible expuesta para hint UI');

// Día futuro sin desde_hora explícita: empieza a las 0h, no a las 19h del reloj
$rDia2 = DisponibilidadEngine::slotsCompatibles($partida, $parejaLibre, 'conocerse', 2, null, 1, 48, null, $lugar);
$mananaTemprano = array_filter(
    $rDia2['slots'] ?? [],
    static fn($s) => (int) ($s['dia'] ?? 0) === 2 && (int) ($s['hora'] ?? 99) < 12
);
ok(count($mananaTemprano) > 0, 'día 2: busca desde 0h (no hereda hora 19 del reloj)');

// Copy jugador cuando no hay ningún hueco en el horizonte
$partidaBloqueada = $partida;
$partidaBloqueada['encuentros'] = [];
for ($h = 0; $h < 24; $h++) {
    $partidaBloqueada['encuentros'][] = [
        'id' => 'enc_fill_' . $h,
        'tipo' => 'conocerse',
        'participantes' => [$a, $b],
        'lugar' => 'lug_bar',
        'hora' => $h,
        'dia' => 1,
        'duracion_horas' => 1,
        'estado' => 'programado',
    ];
}
$rBloq = DisponibilidadEngine::slotsCompatibles($partidaBloqueada, [$a, $b], 'conocerse', 1, null, 1, 48, null, 'lug_bar');
$ui = $rBloq['diagnostico']['resumen_ui'] ?? '';
ok($ui === DisponibilidadEngine::COPY_SIN_HUECOS_PAREJA, 'copy jugador cuando no hay huecos');
ok(str_contains($rBloq['diagnostico']['resumen'] ?? '', 'Sin horas compatibles'), 'resumen técnico conservado');

// Bar nocturno: pareja libre sí puede hoy tras encuentros en_curso
$rBar = DisponibilidadEngine::slotsCompatibles($partida, $parejaLibre, 'conocerse', 1, null, 1, 48, null, 'lug_bar');
$barHoy = array_filter($rBar['slots'] ?? [], static fn($s) => (int) ($s['dia'] ?? 0) === 1);
ok(count($barHoy) > 0, 'bar hoy: hay huecos para pareja no bloqueada toda la noche');

exit($failures > 0 ? 1 : 0);
