<?php
declare(strict_types=1);

// B2 — Anuncio del evento del pueblo mediante Mensajito.

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EventosPuebloAnuncioEngine;
use AquiHayTema\Engine\EventosPuebloCierreEngine;
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
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 11, 'dia_semana_inicio' => 1],
        'rng' => ['seed' => 'b2-anuncio', 'state' => 1],
        'meta' => ['seed' => 'b2-anuncio'],
        'features' => ['eventos_pueblo_enabled' => true, 'buzon_enabled' => true],
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
        'buzon' => [],
        'canales_publicados' => [],
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

function findEstadoOk(): int
{
    for ($st = 1; $st <= 5000; $st++) {
        $p = labPartida();
        $r = planificar($p, $st);
        if (str_starts_with((string) ($r['resultado'] ?? ''), 'evento_programado')) {
            return $st;
        }
    }
    return 0;
}

$stOk = findEstadoOk();
ok($stOk > 0, '1 encuentra seed que programa bingo (B1)');

$p = labPartida();
$encAntes = count(EncuentroEngine::list($p));
$evtAntes = count($p['eventos_pueblo']['programados'] ?? []);
$buzAntes = count($p['buzon'] ?? []);
$r = planificar($p, $stOk);
ok(str_starts_with((string) ($r['resultado'] ?? ''), 'evento_programado'), '1 programa evento real');
ok(count(EncuentroEngine::list($p)) === $encAntes, '5 no crea encuentro hasta confirmar asistentes');
ok(count($p['eventos_pueblo']['programados'] ?? []) === $evtAntes + 1, '5 una sola fila eventos_pueblo');

$msg = null;
foreach ($p['buzon'] ?? [] as $m) {
    if (is_array($m) && ($m['tipo'] ?? '') === EventosPuebloAnuncioEngine::TIPO_EVENTO) {
        $msg = $m;
        break;
    }
}
ok($msg !== null, '2 genera mensajito B2 tras programar');
ok(count($p['buzon'] ?? []) === $buzAntes + 1, '2 un solo mensajito nuevo');

$evt = is_array($r['evento'] ?? null) ? $r['evento'] : [];
$evtId = (string) ($evt['id'] ?? '');
$datos = is_array($msg['datos_familia'] ?? null) ? $msg['datos_familia'] : [];
ok(($datos['evento_pueblo_id'] ?? '') === $evtId, '3 referencia evento_pueblo_id correcto');
ok(($datos['evento_pueblo_catalogo_id'] ?? '') === 'noche_bingo', '3 catalogo_id noche_bingo');
ok(($datos['encuentro_id'] ?? '') === (string) ($evt['encuentro_id'] ?? ''), '3 encuentro_id enlazado');
ok((int) ($datos['dia'] ?? 0) === (int) ($evt['dia'] ?? 0), '3 dia coherente');
ok((int) ($datos['hora'] ?? 0) === (int) ($evt['hora'] ?? 0), '3 hora coherente');
ok(($datos['lugar'] ?? '') === (string) ($evt['lugar'] ?? ''), '3 lugar coherente');
ok((int) ($datos['participantes_n'] ?? 0) === 0, '3 anuncio sin asistentes previos a Celestine');

$texto = trim((string) ($msg['texto'] ?? ''));
ok($texto !== '', '4 copy no vacio');
ok(stripos($texto, 'programada') === false && !preg_match('/\bd[ií]a\s+\d+\s+hora\s+\d+/u', $texto), '4 copy natural (no aviso tecnico)');
ok(stripos($texto, 'bingo') !== false || stripos($texto, 'Celestine') !== false, '4 menciona evento o Celestine');
ok(($msg['estado_decision'] ?? '') === BuzonEngine::DECISION_NO_APLICA, '8 mensajito informativo sin decision pendiente');
ok(($msg['acciones'] ?? null) === [] || $msg['acciones'] === [], '8 sin acciones inventadas');

$interv = (int) ($p['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
ok($interv === 0, '5 no consume intervencion Celestine');

$evtRow = null;
foreach ($p['eventos_pueblo']['programados'] as $ev) {
    if (is_array($ev) && (string) ($ev['id'] ?? '') === $evtId) {
        $evtRow = $ev;
        break;
    }
}
ok($evtRow !== null && !empty($evtRow['anuncio_emitido']), '6 evento marcado anuncio_emitido');

$dup = EventosPuebloAnuncioEngine::anunciarTrasProgramar(
    $p,
    $evtRow,
    $catalog,
    $cal,
    RngService::fromPartida($p)
);
ok($dup === null, '6 reanunciar mismo evento no duplica');
ok(count($p['buzon'] ?? []) === $buzAntes + 1, '6 buzon sin duplicado tras reevaluar');

$pSin = labPartida();
$pSin['features']['buzon_enabled'] = false;
planificar($pSin, $stOk);
$sinMsg = false;
foreach ($pSin['buzon'] ?? [] as $m) {
    if (is_array($m) && ($m['tipo'] ?? '') === EventosPuebloAnuncioEngine::TIPO_EVENTO) {
        $sinMsg = true;
    }
}
ok(!$sinMsg, '7 sin buzon no genera anuncio');

$pFake = labPartida();
$fake = null;
EventosPuebloAnuncioEngine::anunciarTrasProgramar(
    $pFake,
    ['id' => 'evt_fake', 'catalogo_id' => 'noche_bingo', 'participantes' => []],
    $catalog,
    $cal,
    RngService::fromPartida($pFake)
);
foreach ($pFake['buzon'] ?? [] as $m) {
    if (is_array($m) && ($m['tipo'] ?? '') === EventosPuebloAnuncioEngine::TIPO_EVENTO) {
        $fake = $m;
    }
}
ok($fake !== null, '7 anuncio con participantes vacios (pendiente Celestine)');

// --- B2 mitad B: cierre post-evento ---
function countCierres(array $p): int
{
    $n = 0;
    foreach ($p['buzon'] ?? [] as $m) {
        if (is_array($m) && ($m['tipo'] ?? '') === EventosPuebloCierreEngine::TIPO_EVENTO) {
            $n++;
        }
    }

    return $n;
}

function findCierre(array $p): ?array
{
    foreach ($p['buzon'] ?? [] as $m) {
        if (is_array($m) && ($m['tipo'] ?? '') === EventosPuebloCierreEngine::TIPO_EVENTO) {
            return $m;
        }
    }

    return null;
}

function avanzarHastaFinEncuentro(array &$p, array $enc): void
{
    $diaFin = (int) ($enc['dia'] ?? 5);
    $horaFin = (int) ($enc['hora'] ?? 18) + max(1, (int) ($enc['duracion_horas'] ?? 2));
    while ($horaFin >= 24) {
        $horaFin -= 24;
        $diaFin++;
    }
    $p['reloj'] = ['dia_pueblo' => $diaFin, 'hora_actual' => $horaFin, 'dia_semana_inicio' => 1];
}

$idsConfirm = array_slice(array_keys($p['residentes']), 0, 3);
EventosPuebloEngine::confirmarAsistentes($p, $evtId, $idsConfirm, $cal, $catalog);
$evt = EventosPuebloEngine::buscarProgramadoPorId($p, $evtId) ?? $evt;

$encProg = null;
foreach (EncuentroEngine::list($p) as $e) {
    if (($e['intencion'] ?? '') === 'evento_pueblo') {
        $encProg = $e;
        break;
    }
}
ok($encProg !== null, 'B2 encuentro evento_pueblo existe');
ok(findCierre($p) === null, 'B2 sin cierre prematuro antes de lifecycle');

$cierresAntes = countCierres($p);
$anuncioId = (string) ($msg['id'] ?? '');
$hiloAnuncio = (string) ($msg['hilo_id'] ?? '');
if ($encProg !== null) {
    avanzarHastaFinEncuentro($p, $encProg);
    EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);
}

$cierre = findCierre($p);
ok($cierre !== null, 'B2 genera mensajito cierre tras lifecycle');
ok(countCierres($p) === $cierresAntes + 1, 'B2 exactamente un mensajito cierre nuevo');

$datosCierre = is_array($cierre['datos_familia'] ?? null) ? $cierre['datos_familia'] : [];
ok(($datosCierre['evento_pueblo_id'] ?? '') === $evtId, 'B2 cierre referencia mismo evento_pueblo_id');
ok(($datosCierre['encuentro_id'] ?? '') === (string) ($evt['encuentro_id'] ?? ''), 'B2 cierre referencia mismo encuentro');
ok(($datosCierre['evento_pueblo_catalogo_id'] ?? '') === 'noche_bingo', 'B2 cierre catalogo_id coherente');
ok(($datosCierre['estado_final'] ?? '') === 'terminado', 'B2 cierre estado_final terminado');
ok(in_array((string) ($datosCierre['tono_experiencia'] ?? ''), ['cancelado', 'celebrado_fuerte', 'celebrado_normal', 'celebrado_tenue', 'ocurrio'], true), 'B2 tono basado en datos reales');

$textoCierre = trim((string) ($cierre['texto'] ?? ''));
ok($textoCierre !== '', 'B2 copy cierre no vacio');
ok(stripos($textoCierre, 'increíble') === false && stripos($textoCierre, 'increible') === false, 'B2 no inventa superlativo no demostrable');

if ($anuncioId !== '') {
    ok((string) ($cierre['mensaje_origen_id'] ?? '') === $anuncioId, 'B2 cierre enlaza anuncio_mensajito_id');
}
if ($hiloAnuncio !== '') {
    ok((string) ($cierre['hilo_id'] ?? '') === $hiloAnuncio, 'B2 cierre comparte hilo con anuncio');
}

$evtRowPost = null;
foreach ($p['eventos_pueblo']['programados'] as $ev) {
    if (is_array($ev) && (string) ($ev['id'] ?? '') === $evtId) {
        $evtRowPost = $ev;
        break;
    }
}
ok($evtRowPost !== null && !empty($evtRowPost['cierre_emitido']), 'B2 evento marcado cierre_emitido');
ok(($evtRowPost['estado_final'] ?? '') === 'terminado', 'B2 fila evento sincroniza estado_final');

$buzTrasCierre = countCierres($p);
EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);
ok(countCierres($p) === $buzTrasCierre, 'B2 reevaluar lifecycle no duplica cierre');
ok(EventosPuebloCierreEngine::yaCerrado($p, $evtId), 'B2 dedup yaCerrado');

// Cancelación: cierre coherente sin celebración
$pCan = labPartida();
planificar($pCan, $stOk);
$encCan = null;
foreach (EncuentroEngine::list($pCan) as $e) {
    if (($e['intencion'] ?? '') === 'evento_pueblo') {
        $encCan = $e;
    }
}
if ($encCan !== null) {
    EncuentroEngine::cancelar($pCan, (string) ($encCan['id'] ?? ''));
    $cierreCan = findCierre($pCan);
    ok($cierreCan !== null, 'B2 cancelado genera cierre');
    $dc = is_array($cierreCan['datos_familia'] ?? null) ? $cierreCan['datos_familia'] : [];
    ok(($dc['estado_final'] ?? '') === 'cancelado', 'B2 cancelado estado_final cancelado');
    ok(($dc['tono_experiencia'] ?? '') === 'cancelado', 'B2 cancelado tono cancelado');
}

echo $fail === 0 ? "\nOK eventos_pueblo_anuncio_mensajito_test\n" : "\nFAIL eventos_pueblo_anuncio_mensajito_test ($fail)\n";
exit($fail > 0 ? 1 : 0);
