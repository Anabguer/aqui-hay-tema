<?php
declare(strict_types=1);

/**
 * R3 — Día de misiones ignorado (23/08/2026).
 * Caducada individual = 0 Vida. Día con paquete normal y 0 cumplidas = -2 único.
 * Config: calibracion_vida.json -> misiones_diarias.vida_dia_ignorado.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorMisionesDiarias;
use AquiHayTema\Engine\VidaPuebloEngine;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cal = CalibracionConfig::load($root);
$reloj = new RelojOperations($root);

/** Partida lab post-tutorial: 8 residentes, sin tutorial, paquete día 1 generado. */
function labPartida(string $seed): array
{
    global $cal;
    $rng = new RngService($seed);
    $p = SimuladorMisionesDiarias::partidaLab(8, $rng, $cal);
    unset($p['_lab_misiones_b3']);
    $p['meta']['config_id'] = 'juego_v1';
    $p['features'] = [
        VidaPuebloEngine::FLAG => true,
        MisionDiariaEngine::FLAG => true,
        PeticionPuebloEngine::FLAG => true,
    ];
    VidaPuebloEngine::ensure($p, $cal);
    MisionDiariaEngine::alComenzarDia($p, $cal, $rng);
    return [$p, $rng];
}

function normalesDia(array $p, int $dia): array
{
    return array_values(array_filter(
        $p['misiones_diarias']['items'] ?? [],
        static fn($m) => ($m['familia'] ?? '') !== 'primeros_pasos' && (int) ($m['dia'] ?? 0) === $dia
    ));
}

function rellenarHasta(array &$p, int $dia, int $objetivo): int
{
    $base = count(normalesDia($p, $dia));
    for ($j = $base; $j < $objetivo; $j++) {
        $p['misiones_diarias']['items'][] = [
            'id' => 'mis_test_' . $dia . '_' . $j,
            'plantilla_id' => 'tema_del_dia',
            'familia' => 'tema',
            'dia' => $dia,
            'estado' => MisionDiariaEngine::EST_PENDIENTE,
            'texto' => 'test',
            'params' => ['ok' => true],
            'exigencia' => 10,
            'cuenta_latido' => false,
        ];
    }
    return count(normalesDia($p, $dia));
}

function entradasLedger(array $p, ?string $causa = null): array
{
    $out = [];
    foreach ($p['vida_pueblo']['ledger'] ?? [] as $e) {
        if (!empty($e['lab'])) {
            continue;
        }
        if ($causa !== null && ($e['causa'] ?? '') !== $causa) {
            continue;
        }
        $out[] = $e;
    }
    return $out;
}

// ---------- CASO 1: 3 pendientes + 0 cumplidas -> 3 caducadas + UN único -2 ----------
[$p1] = labPartida('r3-caso1');
ok(rellenarHasta($p1, 1, 3) === 3, '1.setup: 3 normales pendientes día 1');
$n = MisionDiariaEngine::alCerrarDia($p1, 1, $cal);
$cad = count(array_filter(normalesDia($p1, 1), fn($m) => ($m['estado'] ?? '') === MisionDiariaEngine::EST_CADUCADA));
$ig = entradasLedger($p1, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO);
ok($n === 3 && $cad === 3, "1a. 3 caducadas (cerradas={$n}, estado={$cad})");
ok(count($ig) === 1 && (int) $ig[0]['delta'] === -2, '1b. UNA entrada dia_misiones_ignorado -2');
ok(VidaPuebloEngine::valor($p1) === 63, '1c. vida 65 -> 63');
ok(count(entradasLedger($p1, VidaPuebloEngine::CAUSA_MISION_FALLIDA)) === 0, '1d. cero entradas mision_fallida');

// ---------- CASO 2: 3 pendientes + 1 cumplida -> +2, caducadas 0, sin -3 diario ----------
[$p2, $rng2] = labPartida('r3-caso2');
rellenarHasta($p2, 1, 3);
$pends = array_values(array_filter(normalesDia($p2, 1), fn($m) => ($m['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE));
$enc = MisionDiariaEngine::encuentroSinteticoPara($pends[0], $p2);
SimuladorMisionesDiarias::sideEffects($p2, $pends[0], $enc, $cal);
MisionDiariaEngine::onEncuentroCelestine($p2, $enc, $cal, null);
ok(VidaPuebloEngine::valor($p2) === 67, '2a. cumplida +2 (65 -> 67)');
$n2 = MisionDiariaEngine::alCerrarDia($p2, 1, $cal);
$cad2 = count(array_filter(normalesDia($p2, 1), fn($m) => ($m['estado'] ?? '') === MisionDiariaEngine::EST_CADUCADA));
ok($n2 === 2 && $cad2 === 2, "2b. 2 caducadas (cerradas={$n2}, estado={$cad2})");
ok(VidaPuebloEngine::valor($p2) === 67, '2c. caducadas con 0 delta: vida sigue 67');
ok(count(entradasLedger($p2, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO)) === 0, '2d. sin penalización diaria');

// ---------- CASO 3: día sin misiones normales -> 0 ----------
[$p3] = labPartida('r3-caso3');
foreach (($p3['misiones_diarias']['items'] ?? []) as $i => $m) {
    if ((int) ($m['dia'] ?? 0) === 1) {
        unset($p3['misiones_diarias']['items'][$i]);
    }
}
$p3['misiones_diarias']['items'] = array_values($p3['misiones_diarias']['items']);
$v3 = VidaPuebloEngine::valor($p3);
$n3 = MisionDiariaEngine::alCerrarDia($p3, 1, $cal);
ok($n3 === 0 && VidaPuebloEngine::valor($p3) === $v3 && entradasLedger($p3, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO) === [], '3. sin paquete normal: 0 penalización');

// ---------- CASO 4: tutorial abierto -> 0 ----------
$svc = new \AquiHayTema\Engine\PartidaService($root);
$p4 = $svc->nuevaPartida('juego_v1', 'r3-caso4-tutorial');
ok(!empty($p4['tutorial']['activo']) && empty($p4['tutorial']['jugable_completado']), '4.setup: tutorial abierto');
$v4 = VidaPuebloEngine::valor($p4);
$n4 = MisionDiariaEngine::alCerrarDia($p4, (int) ($p4['reloj']['dia_pueblo'] ?? 1), $cal);
ok($n4 === 0 && VidaPuebloEngine::valor($p4) === $v4, '4. tutorial abierto: 0 penalización');
ok(entradasLedger($p4, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO) === [], '4b. ledger limpio en tutorial');

// ---------- CASO 5: cambio de día paso a paso -> aplicación única por día ----------
[$p5] = labPartida('r3-caso5');
rellenarHasta($p5, 1, 3);
$reloj->avanzarPasoAPaso($p5, 24);
$ig5 = entradasLedger($p5, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO);
ok(count($ig5) === 1 && (int) $ig5[0]['delta'] === -2, '5a. paso a paso 24h: un solo -2');
$reloj->avanzarPasoAPaso($p5, 24);
$ig5b = entradasLedger($p5, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO);
ok(count($ig5b) === 2, '5b. segundo día paso a paso: segundo -2 único');
ok(VidaPuebloEngine::valor($p5) >= 57 && VidaPuebloEngine::valor($p5) <= 63, '5c. vida ~65-4 (+/- ruido peticiones)');

// ---------- CASO 6: refresh/save/load -> no recobra el mismo día ----------
[$p6] = labPartida('r3-caso6');
rellenarHasta($p6, 1, 3);
MisionDiariaEngine::alCerrarDia($p6, 1, $cal);
$snap = json_decode(json_encode($p6), true); // round-trip persistencia
$n6 = MisionDiariaEngine::alCerrarDia($snap, 1, $cal);
ok(count(entradasLedger($snap, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO)) === 1, '6a. tras reload: sigue UNA entrada');
ok(VidaPuebloEngine::valor($snap) === VidaPuebloEngine::valor($p6), '6b. vida idéntica tras cerrar dos veces');
ok(MisionDiariaEngine::alCerrarDia($snap, 1, $cal) === 0, '6c. tercera llamada cierra 0');

// ---------- CASO 7: avance multi-día -> cada día con paquete, exactamente una vez ----------
[$p7] = labPartida('r3-caso7');
rellenarHasta($p7, 1, 3);
for ($i = 0; $i < 3; $i++) {
    $reloj->avanzar($p7, 24); // patrón de juego real: una sesión por día
}
$porFuente = [];
$totalDelta = 0;
foreach (entradasLedger($p7, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO) as $e) {
    $f = (string) ($e['fuente_id'] ?? '?');
    $porFuente[$f] = ($porFuente[$f] ?? 0) + 1;
    $totalDelta += (int) $e['delta'];
}
ok(count($porFuente) === 3 && max($porFuente) === 1, '7a. tres días, una entrada cada uno');
ok($totalDelta === -6, '7b. total -6 (3 x -2)');
ok((int) ($p7['reloj']['dia_pueblo'] ?? 0) === 4, '7c. reloj en día 4');

// ---------- CASO 7b: salto multi-día -> días SIN paquete propio no cobran (CASO D) ----------
[$p7b] = labPartida('r3-caso7b');
rellenarHasta($p7b, 1, 3);
$reloj->avanzar($p7b, 72);
$conPaquete = [];
for ($d = 1; $d <= 3; $d++) {
    if (count(normalesDia($p7b, $d)) > 0) {
        $conPaquete[] = $d;
    }
}
$cobrados = array_map(
    static fn($e) => (int) str_replace('misiones_dia_', '', (string) ($e['fuente_id'] ?? '')),
    entradasLedger($p7b, VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO)
);
sort($cobrados);
sort($conPaquete);
ok($cobrados === $conPaquete && count($cobrados) > 0,
    '7d. salto 72h: cobran solo días con paquete, una vez c/u (' . implode(',', $cobrados) . ')');

// ---------- CASO 8: ledger diferenciado, nunca 3x(-3) ----------
$todos = entradasLedger($p1);
$fallidas = entradasLedger($p1, VidaPuebloEngine::CAUSA_MISION_FALLIDA);
ok(count($todos) === 1 && count($fallidas) === 0, '8. cierre día ignorado produce solo la entrada diaria');
ok(($todos[0]['causa'] ?? '') === 'dia_misiones_ignorado', '8b. causa legible: dia_misiones_ignorado');
ok(strpos((string) ($todos[0]['fuente_id'] ?? ''), 'misiones_dia_') === 0, '8c. fuente_id apunta al día');

// ---------- PETICIONES INTACTAS (control) ----------
[$pp] = labPartida('r3-peticiones');
$pet = \AquiHayTema\Engine\PeticionEngine::crear($pp, 'lab_r01', 'lugar', [
    'schema_b4' => true,
    'plantilla_id' => 'ir_al_lugar',
    'familia' => 'lugar',
    'peso' => \AquiHayTema\Engine\PeticionEsquemas::PESO_FACIL,
    'texto' => 'Quiero ir al parque.',
    'plazo_horas' => 1,
    'vence_dia' => 1,
    'vence_hora' => 11,
    'params' => ['lugar_id' => 'lug_parque'],
    'hecho' => 'Ir al parque.',
], null);
if (!empty($pet['ok'])) {
    $reloj->avanzar($pp, 2); // cruza vence (1:11): caducidad real por reloj de juego
    PeticionPuebloEngine::tick($pp, $cal, new RngService('r3-pet-tick'));
    $fallos = entradasLedger($pp, VidaPuebloEngine::CAUSA_PETICION_CADUCADA);
    ok(count($fallos) === 1 && (int) $fallos[0]['delta'] === -1, 'pet control: peticion_caducada -1 intacto');
} else {
    ok(false, 'pet control: no se pudo crear petición');
}

echo $failures > 0 ? "\nFALLOS: {$failures}\n" : "\nOK misiones_r3_dia_ignorado\n";
exit($failures > 0 ? 1 : 0);
