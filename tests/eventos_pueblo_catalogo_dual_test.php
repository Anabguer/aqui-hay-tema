<?php
declare(strict_types=1);

/**
 * Expansion eventos pueblo: catalogo dual (bingo + futbol) sobre motor B1-B3 generico.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\MensajitoColectivoEngine;
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

const A = 'ana';
const B = 'bruno';
const C = 'carla';
const D = 'david';

/**
 * @param list<string> $lugares
 * @return array<string, mixed>
 */
function labPartida(array $lugares): array
{
    return [
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 8],
        'rng' => ['seed' => 'evt-dual', 'state' => 1],
        'meta' => ['seed' => 'evt-dual'],
        'features' => ['eventos_pueblo_enabled' => true, 'buzon_enabled' => true],
        'residentes' => [
            A => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            B => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
            C => ['identidad_publica' => ['nombre' => 'Carla'], 'presencia' => 'residente', 'runtime' => []],
            D => ['identidad_publica' => ['nombre' => 'David'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => [
            'lugares_desbloqueados' => $lugares,
            'intervenciones_organizadas_usadas_hoy' => 0,
            'intervenciones_organizadas_max_dia' => 1,
        ],
        'encuentros' => [],
        'buzon' => [],
    ];
}

function planificarId(array &$p, string $eventoId, int $state = 0): array
{
    global $cal, $catalog;
    if ($state > 0) {
        $p['rng']['state'] = $state;
    }
    return EventosPuebloEngine::planificar($p, $eventoId, $cal, RngService::fromPartida($p), $catalog);
}

function findSeed(string $eventoId, array $lugares): int
{
    for ($st = 1; $st <= 8000; $st++) {
        $p = labPartida($lugares);
        $r = planificarId($p, $eventoId, $st);
        if (!empty($r['ok']) || (($r['resultado'] ?? '') === 'evento_programado')) {
            return $st;
        }
    }
    return -1;
}

// 1) Catalogo dual
$items = EventosPuebloEngine::catalogItems($catalog);
$ids = array_map(static fn($i) => (string) ($i['id'] ?? ''), $items);
ok(in_array('noche_bingo', $ids, true), '1 catalogo incluye noche_bingo');
ok(in_array('partido_futbol_benefico', $ids, true), '1 catalogo incluye partido_futbol_benefico');

// 9) Seleccion ponderada generica
$pSel = labPartida(['lug_bingo', 'lug_parque']);
$pSel['rng']['state'] = 42;
$defSel = EventosPuebloEngine::elegirItemCatalogo($items, RngService::fromPartida($pSel));
ok($defSel !== null && in_array((string) ($defSel['id'] ?? ''), $ids, true), '9 elegirItemCatalogo devuelve id valido');

// Bingo sigue OK
$stBingo = findSeed('noche_bingo', ['lug_cafeteria', 'lug_bingo']);
ok($stBingo > 0, '13 bingo programa con seed');
$pB = labPartida(['lug_cafeteria', 'lug_bingo']);
$pB['rng']['state'] = $stBingo;
$rB = planificarId($pB, 'noche_bingo');
ok(($rB['evento']['catalogo_id'] ?? '') === 'noche_bingo', '13 bingo catalogo_id');
ok(($rB['evento']['lugar'] ?? '') === 'lug_bingo', '13 bingo lugar');

// Futbol programa
$stFut = findSeed('partido_futbol_benefico', ['lug_parque', 'lug_cafeteria']);
ok($stFut > 0, '2 futbol programa con seed');
$pF = labPartida(['lug_parque', 'lug_cafeteria']);
$pF['rng']['state'] = $stFut;
$rF = planificarId($pF, 'partido_futbol_benefico');
$evF = is_array($rF['evento'] ?? null) ? $rF['evento'] : [];
ok(($evF['catalogo_id'] ?? '') === 'partido_futbol_benefico', '2 futbol catalogo_id');
ok(($evF['lugar'] ?? '') === 'lug_parque', '3 futbol usa lug_parque');
$partF = is_array($evF['participantes'] ?? null) ? $evF['participantes'] : [];
ok(count($partF) >= 4 && count($partF) <= 12, '4 participantes min/max futbol (' . count($partF) . ')');
ok((int) ($evF['hora'] ?? 0) >= 9 && (int) ($evF['hora'] ?? 0) <= 12, '3 futbol franja manana');

$encF = null;
foreach ($pF['encuentros'] ?? [] as $e) {
    if (is_array($e) && ($e['intencion'] ?? '') === 'evento_pueblo') {
        $encF = $e;
    }
}
ok($encF !== null, '6 encuentro evento_pueblo futbol');
ok(count($encF['participantes'] ?? []) === count($partF), '5 participantes = encuentro');
$usadas = (int) ($pF['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
ok($usadas === 0, '8 intervencionCeleste=false');

// B2 anuncio unico
$msgsAntes = count($pF['buzon'] ?? []);
$anuncios = 0;
foreach ($pF['buzon'] ?? [] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'anuncio_evento_pueblo') {
        $anuncios++;
        $datos = is_array($m['datos_familia'] ?? null) ? $m['datos_familia'] : [];
        ok(($datos['evento_pueblo_catalogo_id'] ?? '') === 'partido_futbol_benefico', '9 anuncio catalogo futbol');
        ok(strpos((string) ($m['texto'] ?? ''), 'bingo') === false, '9 anuncio no dice bingo');
    }
}
ok($anuncios === 1, '9 exactamente un anuncio B2');

// B3 proximo
$vista = EventosPuebloEngine::vistaProximoEvento($pF, $catalog);
ok($vista !== null, '11 B3 proximo futbol');
ok(($vista['catalogo_id'] ?? '') === 'partido_futbol_benefico', '11 B3 catalogo futbol');
ok(($vista['icono'] ?? '') === '⚽', '11 B3 icono catalogo futbol');
ok(strpos((string) ($vista['nombre_ui'] ?? ''), 'fútbol') !== false || strpos((string) ($vista['nombre_ui'] ?? ''), 'futbol') !== false,
    '11 B3 nombre natural futbol');

// B2 cierre + B3 desaparece
if ($encF !== null) {
    $diaFin = (int) ($encF['dia'] ?? 5);
    $horaFin = (int) ($encF['hora'] ?? 10) + max(1, (int) ($encF['duracion_horas'] ?? 2));
    while ($horaFin >= 24) {
        $horaFin -= 24;
        $diaFin++;
    }
    $pF['reloj'] = ['dia_pueblo' => $diaFin, 'hora_actual' => $horaFin];
    EncuentroLifecycle::sincronizarConReloj($pF, null, $catalog);
    $cierres = 0;
    foreach ($pF['buzon'] ?? [] as $m) {
        if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'cierre_evento_pueblo') {
            $cierres++;
            ok(strpos((string) ($m['texto'] ?? ''), 'bingo') === false, '10 cierre no dice bingo');
        }
    }
    ok($cierres >= 1, '10 al menos un cierre B2');
    ok(EventosPuebloEngine::proximoEvento($pF, $catalog) === null, '12 proximo desaparece tras terminar');
}

// max_activos: segundo evento bloqueado si hay uno activo
$pDup = labPartida(['lug_parque', 'lug_bingo']);
$pDup['rng']['state'] = $stFut;
planificarId($pDup, 'partido_futbol_benefico');
$rDup = planificarId($pDup, 'noche_bingo');
ok(($rDup['resultado'] ?? '') === 'gate_cupo_activos', '14 no duplica evento activo (max_activos)');

// F4 acepta evento_id del catalogo
$pF4 = labPartida(['lug_parque']);
$pF4['features']['buzon_enabled'] = true;
$msgId = 'msg_f4_futbol';
BuzonEngine::crear($pF4, [
    'id' => $msgId,
    'de_persona' => A,
    'texto' => '¿Montamos el partido benéfico?',
    'familia_mensajito' => 'f_colectivo',
    'datos_familia' => [
        'evento_catalogo_id' => 'partido_futbol_benefico',
        'evento_nombre' => 'Partido de fútbol benéfico',
    ],
    'acciones' => ['aceptar_evento', 'declinar_evento'],
    'hilo_id' => $msgId,
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
$pF4['rng']['state'] = $stFut;
$rF4 = MensajitoColectivoEngine::aceptar($pF4, $msgId, $root);
ok(($rF4['ok'] ?? false) || (($rF4['error'] ?? '') === 'no_se_pudo_programar'), '12 F4 aceptar futbol evaluable');
$rF4bad = MensajitoColectivoEngine::aceptar($pF4, 'msg_fake', $root);
ok(!($rF4bad['ok'] ?? true), '12 F4 mensaje inexistente falla');

$pInv = labPartida(['lug_parque']);
BuzonEngine::crear($pInv, [
    'id' => 'msg_inv',
    'de_persona' => A,
    'texto' => 'x',
    'familia_mensajito' => 'f_colectivo',
    'datos_familia' => ['evento_catalogo_id' => 'evento_inventado'],
    'acciones' => ['aceptar_evento'],
    'hilo_id' => 'msg_inv',
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
$rInv = MensajitoColectivoEngine::aceptar($pInv, 'msg_inv', $root);
ok(($rInv['error'] ?? '') === 'evento_catalogo_invalido', '12 F4 sin fallback noche_bingo');

echo $fail === 0 ? "\nOK eventos_pueblo_catalogo_dual_test\n" : "\nFAIL eventos_pueblo_catalogo_dual_test ($fail)\n";
exit($fail > 0 ? 1 : 0);
