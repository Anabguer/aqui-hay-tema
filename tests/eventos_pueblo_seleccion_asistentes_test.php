<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EventosPuebloAnuncioEngine;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
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
        'rng' => ['seed' => 'evt-sel', 'state' => 1],
        'meta' => ['seed' => 'evt-sel'],
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

$st = planificarSeed();
ok($st > 0, '1 evento autonomo se programa sin Celestine');

$p = planificarPartida($st);
$ev = eventoDePartida($p);
ok($ev !== null, 'evento programado existe');
$evtId = (string) ($ev['id'] ?? '');
ok($evtId !== '', 'evento tiene id');
ok(EventosPuebloEngine::seleccionEstado($ev) === 'pendiente_asistentes', '2 estado pendiente_asistentes');
ok((string) ($ev['encuentro_id'] ?? '') === '', '2 sin encuentro antes de seleccion');
ok(count($ev['participantes'] ?? []) === 0, '2 sin asistentes fijados al programar');

$vista = EventosPuebloEngine::vistaProximoEvento($p, $catalog);
ok(($vista['preset_organizar']['modo'] ?? '') === 'evento_pueblo', '15 preset modo evento_pueblo');
ok(($vista['pendiente_seleccion'] ?? false) === true, '2 disponible para seleccionar');
ok(($vista['cta_label'] ?? '') === '¿Quién va?', 'CTA elegir quien va');
ok((int) ($vista['aforo'] ?? 0) > 0, 'aforo expuesto en vista');

$eleg = EventosPuebloEngine::vecinosElegibles($p, $evtId, $cal, $catalog);
ok(($eleg['ok'] ?? false) === true, 'elegibles ok');
$libres = array_values(array_filter($eleg['vecinos'] ?? [], static fn($v) => is_array($v) && ($v['elegible'] ?? false)));
ok(count($libres) >= 3, 'hay vecinos elegibles');

$aforo = EventosPuebloEngine::aforoEvento($p, $ev, null, $catalog);
ok($aforo >= 3, 'aforo canonico >= min');

// 3-5 aforo distinto de max=2
ok($aforo >= 3, '3 aforo bingo permite al menos 3');
$stClub = planificarSeed('club_lectura');
if ($stClub > 0) {
    $pClub = planificarPartida($stClub, 'club_lectura');
    $evClub = eventoDePartida($pClub);
    $aforoClub = EventosPuebloEngine::aforoEvento($pClub, $evClub, null, $catalog);
    ok($aforoClub >= 3 && $aforoClub <= 5, "4 club aforo en rango ($aforoClub)");
}

// Plan normal max 2
$pNorm = labPartida();
$r3 = PropuestaEncuentroEngine::proponer($pNorm, [A, B, C], 1, 20, 'conocerse', 'lug_cine');
ok(empty($r3['ok']), '14 plan normal 3 rechazado');
ok(($r3['error'] ?? '') === GameError::PARTICIPANTES_EXCESO, '14 PARTICIPANTES_EXCESO plan normal');

// Confirmar seleccion crea encuentro
$sel = array_map(static fn($v) => (string) ($v['id'] ?? ''), array_slice($libres, 0, min(3, $aforo)));
$antesInterv = (int) ($p['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
$rConf = EventosPuebloEngine::confirmarAsistentes($p, $evtId, $sel, $cal, $catalog);
ok($rConf['ok'] ?? false, 'confirmar asistentes ok: ' . ($rConf['error'] ?? ''));
ok((string) ($rConf['evento_id'] ?? '') === $evtId, '10 mismo evento_id');
$encId = (string) ($rConf['encuentro_id'] ?? '');
ok($encId !== '', 'encuentro creado tras confirmar');

$enc = null;
foreach (EncuentroEngine::list($p) as $e) {
    if (($e['id'] ?? '') === $encId) {
        $enc = $e;
    }
}
ok($enc !== null, '12 encuentro existe');
ok(($enc['intencion'] ?? '') === 'evento_pueblo', 'encuentro evento_pueblo');
ok(($enc['intencion'] ?? '') !== 'celeste_organizado', '11 no celeste_organizado');
ok(count($enc['participantes'] ?? []) === count($sel), '12 ejecuta con asistentes confirmados');

$despuesInterv = (int) ($p['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
ok($antesInterv === $despuesInterv, 'confirmar no consume intervencion Celestine');

// Idempotencia
$rIdem = EventosPuebloEngine::confirmarAsistentes($p, $evtId, $sel, $cal, $catalog);
ok($rIdem['ok'] ?? false, 'idempotencia ok');
ok(!empty($rIdem['idempotente']), 'idempotente flag');

// 6 no supera aforo
$over = EventosPuebloEngine::confirmarAsistentes($p, $evtId, array_merge($sel, [D, E, F]), $cal, $catalog);
ok(empty($over['ok']), '6 rechaza superar aforo manual');

// 7 vecino no elegible (ocupar agenda)
$pOcc = planificarPartida($st);
$evOcc = eventoDePartida($pOcc);
$dia = (int) ($evOcc['dia'] ?? 6);
$hora = (int) ($evOcc['hora'] ?? 18);
EncuentroEngine::programar($pOcc, [D, E], $dia, $hora, 'conocerse', 'lug_bingo', null, null, false);
$bad = EventosPuebloEngine::confirmarAsistentes($pOcc, (string) $evOcc['id'], [A, B, D], $cal, $catalog);
ok(empty($bad['ok']) && ($bad['error'] ?? '') === 'vecino_no_elegible', '7 vecino ocupado no elegible');

// 8 no duplicar en request
$pDup = planificarPartida($st);
$evDup = eventoDePartida($pDup);
$elegDup = EventosPuebloEngine::vecinosElegibles($pDup, (string) $evDup['id'], $cal, $catalog);
$idsDup = array_map(static fn($v) => (string) $v['id'], array_slice(array_filter($elegDup['vecinos'] ?? [], static fn($x) => ($x['elegible'] ?? false)), 0, 3));
$idsDup[] = $idsDup[0];
$rDup = EventosPuebloEngine::confirmarAsistentes($pDup, (string) $evDup['id'], $idsDup, $cal, $catalog);
ok($rDup['ok'] ?? false, '8 dedup en confirmacion acepta unicos');

// 9 save/load simulado
$nSave = count(EventosPuebloEngine::participantesCanon($p, eventoDePartida($p) ?? $ev));
$pReload = $p;
$nReload = count(EventosPuebloEngine::participantesCanon($pReload, eventoDePartida($pReload) ?? $ev));
ok($nSave === $nReload && $nSave >= 3, '9 save/load conserva seleccion');

// Anuncio sin asistentes al programar
$pAn = planificarPartida($st);
$anuncios = 0;
foreach ($pAn['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'anuncio_evento_pueblo') {
        $anuncios++;
    }
}
ok($anuncios === 1, '13 anuncio tras programar sin asistentes');

// 13 cierre tras lifecycle
$pCierre = $p;
$encCierre = $enc;
$diaFin = (int) ($encCierre['dia'] ?? 5);
$horaFin = (int) ($encCierre['hora'] ?? 18) + max(1, (int) ($encCierre['duracion_horas'] ?? 2));
while ($horaFin >= 24) {
    $horaFin -= 24;
    $diaFin++;
}
$pCierre['reloj'] = ['dia_pueblo' => $diaFin, 'hora_actual' => $horaFin];
EncuentroLifecycle::sincronizarConReloj($pCierre, null, $catalog);
$cierres = 0;
foreach ($pCierre['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'cierre_evento_pueblo') {
        $cierres++;
    }
}
ok($cierres === 1, '13 cierre mensajito tras evento');

echo $fail === 0 ? "\nOK eventos_pueblo_seleccion_asistentes_test\n" : "\nFAIL eventos_pueblo_seleccion_asistentes_test ($fail)\n";
exit($fail === 0 ? 0 : 1);
