<?php
declare(strict_types=1);

/**
 * Pack inicial eventos pueblo (6 tipos) + activacion global — validacion focal.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ComplejoCatalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\LugaresCanonicos;
use AquiHayTema\Engine\MensajitoColectivoEngine;
use AquiHayTema\Engine\PartidaService;
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
const G = 'gema';
const H = 'hugo';
const I = 'ines';
const J = 'jorge';
const K = 'kiko';
const L = 'lola';

/** @param list<string> $lugares @return array<string, mixed> */
function labPartida(array $lugares, int $residentes = 4): array
{
    $pool = [
        A => 'Ana', B => 'Bruno', C => 'Carla', D => 'David',
        E => 'Elena', F => 'Fran', G => 'Gema', H => 'Hugo',
        I => 'Ines', J => 'Jorge', K => 'Kiko', L => 'Lola',
    ];
    $residentesData = [];
    $n = 0;
    foreach ($pool as $id => $nombre) {
        if ($n >= $residentes) {
            break;
        }
        $residentesData[$id] = [
            'identidad_publica' => ['nombre' => $nombre],
            'presencia' => 'residente',
            'runtime' => [],
        ];
        $n++;
    }

    return [
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 8],
        'rng' => ['seed' => 'evt-pack', 'state' => 1],
        'meta' => ['seed' => 'evt-pack'],
        'features' => ['eventos_pueblo_enabled' => true, 'buzon_enabled' => true],
        'residentes' => $residentesData,
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

/** @param list<string> $lugares */
function findSeed(string $eventoId, array $lugares, int $residentes = 12): int
{
    for ($st = 1; $st <= 12000; $st++) {
        $p = labPartida($lugares, $residentes);
        $r = planificarId($p, $eventoId, $st);
        if (!empty($r['ok']) || (($r['resultado'] ?? '') === 'evento_programado')) {
            return $st;
        }
    }
    return -1;
}

$items = EventosPuebloEngine::catalogItems($catalog);
ok(count($items) === 6, '1 catalogo carga 6 eventos');

$ids = [];
foreach ($items as $item) {
    $id = (string) ($item['id'] ?? '');
    ok($id !== '', '2 id no vacio');
    ok(!in_array($id, $ids, true), "2 id unico: $id");
    $ids[] = $id;

    $lugares = is_array($item['lugares'] ?? null) ? $item['lugares'] : [];
    ok($lugares !== [], "3 lugares definidos: $id");
    foreach ($lugares as $lug) {
        $lug = (string) $lug;
        ok(LugaresCanonicos::esCanonico($lug), "3 lugar canonico $lug en $id");
        $dest = ComplejoCatalog::destino($lug);
        ok($dest !== null, "3 destino existe $lug en $id");
    }

    $hMin = (int) ($item['hora_min'] ?? 0);
    $hMax = (int) ($item['hora_max'] ?? 0);
    $dur = max(1, (int) ($item['duracion_horas'] ?? 1));
    ok($hMax >= $hMin, "4 horario coherente: $id");
    $franjaOk = false;
    foreach ($lugares as $lug) {
        for ($h = $hMin; $h <= $hMax - $dur + 1; $h++) {
            if (ComplejoCatalog::estaAbierto((string) $lug, $h)) {
                $franjaOk = true;
                break 2;
            }
        }
    }
    ok($franjaOk, "4 horario compatible con apertura: $id");

    $minP = (int) ($item['participantes_min'] ?? 0);
    $maxP = (int) ($item['participantes_max'] ?? 0);
    ok($minP > 0 && $maxP >= $minP, "6 min<=max: $id");
    foreach ($lugares as $lug) {
        $aforo = (int) (ComplejoCatalog::destino((string) $lug)['aforo'] ?? 0);
        ok($maxP <= $aforo || $aforo <= 0, "5 aforo compatible $id en $lug (max=$maxP aforo=$aforo)");
    }
}

$required = [
    'noche_bingo',
    'partido_futbol_benefico',
    'sesion_cine_comunitaria',
    'tardeo_en_el_bar',
    'clase_abierta_gimnasio',
    'club_lectura',
];
foreach ($required as $rid) {
    ok(in_array($rid, $ids, true), "pack incluye $rid");
}

// 7) Seleccion ponderada alcanza todos los ids (simulacion corta)
$seen = [];
for ($i = 1; $i <= 500; $i++) {
    $rng = new RngService('pack-peso-' . $i);
    $def = EventosPuebloEngine::elegirItemCatalogo($items, $rng);
    if ($def !== null) {
        $seen[(string) ($def['id'] ?? '')] = true;
    }
}
foreach ($required as $rid) {
    ok(isset($seen[$rid]), "7 peso alcanza $rid en 500 rolls");
}

$allLugares = LugaresCanonicos::todos();

// 8-9) Bingo y futbol siguen OK
$stBingo = findSeed('noche_bingo', $allLugares);
ok($stBingo > 0, '8 bingo programa');
$stFut = findSeed('partido_futbol_benefico', $allLugares);
ok($stFut > 0, '9 futbol programa');

// 10-11) Evento pequeno (club) y grande (futbol)
$stClub = findSeed('club_lectura', $allLugares);
ok($stClub > 0, '10 club_lectura (pequeno) programa');
$pClub = labPartida($allLugares, 12);
planificarId($pClub, 'club_lectura', $stClub);
$aforoClub = (int) ($pClub['eventos_pueblo']['programados'][0]['aforo'] ?? 0);
ok($aforoClub >= 3 && $aforoClub <= 5, "10 aforo club en rango ($aforoClub)");

$pFut = labPartida($allLugares, 12);
planificarId($pFut, 'partido_futbol_benefico', $stFut);
$aforoFut = (int) ($pFut['eventos_pueblo']['programados'][0]['aforo'] ?? 0);
ok($aforoFut >= 4 && $aforoFut <= 12, "11 aforo futbol en rango ($aforoFut)");

// 12-14) B2/B3 con evento nuevo (cine)
$stCine = findSeed('sesion_cine_comunitaria', $allLugares);
ok($stCine > 0, 'evento nuevo cine programa');
$pCine = labPartida($allLugares, 12);
$rCine = planificarId($pCine, 'sesion_cine_comunitaria', $stCine);
ok(!empty($rCine['ok']), 'cine planificado');
$evCine = $rCine['evento'] ?? [];
$evtCineId = (string) ($evCine['id'] ?? '');
$elegCine = EventosPuebloEngine::vecinosElegibles($pCine, $evtCineId, $cal, $catalog);
$minCine = (int) ($elegCine['participantes_min'] ?? 3);
$idsCine = [];
foreach ($elegCine['vecinos'] ?? [] as $row) {
    if (!is_array($row) || !($row['elegible'] ?? false)) {
        continue;
    }
    $idsCine[] = (string) ($row['id'] ?? '');
    if (count($idsCine) >= $minCine) {
        break;
    }
}
$rConfCine = EventosPuebloEngine::confirmarAsistentes($pCine, $evtCineId, $idsCine, $cal, $catalog);
ok(($rConfCine['ok'] ?? false) && count($idsCine) >= $minCine, 'cine confirmar asistentes: ' . ($rConfCine['error'] ?? '') . ' n=' . count($idsCine));
$encId = (string) (EventosPuebloEngine::buscarProgramadoPorId($pCine, $evtCineId)['encuentro_id'] ?? '');

$anuncios = 0;
foreach ($pCine['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'anuncio_evento_pueblo') {
        $anuncios++;
        $txt = (string) ($m['texto'] ?? '');
        ok(stripos($txt, 'bingo') === false, '12 anuncio cine sin bingo hardcoded');
        ok(stripos($txt, 'cine') !== false || stripos($txt, 'sesión') !== false || stripos($txt, 'sesion') !== false, '12 anuncio menciona evento');
    }
}
ok($anuncios === 1, '12 un anuncio B2');

$vista = EventosPuebloEngine::vistaProximoEvento($pCine, $catalog);
ok(($vista['catalogo_id'] ?? '') === 'sesion_cine_comunitaria', '14 B3 catalogo cine');
ok(($vista['icono'] ?? '') === '🎬', '14 B3 icono cine');

$evCineRow = EventosPuebloEngine::buscarProgramadoPorId($pCine, $evtCineId);
$encId = (string) ($evCineRow['encuentro_id'] ?? '');
// B2 cierre generico
$encCine = null;
if ($encId !== '') {
    foreach ($pCine['encuentros'] as $enc) {
        if (($enc['id'] ?? '') === $encId) {
            $encCine = $enc;
            break;
        }
    }
}
if ($encCine !== null) {
    $diaFin = (int) ($encCine['dia'] ?? 5);
    $horaFin = (int) ($encCine['hora'] ?? 17) + max(1, (int) ($encCine['duracion_horas'] ?? 3));
    while ($horaFin >= 24) {
        $horaFin -= 24;
        $diaFin++;
    }
    $pCine['reloj'] = ['dia_pueblo' => $diaFin, 'hora_actual' => $horaFin];
    EncuentroLifecycle::sincronizarConReloj($pCine, null, $catalog);
}
$cierres = 0;
foreach ($pCine['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'cierre_evento_pueblo') {
        $cierres++;
        ok(stripos((string) ($m['texto'] ?? ''), 'cine') !== false || stripos((string) ($m['texto'] ?? ''), 'sesión') !== false || stripos((string) ($m['texto'] ?? ''), 'sesion') !== false, '13 cierre menciona evento');
    }
}
ok($cierres === 1, '13 un cierre B2');

// 15) F4 acepta id del catalogo
$stBar = findSeed('tardeo_en_el_bar', $allLugares);
ok($stBar > 0, '15 tardeo programa para F4');
$pF4 = labPartida($allLugares, 12);
$pF4['features']['buzon_enabled'] = true;
$msgId = 'msg_f4_bar';
BuzonEngine::crear($pF4, [
    'id' => $msgId,
    'de_persona' => A,
    'texto' => '¿Organizamos un tardeo?',
    'familia_mensajito' => 'f_colectivo',
    'datos_familia' => [
        'evento_catalogo_id' => 'tardeo_en_el_bar',
        'evento_nombre' => 'Tardeo en el bar',
    ],
    'acciones' => ['aceptar_evento', 'declinar_evento'],
    'hilo_id' => $msgId,
    'estado' => 'pendiente',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    '_placeholder_contenido' => false,
]);
$pF4['rng']['state'] = $stBar;
$rF4 = MensajitoColectivoEngine::aceptar($pF4, $msgId, $root);
ok(($rF4['ok'] ?? false) || (($rF4['error'] ?? '') === 'no_se_pudo_programar'), '15 F4 acepta tardeo_en_el_bar');
if ($rF4['ok'] ?? false) {
    ok(($pF4['eventos_pueblo']['programados'][0]['catalogo_id'] ?? '') === 'tardeo_en_el_bar', '15 F4 planifica evento real');
}

$pInv = labPartida($allLugares, 12);
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
ok(($rInv['error'] ?? '') === 'evento_catalogo_invalido', '15 F4 rechaza id invalido');

// 16) max_activos
$pCup = labPartida($allLugares, 12);
planificarId($pCup, 'noche_bingo', $stBingo);
$rCup = planificarId($pCup, 'club_lectura', $stClub);
ok(($rCup['resultado'] ?? '') === 'gate_cupo_activos', '16 max_activos bloquea segundo evento');

// 17) sin duplicados mismo catalogo
$pDup = labPartida($allLugares, 12);
planificarId($pDup, 'clase_abierta_gimnasio', findSeed('clase_abierta_gimnasio', $allLugares));
$rDup = planificarId($pDup, 'clase_abierta_gimnasio', findSeed('clase_abierta_gimnasio', $allLugares) + 1);
ok(in_array((string) ($rDup['resultado'] ?? ''), ['gate_evento_activo', 'gate_cupo_activos'], true), '17 no duplica mismo evento activo');

// 18) Partida nueva sin override — requiere activo global en calibracion
$activoGlobal = (bool) CalibracionConfig::get($cal, 'eventos_pueblo.activo', false);
if ($activoGlobal) {
    $svc = new PartidaService($root);
    $nueva = $svc->nuevaPartida('debug_v0', 'pack-global-test-' . time());
    ok(!FeatureConfig::isEnabled($nueva, 'eventos_pueblo_enabled'), '18 partida nueva sin override Neni');
    ok(EventosPuebloEngine::activa($nueva, $cal), '18 eventos activos por calibracion global');
    $maxAct = (int) CalibracionConfig::get($cal, 'eventos_pueblo.max_activos', 1);
    ok($maxAct === 1, '18 max_activos sigue en 1');
} else {
    echo "SKIP: 18 activacion global (calibracion eventos_pueblo.activo=false hasta commit 2)\n";
}

echo $fail === 0 ? "\nOK eventos_pueblo_pack_inicial_test\n" : "\nFAIL eventos_pueblo_pack_inicial_test ($fail)\n";
exit($fail === 0 ? 0 : 1);
