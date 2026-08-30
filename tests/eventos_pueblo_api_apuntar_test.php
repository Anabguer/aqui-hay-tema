<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../src/autoload.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\EventosPuebloHandler;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\RngService;

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$ctx = new ApiContext($root);

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $fail++; }
}

const A = 'ana';
const B = 'bruno';
const C = 'carla';
const D = 'david';
const E = 'elena';
const F = 'fran';
const G = 'gabi';
const H = 'hugo';

function labPartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 11, 'dia_semana_inicio' => 1],
        'rng' => ['seed' => 'evt-api', 'state' => 1],
        'meta' => ['seed' => 'evt-api', 'partida_id' => 'lab_evt_api_' . uniqid('', true)],
        'features' => ['eventos_pueblo_enabled' => true, 'buzon_enabled' => true],
        'residentes' => [
            A => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            B => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
            C => ['identidad_publica' => ['nombre' => 'Carla'], 'presencia' => 'residente', 'runtime' => []],
            D => ['identidad_publica' => ['nombre' => 'David'], 'presencia' => 'residente', 'runtime' => []],
            E => ['identidad_publica' => ['nombre' => 'Elena'], 'presencia' => 'residente', 'runtime' => []],
            F => ['identidad_publica' => ['nombre' => 'Fran'], 'presencia' => 'residente', 'runtime' => []],
            G => ['identidad_publica' => ['nombre' => 'Gabi'], 'presencia' => 'residente', 'runtime' => []],
            H => ['identidad_publica' => ['nombre' => 'Hugo'], 'presencia' => 'residente', 'runtime' => []],
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

// ===== API-LEVEL TRACE: frontend → payload → API → confirmarAsistentes() =====

$st = planificarSeed();
$p = labPartida();
$p['rng']['state'] = $st;
EventosPuebloEngine::planificar($p, 'noche_bingo', $cal, RngService::fromPartida($p), $catalog);
$evtId = '';
foreach ($p['eventos_pueblo']['programados'] as $e) { if (is_array($e)) { $evtId = (string) $e['id']; break; } }
ok($evtId !== '', 'API: evento programado tiene id');

// Elegibles reales (lo que el frontend ahora carga vía evento_pueblo.elegibles)
$elegResp = EventosPuebloHandler::elegibles($ctx, ['evento_pueblo_id' => $evtId], $p);
ok(($elegResp['ok'] ?? false) === true, 'API: elegibles ok');
$libres = array_values(array_filter($elegResp['vecinos'] ?? [], static fn($v) => is_array($v) && ($v['elegible'] ?? false)));
ok(count($libres) >= 3, 'API: hay >=3 elegibles');

// Mínimo válido (3 = participantes_min del catálogo) — el frontend manda exactamente esto
$selMin = array_map(static fn($v) => (string) $v['id'], array_slice($libres, 0, 3));
$rMin = EventosPuebloHandler::apuntar($ctx, ['evento_pueblo_id' => $evtId, 'participantes' => $selMin], $p);
ok(($rMin['ok'] ?? false) === true, 'API: 3 asistentes válidos confirma: ' . ($rMin['error'] ?? ''));
ok(isset($rMin['encuentro_id']) && $rMin['encuentro_id'] !== '', 'API: crea encuentro');
ok(isset($rMin['evento_pueblo']) && (isset($rMin['proximo_evento_pueblo']) || isset($rMin['evento_pueblo'])), 'API: adjunta vistas evento_pueblo/proximo');
ok(isset($rMin['mensaje_ui']) && $rMin['mensaje_ui'] !== '', 'API: mensaje_ui presente');

// Máximo permitido (aforo 8): re-planificar y llenar
$st2 = planificarSeed('noche_bingo');
$p2 = labPartida();
$p2['rng']['state'] = $st2;
EventosPuebloEngine::planificar($p2, 'noche_bingo', $cal, RngService::fromPartida($p2), $catalog);
$evtId2 = '';
foreach ($p2['eventos_pueblo']['programados'] as $e) { if (is_array($e)) { $evtId2 = (string) $e['id']; break; } }
$eleg2 = EventosPuebloHandler::elegibles($ctx, ['evento_pueblo_id' => $evtId2], $p2);
$libres2 = array_values(array_filter($eleg2['vecinos'] ?? [], static fn($v) => is_array($v) && ($v['elegible'] ?? false)));
$aforo2 = EventosPuebloEngine::aforoEvento($p2, end($p2['eventos_pueblo']['programados']), null, $catalog);
$selMax = array_map(static fn($v) => (string) $v['id'], array_slice($libres2, 0, min($aforo2, count($libres2))));
$rMax = EventosPuebloHandler::apuntar($ctx, ['evento_pueblo_id' => $evtId2, 'participantes' => $selMax], $p2);
ok(($rMax['ok'] ?? false) === true, 'API: máximo permitido (' . count($selMax) . ') confirma: ' . ($rMax['error'] ?? ''));

// Selección inválida correctamente rechazada: no-elegible (ocupar agenda de uno)
$pInv = labPartida();
$pInv['rng']['state'] = $st;
EventosPuebloEngine::planificar($pInv, 'noche_bingo', $cal, RngService::fromPartida($pInv), $catalog);
$evtIdInv = '';
foreach ($pInv['eventos_pueblo']['programados'] as $e) { if (is_array($e)) { $evtIdInv = (string) $e['id']; break; } }
$evRow = null;
foreach ($pInv['eventos_pueblo']['programados'] as $e) { if (is_array($e)) { $evRow = $e; break; } }
$diaInv = (int) ($evRow['dia'] ?? 6);
$horaInv = (int) ($evRow['hora'] ?? 18);
\AquiHayTema\Engine\EncuentroEngine::programar($pInv, [D, E], $diaInv, $horaInv, 'conocerse', 'lug_bingo', null, null, false);
$rInv = EventosPuebloHandler::apuntar($ctx, ['evento_pueblo_id' => $evtIdInv, 'participantes' => [A, B, D]], $pInv);
ok(($rInv['ok'] ?? false) === false && ($rInv['error'] ?? '') === 'vecino_no_elegible', 'API: rechaza no-elegible con error vecino_no_elegible');

// Doble confirmación idempotente
$rIdem = EventosPuebloHandler::apuntar($ctx, ['evento_pueblo_id' => $evtId, 'participantes' => $selMin], $p);
ok(($rIdem['ok'] ?? false) === true && !empty($rIdem['idempotente']), 'API: doble confirmación idempotente');

// El mínimo es CONFIG-DRIVEN (no hardcodeado a 2 como planes normales).
// Para noche_bingo (participantes_min=3): 2 asistentes se rechaza, 3 es válido.
// (Cubierto también por el test de 3 asistentes válidos más arriba.)
$pMin = labPartida();
$pMin['rng']['state'] = $st;
EventosPuebloEngine::planificar($pMin, 'noche_bingo', $cal, RngService::fromPartida($pMin), $catalog);
$evtIdMin = '';
foreach ($pMin['eventos_pueblo']['programados'] as $e) { if (is_array($e)) { $evtIdMin = (string) $e['id']; break; } }
$elegMin = EventosPuebloHandler::elegibles($ctx, ['evento_pueblo_id' => $evtIdMin], $pMin);
$libMin = array_values(array_filter($elegMin['vecinos'] ?? [], static fn($v) => is_array($v) && ($v['elegible'] ?? false)));
$rBelow = EventosPuebloHandler::apuntar($ctx, ['evento_pueblo_id' => $evtIdMin, 'participantes' => [(string) $libMin[0]['id'], (string) $libMin[1]['id']]], $pMin);
ok(($rBelow['ok'] ?? false) === false && ($rBelow['error'] ?? '') === 'participantes_insuficientes', 'API: 2 asistentes rechazado (min=3 config-driven, no hardcodeado a 2)');
$fMin = $root . '/data/partidas/' . ($pMin['meta']['partida_id'] ?? '') . '.json';
if (is_file($fMin)) { unlink($fMin); }
$fbMin = $fMin . '.bak';
if (is_file($fbMin)) { unlink($fbMin); }

// Limpieza de archivos de partida LAB escritos por guardar()
foreach ([$p, $p2, $pInv] as $pp) {
    $pid = $pp['meta']['partida_id'] ?? null;
    if ($pid) {
        $f = $root . '/data/partidas/' . $pid . '.json';
        if (is_file($f)) { unlink($f); }
        $fb = $f . '.bak';
        if (is_file($fb)) { unlink($fb); }
    }
}

echo $fail === 0 ? "\nOK eventos_pueblo_api_apuntar_test\n" : "\nFAIL eventos_pueblo_api_apuntar_test ($fail)\n";
exit($fail === 0 ? 0 : 1);
