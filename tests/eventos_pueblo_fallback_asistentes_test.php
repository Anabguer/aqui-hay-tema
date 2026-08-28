<?php
declare(strict_types=1);

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
const E = 'elena';
const F = 'fran';

function labPartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 11, 'dia_semana_inicio' => 1],
        'rng' => ['seed' => 'evt-fb', 'state' => 1],
        'meta' => ['seed' => 'evt-fb'],
        'features' => ['eventos_pueblo_enabled' => true, 'buzon_enabled' => true],
        'residentes' => [
            A => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            B => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
            C => ['identidad_publica' => ['nombre' => 'Carla'], 'presencia' => 'residente', 'runtime' => []],
            D => ['identidad_publica' => ['nombre' => 'David'], 'presencia' => 'residente', 'runtime' => []],
            E => ['identidad_publica' => ['nombre' => 'Elena'], 'presencia' => 'residente', 'runtime' => []],
            F => ['identidad_publica' => ['nombre' => 'Fran'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => [
            'lugares_desbloqueados' => ['lug_cafeteria', 'lug_bingo'],
            'intervenciones_organizadas_usadas_hoy' => 0,
            'intervenciones_organizadas_max_dia' => 1,
        ],
        'encuentros' => [],
        'eventos_pueblo' => ['programados' => [], 'log' => []],
        'buzon' => [],
    ];
}

function planificarSeed(string $catId = 'noche_bingo'): int
{
    global $cal, $catalog;
    for ($st = 1; $st <= 5000; $st++) {
        $p = labPartida();
        $p['rng']['state'] = $st;
        $r = EventosPuebloEngine::planificar($p, $catId, $cal, RngService::fromPartida($p), $catalog);
        if (str_starts_with((string) ($r['resultado'] ?? ''), 'evento_programado')) {
            return $st;
        }
    }

    return 0;
}

function eventoDePartida(array $p): ?array
{
    foreach ($p['eventos_pueblo']['programados'] ?? [] as $ev) {
        if (is_array($ev)) {
            return $ev;
        }
    }

    return null;
}

function planificarPartida(int $st, string $catId = 'noche_bingo'): array
{
    global $cal, $catalog;
    $p = labPartida();
    $p['rng']['state'] = $st;
    EventosPuebloEngine::planificar($p, $catId, $cal, RngService::fromPartida($p), $catalog);

    return $p;
}

function avanzarAHoraEvento(array &$p, array $ev): void
{
    $p['reloj'] = [
        'dia_pueblo' => (int) ($ev['dia'] ?? 5),
        'hora_actual' => (int) ($ev['hora'] ?? 18),
        'dia_semana_inicio' => (int) ($p['reloj']['dia_semana_inicio'] ?? 1),
    ];
}

function contarCierres(array $p): int
{
    $n = 0;
    foreach ($p['buzon'] ?? [] as $m) {
        if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'cierre_evento_pueblo') {
            $n++;
        }
    }

    return $n;
}

$st = planificarSeed();
ok($st > 0, 'seed evento disponible');

// 1 pendiente_asistentes + llega la hora → motor selecciona grupo válido
$p1 = planificarPartida($st);
$ev1 = eventoDePartida($p1);
ok($ev1 !== null, '1 evento programado');
$evtId1 = (string) ($ev1['id'] ?? '');
ok(EventosPuebloEngine::seleccionEstado($ev1) === 'pendiente_asistentes', '1 pendiente antes de hora');
avanzarAHoraEvento($p1, $ev1);
EncuentroLifecycle::sincronizarConReloj($p1, null, $catalog);
$ev1Post = eventoDePartida($p1);
ok($ev1Post !== null, '1 evento sigue en programados');
ok((string) ($ev1Post['id'] ?? '') === $evtId1, '9 mismo evento_id tras fallback');
ok(EventosPuebloEngine::seleccionEstado($ev1Post) === 'confirmado', '1 confirmado tras fallback');
ok(($ev1Post['seleccion_origen'] ?? '') === 'autonomo', '1 origen autonomo');
$parts1 = EventosPuebloEngine::participantesCanon($p1, $ev1Post);
$minP = (int) ($ev1Post['participantes_min'] ?? 3);
ok(count($parts1) >= $minP, '4 fallback respeta participantes_min');
$aforo1 = EventosPuebloEngine::aforoEvento($p1, $ev1Post, null, $catalog);
ok(count($parts1) <= $aforo1, '3 fallback respeta aforo');
ok((string) ($ev1Post['encuentro_id'] ?? '') !== '', '1 encuentro creado');

// 2 selección confirmada por Celestine → conserva EXACTAMENTE participantes
$p2 = planificarPartida($st);
$ev2 = eventoDePartida($p2);
$evtId2 = (string) ($ev2['id'] ?? '');
$eleg2 = EventosPuebloEngine::vecinosElegibles($p2, $evtId2, $cal, $catalog);
$libres2 = array_values(array_filter($eleg2['vecinos'] ?? [], static fn($v) => is_array($v) && ($v['elegible'] ?? false)));
$sel2 = array_map(static fn($v) => (string) ($v['id'] ?? ''), array_slice($libres2, 0, 3));
EventosPuebloEngine::confirmarAsistentes($p2, $evtId2, $sel2, $cal, $catalog);
avanzarAHoraEvento($p2, $ev2);
EncuentroLifecycle::sincronizarConReloj($p2, null, $catalog);
$ev2Post = eventoDePartida($p2);
$parts2 = EventosPuebloEngine::participantesCanon($p2, $ev2Post ?? $ev2);
sort($sel2);
$parts2Sorted = $parts2;
sort($parts2Sorted);
ok($parts2Sorted === $sel2, '2 conserva exactamente seleccion Celestine');
ok(($ev2Post['seleccion_origen'] ?? 'celestine') === 'celestine', '2 origen celestine no sobrescrito');

// 5 fallback no usa vecinos ocupados/no elegibles
$p5 = planificarPartida($st);
$ev5 = eventoDePartida($p5);
$dia5 = (int) ($ev5['dia'] ?? 5);
$hora5 = (int) ($ev5['hora'] ?? 18);
$dur5 = max(1, (int) ($ev5['duracion_horas'] ?? 2));
EncuentroEngine::programar($p5, [D, E], $dia5, $hora5, 'conocerse', (string) ($ev5['lugar'] ?? 'lug_bingo'), null, null, false);
avanzarAHoraEvento($p5, $ev5);
EncuentroLifecycle::sincronizarConReloj($p5, null, $catalog);
$ev5Post = eventoDePartida($p5);
$parts5 = EventosPuebloEngine::participantesCanon($p5, $ev5Post ?? $ev5);
ok(!in_array(D, $parts5, true) && !in_array(E, $parts5, true), '5 no incluye vecinos ocupados');
foreach ($parts5 as $pid) {
    ok(($p5['residentes'][$pid]['presencia'] ?? '') === 'residente', '5 solo residentes activos');
}

// 6 insuficientes elegibles → cancelación limpia
$p6 = planificarPartida($st);
$ev6 = eventoDePartida($p6);
$dia6 = (int) ($ev6['dia'] ?? 5);
$hora6 = (int) ($ev6['hora'] ?? 18);
EncuentroEngine::programar($p6, [A, B], $dia6, $hora6, 'conocerse', 'lug_cafeteria', null, null, false);
EncuentroEngine::programar($p6, [C, D], $dia6, $hora6, 'conocerse', 'lug_cafeteria', null, null, false);
EncuentroEngine::programar($p6, [E, F], $dia6, $hora6, 'conocerse', 'lug_cafeteria', null, null, false);
avanzarAHoraEvento($p6, $ev6);
EncuentroLifecycle::sincronizarConReloj($p6, null, $catalog);
$ev6Post = eventoDePartida($p6);
ok(($ev6Post['estado'] ?? '') === 'cancelado', '6 estado cancelado');
ok(($ev6Post['motivo_cancelacion'] ?? '') === 'participantes_insuficientes', '6 motivo participantes_insuficientes');
ok((string) ($ev6Post['encuentro_id'] ?? '') === '', '6 sin encuentro huérfano');

// 7 evento cancelado no genera cierre narrativo como si hubiera ocurrido
ok(contarCierres($p6) === 0, '7 sin cierre narrativo en cancelacion');

// 8 save/load antes de la hora conserva pendiente y luego ejecuta fallback
$p8 = planificarPartida($st);
$ev8 = eventoDePartida($p8);
$evtId8 = (string) ($ev8['id'] ?? '');
ok(EventosPuebloEngine::seleccionEstado($ev8) === 'pendiente_asistentes', '8 pendiente antes de hora');
$p8Reload = json_decode(json_encode($p8), true, 512, JSON_THROW_ON_ERROR);
$ev8Reload = eventoDePartida($p8Reload);
ok(EventosPuebloEngine::seleccionEstado($ev8Reload) === 'pendiente_asistentes', '8 save/load conserva pendiente');
avanzarAHoraEvento($p8Reload, $ev8Reload);
EncuentroLifecycle::sincronizarConReloj($p8Reload, null, $catalog);
$ev8Post = eventoDePartida($p8Reload);
ok((string) ($ev8Post['id'] ?? '') === $evtId8, '8 mismo evento_id tras reload+fallback');
ok(EventosPuebloEngine::seleccionEstado($ev8Post) === 'confirmado', '8 fallback tras reload');

// 10 no convertir en celeste_organizado
$encId1 = (string) ($ev1Post['encuentro_id'] ?? '');
$encFb = null;
foreach (EncuentroEngine::list($p1) as $e) {
    if (($e['id'] ?? '') === $encId1) {
        $encFb = $e;
    }
}
ok($encFb !== null, '10 encuentro fallback existe');
ok(($encFb['intencion'] ?? '') === 'evento_pueblo', '10 intencion evento_pueblo');
ok(($encFb['intencion'] ?? '') !== 'celeste_organizado', '10 no celeste_organizado');

echo $fail === 0 ? "\nOK eventos_pueblo_fallback_asistentes_test\n" : "\nFAIL eventos_pueblo_fallback_asistentes_test ($fail)\n";
exit($fail === 0 ? 0 : 1);
