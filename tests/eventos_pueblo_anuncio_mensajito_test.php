<?php
declare(strict_types=1);

// B2 — Anuncio del evento del pueblo mediante Mensajito.

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EventosPuebloAnuncioEngine;
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
ok(count(EncuentroEngine::list($p)) === $encAntes + 1, '5 no crea segundo encuentro en planificar');
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
ok((int) ($datos['participantes_n'] ?? 0) >= 3, '3 participantes_n >= min bingo');

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
ok($fake === null, '7 sin participantes no inventa anuncio');

echo $fail === 0 ? "\nOK eventos_pueblo_anuncio_mensajito_test\n" : "\nFAIL eventos_pueblo_anuncio_mensajito_test ($fail)\n";
exit($fail > 0 ? 1 : 0);
