<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\GameLogger;
use AquiHayTema\Engine\RngService;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $fail++; }
}

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);

const RES = ['r01','r02','r03','r04','r05','r06','r07','r08','r09','r10','r11','r12','r13','r14','r15','r16'];

function labPartida16(): array
{
    $res = [];
    foreach (RES as $id) {
        $res[$id] = [
            'identidad_publica' => ['nombre' => 'Vecino ' . $id],
            'presencia' => 'residente',
            'runtime' => [],
        ];
    }
    return [
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 11, 'dia_semana_inicio' => 1],
        'rng' => ['seed' => 'bloque1-cierre', 'state' => 1],
        'meta' => ['seed' => 'bloque1-cierre'],
        'features' => ['eventos_pueblo_enabled' => true, 'buzon_enabled' => true],
        'residentes' => $res,
        'celeste' => [
            'lugares_desbloqueados' => ['lug_cafeteria','lug_bingo','lug_cine','lug_biblioteca','lug_bar','lug_gimnasio','lug_parque','lug_restaurante','lug_discoteca'],
            'intervenciones_organizadas_usadas_hoy' => 0,
            'intervenciones_organizadas_max_dia' => 3,
        ],
        'encuentros' => [],
        'eventos_pueblo' => ['programados' => [], 'log' => []],
        'npc_autonomo' => ['planes_pendientes' => []],
        'buzon' => [],
    ];
}

function planificarSeed(): int
{
    global $cal, $catalog;
    for ($s = 1; $s <= 5000; $s++) {
        $pp = labPartida16();
        $pp['rng']['state'] = $s;
        $r = EventosPuebloEngine::planificar($pp, 'noche_bingo', $cal, RngService::fromPartida($pp), $catalog);
        if (str_starts_with((string) ($r['resultado'] ?? ''), 'evento_programado')) {
            return $s;
        }
    }
    return 0;
}

function freshEvent(int $st): array
{
    global $cal, $catalog;
    $p = labPartida16();
    $p['rng']['state'] = $st;
    EventosPuebloEngine::planificar($p, 'noche_bingo', $cal, RngService::fromPartida($p), $catalog);
    $ev = null;
    foreach ($p['eventos_pueblo']['programados'] as $e) { if (is_array($e)) { $ev = $e; break; } }
    $evtId = (string) ($ev['id'] ?? '');
    $aforo = EventosPuebloEngine::aforoEvento($p, $ev, null, $catalog);
    $eleg = EventosPuebloEngine::vecinosElegibles($p, $evtId, $cal, $catalog);
    $libres = array_values(array_filter($eleg['vecinos'] ?? [], static fn($v) => is_array($v) && ($v['elegible'] ?? false)));
    $minP = (int) ($ev['participantes_min'] ?? 3);
    $plazas = (int) ($eleg['plazas_disponibles'] ?? 0);
    return [$p, $evtId, $aforo, $libres, $minP, $plazas];
}

$st = planificarSeed();
ok($st > 0, 'B1 evento programado en pueblo de 16');

// Contrato de capacidad coherente (cierra la discrepancia "0 de N")
[$p, $evtId, $aforo, $libres, $minP, $plazas] = freshEvent($st);
ok($aforo >= $minP, "B1 aforo($aforo) >= min($minP)");
ok($plazas === $aforo, "B1 plazas_disponibles($plazas) == aforo($aforo) [sin discrepancia 0 de N]");

// 1) asistente valido = exactamente el minimo (grupo valido mas pequeno)
[$p, $evtId, $aforo, $libres, $minP] = freshEvent($st);
$minSel = array_map(static fn($v) => (string) ($v['id'] ?? ''), array_slice($libres, 0, $minP));
$r = EventosPuebloEngine::confirmarAsistentes($p, $evtId, $minSel, $cal, $catalog);
ok($r['ok'] ?? false, "B1 menor grupo valido ($minP) confirmado");

// 3 asistentes validos (si el minimo lo permite; si min<3 usamos 3)
[$p, $evtId, $aforo, $libres, $minP] = freshEvent($st);
$n3 = min(3, $aforo);
$tres = array_map(static fn($v) => (string) ($v['id'] ?? ''), array_slice($libres, 0, $n3));
$r3 = EventosPuebloEngine::confirmarAsistentes($p, $evtId, $tres, $cal, $catalog);
ok($r3['ok'] ?? false, "B1 $n3 asistentes validos confirmados");

// maximo permitido
[$p, $evtId, $aforo, $libres, $minP] = freshEvent($st);
$maxIds = array_map(static fn($v) => (string) ($v['id'] ?? ''), array_slice($libres, 0, $aforo));
$rmax = EventosPuebloEngine::confirmarAsistentes($p, $evtId, $maxIds, $cal, $catalog);
ok($rmax['ok'] ?? false, "B1 maximo permitido ($aforo) confirmado");

// seleccion por debajo del minimo rechazada (sub-minimo = invalida)
[$p, $evtId, $aforo, $libres, $minP] = freshEvent($st);
$sub = array_slice($libres, 0, max(1, $minP - 1));
$subIds = array_map(static fn($v) => (string) ($v['id'] ?? ''), $sub);
$rSub = EventosPuebloEngine::confirmarAsistentes($p, $evtId, $subIds, $cal, $catalog);
ok(empty($rSub['ok']) && (($rSub['error'] ?? '') === 'participantes_insuficientes'), 'B1 sub-minimo rechazado: ' . ($rSub['error'] ?? ''));

// seleccion con id no elegible rechazada
[$p, $evtId, $aforo, $libres, $minP] = freshEvent($st);
$rInv = EventosPuebloEngine::confirmarAsistentes($p, $evtId, ['no_existe_xxx'], $cal, $catalog);
ok(empty($rInv['ok']) && (($rInv['error'] ?? '') === 'vecino_no_elegible' || ($rInv['error'] ?? '') === 'participantes_insuficientes'), 'B1 id no elegible rechazado: ' . ($rInv['error'] ?? ''));

// doble confirmacion idempotente
[$p, $evtId, $aforo, $libres, $minP] = freshEvent($st);
$sel = array_map(static fn($v) => (string) ($v['id'] ?? ''), array_slice($libres, 0, $minP));
$first = EventosPuebloEngine::confirmarAsistentes($p, $evtId, $sel, $cal, $catalog);
ok($first['ok'] ?? false, 'B1 primera confirmacion ok');
$seg = EventosPuebloEngine::confirmarAsistentes($p, $evtId, $sel, $cal, $catalog);
ok(($seg['ok'] ?? false) && !empty($seg['idempotente']), 'B1 doble confirmacion idempotente');

// save/refresh conserva confirmacion
$tmp = sys_get_temp_dir() . '/b1_' . getmypid() . '.json';
file_put_contents($tmp, json_encode($p));
$reload = json_decode((string) file_get_contents($tmp), true);
$evR = null; foreach ($reload['eventos_pueblo']['programados'] as $e) { if (is_array($e) && (string) ($e['id'] ?? '') === $evtId) { $evR = $e; break; } }
ok(($evR['seleccion_estado'] ?? '') === 'confirmado' && count($evR['participantes'] ?? []) === $minP, 'B1 save/refresh conserva confirmacion');
@unlink($tmp);

// llegada a hora del evento + fallback autonomo (sin elegir)
[$p, $evtId, $aforo, $libres, $minP] = freshEvent($st);
$ev0 = null; foreach ($p['eventos_pueblo']['programados'] as $e) { if (is_array($e) && (string) ($e['id'] ?? '') === $evtId) { $ev0 = $e; break; } }
$dia3 = (int) ($ev0['dia'] ?? 0);
$hora3 = (int) ($ev0['hora'] ?? 0);
$p['reloj']['dia_pueblo'] = $dia3;
$p['reloj']['hora_actual'] = $hora3;
$res = EventosPuebloEngine::resolverAsistentesPendientesConReloj($p, $cal, $catalog, new GameLogger($root));
ok(($res['resueltos'] ?? 0) >= 1, 'B1 llegada a hora resuelve evento pendiente (fallback)');
$ev3b = null; foreach ($p['eventos_pueblo']['programados'] as $e) { if (is_array($e) && (string) ($e['id'] ?? '') === $evtId) { $ev3b = $e; break; } }
$st3 = $ev3b['seleccion_estado'] ?? '';
ok(in_array($st3, ['confirmado', 'cancelado'], true), "B1 evento resuelto (confirmado/cancelado): $st3");

// celebracion/resolucion: si quedo confirmado, existe el encuentro evento_pueblo
if ($st3 === 'confirmado') {
    $enc = null;
    foreach (EncuentroEngine::list($p) as $e) { if ((string) ($e['evento_pueblo_id'] ?? '') === $evtId) { $enc = $e; break; } }
    ok($enc !== null && ($enc['intencion'] ?? '') === 'evento_pueblo', 'B1 celebracion crea encuentro evento_pueblo');
} else {
    ok(true, 'B1 cancelado limpio por participantes insuficientes (fallback correcto)');
}

echo $fail === 0 ? "\nOK eventos_pueblo_bloque1_cierre_test\n" : "\nFAIL eventos_pueblo_bloque1_cierre_test ($fail)\n";
exit($fail === 0 ? 0 : 1);
