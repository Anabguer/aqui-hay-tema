<?php
declare(strict_types=1);

// B1 — Núcleo eventos del pueblo (MVP noche de bingo).

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\RngService;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$GLOBALS['cal'] = $cal;
$GLOBALS['catalog'] = $catalog;

const A = 'ana';
const B = 'bruno';
const C = 'carla';
const D = 'david';

function labPartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 11],
        'rng' => ['seed' => 'b1-bingo', 'state' => 1],
        'meta' => ['seed' => 'b1-bingo'],
        'features' => ['eventos_pueblo_enabled' => true],
        'residentes' => [
            A => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            B => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
            C => ['identidad_publica' => ['nombre' => 'Carla'], 'presencia' => 'residente', 'runtime' => []],
            D => ['identidad_publica' => ['nombre' => 'David'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => [
            'lugares_desbloqueados' => ['lug_cafeteria', 'lug_bingo'],
            'intervenciones_organizadas_usadas_hoy' => 0,
            'intervenciones_organizadas_max_dia' => 1,
        ],
        'encuentros' => [],
    ];
}

function planificar(array &$p, int $state = 0): array
{
    if ($state > 0) {
        $p['rng']['state'] = $state;
    }
    return EventosPuebloEngine::planificar(
        $p,
        'noche_bingo',
        $GLOBALS['cal'],
        RngService::fromPartida($p),
        $GLOBALS['catalog']
    );
}

function findEstado(int $wantState): int
{
    for ($st = 1; $st <= 5000; $st++) {
        $p = labPartida();
        $r = planificar($p, $st);
        if (str_starts_with((string) ($r['resultado'] ?? ''), 'evento_programado')) {
            return $st;
        }
    }
    return -1;
}

// 1) Catálogo carga bingo
$item = EventosPuebloEngine::catalogItem($catalog, 'noche_bingo');
ok($item !== null && ($item['id'] ?? '') === 'noche_bingo', '1 catálogo carga noche_bingo');
ok(($item['nombre'] ?? '') === 'Noche de bingo', '1 nombre del evento');

// 2) Programa evento futuro válido
$stOk = findEstado(0);
ok($stOk > 0, '2 existe seed RNG con evento programado');
$p = labPartida();
$p['rng']['state'] = $stOk;
$r = planificar($p);
ok(str_starts_with((string) ($r['resultado'] ?? ''), 'evento_programado'), '2 programa evento futuro');
$ev = $r['evento'] ?? null;
ok(is_array($ev) && ($ev['catalogo_id'] ?? '') === 'noche_bingo', '2 fila eventos_pueblo creada');
ok(((int) ($ev['dia'] ?? 0)) > 5 || ((int) ($ev['hora'] ?? 0)) > 11, '2 franja futura');

// 3) No duplica evento activo
$rDup = planificar($p);
ok(($rDup['resultado'] ?? '') === 'gate_evento_activo', '3 no duplica evento activo');

// 4) RNG reproducible
$p4a = labPartida();
$p4b = labPartida();
$p4a['rng']['state'] = $stOk;
$p4b['rng']['state'] = $stOk;
$r4a = planificar($p4a);
$r4b = planificar($p4b);
ok(($r4a['evento']['dia'] ?? null) === ($r4b['evento']['dia'] ?? null), '4 mismo día con mismo RNG');
ok(($r4a['evento']['hora'] ?? null) === ($r4b['evento']['hora'] ?? null), '4 misma hora con mismo RNG');

// 5) Tras confirmar asistentes: encuentro evento_pueblo
$selIds = array_slice(array_keys($p['residentes']), 0, 3);
EventosPuebloEngine::confirmarAsistentes($p, (string) ($ev['id'] ?? ''), $selIds, $cal, $catalog);
$enc = null;
foreach (($p['encuentros'] ?? []) as $e) {
    if (($e['intencion'] ?? '') === 'evento_pueblo') {
        $enc = $e;
    }
}
ok($enc !== null, '5 encuentro evento_pueblo existe');
ok(count($enc['participantes'] ?? []) >= 3, '5 al menos 3 participantes');
ok(count($enc['participantes'] ?? []) <= 8, '5 no excede max del catálogo');

// 6) Imposibilidad limpia — lugar del evento no operativo
$p6 = labPartida();
$p6['celeste']['lugares_desbloqueados'] = ['lug_cafeteria'];
$r6 = planificar($p6, $stOk);
ok(($r6['resultado'] ?? '') === 'gate_sin_lugar', '6 sin lug_bingo operativo falla limpio (' . ($r6['resultado'] ?? '?') . ')');
ok(count($p6['eventos_pueblo']['programados'] ?? []) === 0, '6 sin fila programada corrupta');
// 6b) Agenda imposible — residentes insuficientes
$p6b = labPartida();
unset($p6b['residentes'][C], $p6b['residentes'][D]);
$r6b = planificar($p6b, $stOk);
ok(($r6b['resultado'] ?? '') === 'participantes_insuficientes', '6b participantes insuficientes sin corrupto');

// 7) Encuentro multi-participante canónico
ok(count($enc['participantes'] ?? []) >= 3, '7 encuentro multi-participante');

// 8) No consume Celestine
$p8 = labPartida();
$p8['celeste']['intervenciones_organizadas_usadas_hoy'] = 1;
$p8['rng']['state'] = $stOk;
$antes = (int) $p8['celeste']['intervenciones_organizadas_usadas_hoy'];
planificar($p8);
$despues = (int) $p8['celeste']['intervenciones_organizadas_usadas_hoy'];
ok($antes === $despues, '8 no incrementa intervenciones Celestine');

// 9) Intención evento_pueblo
ok(($enc['intencion'] ?? '') === 'evento_pueblo', '9 intencion evento_pueblo');
ok(($enc['actividad'] ?? '') === 'noche_bingo', '9 actividad catalogo_id');

// 10) Lifecycle normal (con asistentes confirmados)
$p10 = labPartida();
$p10['rng']['state'] = $stOk;
$r10 = planificar($p10);
$ev10 = $r10['evento'] ?? null;
if (is_array($ev10)) {
    EventosPuebloEngine::confirmarAsistentes(
        $p10,
        (string) ($ev10['id'] ?? ''),
        array_slice(array_keys($p10['residentes']), 0, 3),
        $cal,
        $catalog
    );
}
$enc10 = null;
foreach (($p10['encuentros'] ?? []) as $e) {
    if (($e['intencion'] ?? '') === 'evento_pueblo') {
        $enc10 = $e;
    }
}
if ($enc10 !== null) {
    $diaFin = (int) ($enc10['dia'] ?? 5);
    $horaFin = (int) ($enc10['hora'] ?? 18) + max(1, (int) ($enc10['duracion_horas'] ?? 2));
    while ($horaFin >= 24) {
        $horaFin -= 24;
        $diaFin++;
    }
    $p10['reloj'] = ['dia_pueblo' => $diaFin, 'hora_actual' => $horaFin];
    $sync = EncuentroLifecycle::sincronizarConReloj($p10, null, $catalog);
    $terminado = false;
    foreach (($p10['encuentros'] ?? []) as $e) {
        if (($e['id'] ?? '') === ($enc10['id'] ?? '') && ($e['estado'] ?? '') === 'terminado') {
            $terminado = is_array($e['resultado'] ?? null);
        }
    }
    ok($terminado, '10 lifecycle termina con resultado');
    ok(($sync['resueltos'] ?? 0) >= 1, '10 EncuentroLifecycle resuelve');
}

// 11) proximoEvento para B3
$p11 = labPartida();
$p11['rng']['state'] = $stOk;
planificar($p11);
$prox = EventosPuebloEngine::proximoEvento($p11, $catalog);
ok($prox !== null, '11 proximoEvento devuelve fila');
ok(($prox['catalogo_id'] ?? '') === 'noche_bingo', '11 proximo catalogo_id');
ok(($prox['estado'] ?? '') === 'programado', '11 proximo estado');
ok(($prox['participantes_n'] ?? 0) === 0 || ($prox['seleccion_estado'] ?? '') === 'pendiente_asistentes', '11 proximo sin participantes hasta confirmar');

// 12) Fallo limpio sin estado corrupto
$p12 = labPartida();
unset($p12['residentes'][C], $p12['residentes'][D]);
$p12['rng']['state'] = 1;
$r12 = planificar($p12);
ok(!($r12['ok'] ?? false), '12 fallo sin ok cuando no hay suficientes residentes (' . ($r12['resultado'] ?? '?') . ')');
ok(count($p12['eventos_pueblo']['programados'] ?? []) === 0, '12 no deja evento programado corrupto');
ok(isset($p12['eventos_pueblo']['log']) && $p12['eventos_pueblo']['log'] !== [], '12 deja traza en log');

echo $fail === 0 ? "\nOK eventos_pueblo_bingo_test\n" : "\nFAIL eventos_pueblo_bingo_test ($fail)\n";
exit($fail === 0 ? 0 : 1);
