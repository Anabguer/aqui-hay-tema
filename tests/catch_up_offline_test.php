<?php
declare(strict_types=1);

/**
 * Catch-up offline: ausencia real → avance de reloj con reglas existentes.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatchUpEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaLifecycle;
use AquiHayTema\Engine\PartidaRepository;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorCatchUpOffline;
use AquiHayTema\Engine\SimuladorMisionesDiarias;
use AquiHayTema\Engine\VidaPuebloEngine;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function partidaCatchUp(string $seed = 'catchup-test'): array
{
    global $cal, $root;
    $rng = new RngService($seed);
    $p = SimuladorMisionesDiarias::partidaLab(8, $rng, $cal);
    unset($p['_lab_misiones_b3']);
    $p['reloj']['dia_en_temporada'] = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    $p['features'] = [
        VidaPuebloEngine::FLAG => true,
        MisionDiariaEngine::FLAG => true,
        CatchUpEngine::FLAG => true,
        'encuentros_enabled' => true,
    ];
    VidaPuebloEngine::ensure($p, $cal);
    MisionDiariaEngine::alComenzarDia($p, $cal, $rng);
    return [$p, $rng];
}

function ledgerIgnorado(array $p): array
{
    $out = [];
    foreach ($p['vida_pueblo']['ledger'] ?? [] as $e) {
        if (($e['causa'] ?? '') === VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO) {
            $out[] = $e;
        }
    }
    return $out;
}

// --- 0 ausencia: nada cambia ---
[$p0] = partidaCatchUp('cu-0');
$ahora0 = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
$p0['reloj']['ultimo_catch_up_iso'] = $ahora0->format(DATE_ATOM);
$v0 = VidaPuebloEngine::valor($p0);
$d0 = (int) $p0['reloj']['dia_pueblo'];
$r0 = CatchUpEngine::ejecutarAlCargar($p0, $root, $cal, null, null, $ahora0);
ok(empty($r0['ejecutado']), 'ausencia 0: no ejecuta');
ok(VidaPuebloEngine::valor($p0) === $v0, 'ausencia 0: vida igual');
ok((int) $p0['reloj']['dia_pueblo'] === $d0, 'ausencia 0: día igual');

// --- catch-up 1 día ---
[$p1] = partidaCatchUp('cu-1d');
$ahora1 = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
$desde1 = $ahora1->sub(new \DateInterval('P1D'));
$p1['reloj']['ultimo_catch_up_iso'] = $desde1->format(DATE_ATOM);
$v1a = VidaPuebloEngine::valor($p1);
CatchUpEngine::ejecutarAlCargar($p1, $root, $cal, null, null, $ahora1);
ok(VidaPuebloEngine::valor($p1) === $v1a - 3, 'catch-up 1d: -3 vida por día ignorado');
ok(count(ledgerIgnorado($p1)) === 1, 'catch-up 1d: una penalización diaria');

// --- catch-up varios días ---
[$p4] = partidaCatchUp('cu-4d');
$ahora4 = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
$p4['reloj']['ultimo_catch_up_iso'] = $ahora4->sub(new \DateInterval('P4D'))->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($p4, $root, $cal, null, null, $ahora4);
ok(count(ledgerIgnorado($p4)) === 4, 'catch-up 4d: 4 penalizaciones');
ok(VidaPuebloEngine::valor($p4) === 65 - 12, 'catch-up 4d: -12 vida');

// --- doble carga idempotente ---
[$p2x] = partidaCatchUp('cu-idem');
$ahora2 = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
$p2x['reloj']['ultimo_catch_up_iso'] = $ahora2->sub(new \DateInterval('P2D'))->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($p2x, $root, $cal, null, null, $ahora2);
$v2 = VidaPuebloEngine::valor($p2x);
$n2 = count(ledgerIgnorado($p2x));
CatchUpEngine::ejecutarAlCargar($p2x, $root, $cal, null, null, $ahora2);
ok(VidaPuebloEngine::valor($p2x) === $v2, 'doble carga: vida no duplica');
ok(count(ledgerIgnorado($p2x)) === $n2, 'doble carga: ledger no duplica');

// --- misiones caducan una sola vez ---
[$pm] = partidaCatchUp('cu-mis');
$ahoraM = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
$pm['reloj']['ultimo_catch_up_iso'] = $ahoraM->sub(new \DateInterval('P1D'))->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($pm, $root, $cal, null, null, $ahoraM);
$cad = array_filter(
    $pm['misiones_diarias']['items'] ?? [],
    static fn($m) => (int) ($m['dia'] ?? 0) === 1 && ($m['estado'] ?? '') === MisionDiariaEngine::EST_CADUCADA
);
ok(count($cad) >= 1, 'misiones día 1 caducadas');
MisionDiariaEngine::alCerrarDia($pm, 1, $cal);
ok(count(ledgerIgnorado($pm)) === 1, 'misiones: alCerrarDia idempotente tras catch-up');

// --- encuentro programado durante ausencia ---
[$pe] = partidaCatchUp('cu-enc');
$pe['reloj']['hora_actual'] = 10;
$pe['reloj']['dia_en_temporada'] = 1;
$encId = 'enc_cu_test';
$pe['encuentros'] = [[
    'id' => $encId,
    'estado' => 'programado',
    'dia' => 1,
    'hora' => 12,
    'duracion_horas' => 2,
    'participantes' => ['lab_r01', 'lab_r02'],
    'lugar' => 'lug_cafeteria',
    'tipo' => 'quedar',
    'intencion' => 'celeste_organizado',
]];
$ahoraE = new \DateTimeImmutable('2026-08-20 18:00:00', new \DateTimeZone('UTC'));
$pe['reloj']['ultimo_catch_up_iso'] = $ahoraE->sub(new \DateInterval('P1D'))->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($pe, $root, $cal, null, null, $ahoraE);
$encFin = null;
foreach ($pe['encuentros'] ?? [] as $enc) {
    if (($enc['id'] ?? '') === $encId) {
        $encFin = $enc;
        break;
    }
}
ok(($encFin['estado'] ?? '') === 'terminado', 'encuentro programado se resuelve offline');
ok(isset($encFin['resultado']), 'encuentro tiene resultado');

// --- timestamp ultimo_catch_up actualizado ---
[$pt] = partidaCatchUp('cu-ts');
$ahoraT = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
$desdeT = $ahoraT->sub(new \DateInterval('P1D'));
$pt['reloj']['ultimo_catch_up_iso'] = $desdeT->format(DATE_ATOM);
CatchUpEngine::ejecutarAlCargar($pt, $root, $cal, null, null, $ahoraT);
$isoFin = (string) ($pt['reloj']['ultimo_catch_up_iso'] ?? '');
ok($isoFin !== '' && $isoFin !== $desdeT->format(DATE_ATOM), 'ultimo_catch_up_iso avanza');

// --- save/load lifecycle ---
$repo = new PartidaRepository($root);
[$ps] = partidaCatchUp('cu-save');
$ps['meta']['partida_id'] = 'part_catchup_test_' . substr(bin2hex(random_bytes(4)), 0, 8);
$ahoraS = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$desdeS = $ahoraS->sub(new \DateInterval('P1D'));
$ps['reloj']['ultimo_catch_up_iso'] = $desdeS->format(DATE_ATOM);
$ps['reloj']['ultima_sesion_iso'] = $desdeS->format(DATE_ATOM);
$vAntesSave = VidaPuebloEngine::valor($ps);
$repo->guardar($ps);
$catalog = new \AquiHayTema\Engine\Catalog($root);
$logger = new \AquiHayTema\Engine\GameLogger($root);
$residentes = new \AquiHayTema\Engine\ResidenteOperations($catalog, $logger);
$lifecycle = new PartidaLifecycle($root, $catalog, $repo, $logger, $residentes);
$loaded = $lifecycle->cargar($ps['meta']['partida_id']);
ok(!empty($loaded['reloj']['catch_up_pendiente']['ejecutado']), 'lifecycle save/load ejecuta catch-up');
ok(VidaPuebloEngine::valor($loaded) === $vAntesSave - 3, 'lifecycle: vida tras 1d ausencia');

// --- ausencia larga no timeout (cap 90d procesa en <5s) ---
[$pL] = partidaCatchUp('cu-long');
$ahoraL = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
$pL['reloj']['ultimo_catch_up_iso'] = $ahoraL->sub(new \DateInterval('P120D'))->format(DATE_ATOM);
$t0 = microtime(true);
CatchUpEngine::ejecutarAlCargar($pL, $root, $cal, null, null, $ahoraL);
$elapsed = microtime(true) - $t0;
ok($elapsed < 30.0, 'ausencia 120d real cap 90d: sin timeout (' . round($elapsed, 2) . 's)');
ok((int) ($pL['reloj']['catch_up_pendiente']['horas_juego_avanzadas'] ?? 0) === 90 * 24, 'cap técnico 90d');

// --- flag apagado: solo plan ---
[$pOff] = partidaCatchUp('cu-off');
$pOff['features'][CatchUpEngine::FLAG] = false;
$pOff['reloj']['ultima_sesion_iso'] = (new \DateTimeImmutable('2026-08-10 12:00:00', new \DateTimeZone('UTC')))->format(DATE_ATOM);
$vOff = VidaPuebloEngine::valor($pOff);
$dOff = (int) $pOff['reloj']['dia_pueblo'];
Reloj::calcularCatchUpPendiente($pOff);
ok(VidaPuebloEngine::valor($pOff) === $vOff, 'flag off: vida no cambia');
ok((int) $pOff['reloj']['dia_pueblo'] === $dOff, 'flag off: reloj no avanza');
ok(($pOff['reloj']['catch_up_pendiente']['plan']['ejecutado'] ?? true) === false, 'flag off: solo plan');

// --- lab tabla mínima ---
$lab = SimuladorCatchUpOffline::ejecutar($root);
ok(isset($lab['tabla']['alta']['7']), 'lab: celda alta 7d');
ok((int) $lab['tabla']['alta']['1']['delta_vida'] === -3, 'lab: 1d alta = -3');
ok((int) $lab['tabla']['media']['7']['delta_vida'] === -21, 'lab: 7d media = -21');
ok(isset($lab['cumple_intencion']['parametro_calibrar_si_no_cumple']), 'lab: evalúa intención');

echo $failures > 0 ? "\nFALLOS: {$failures}\n" : "\nOK catch_up_offline\n";
exit($failures > 0 ? 1 : 0);
