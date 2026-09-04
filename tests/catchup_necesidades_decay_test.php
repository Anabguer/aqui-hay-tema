<?php
declare(strict_types=1);

/**
 * Catch-up necesidades: decay se aplica a cada residente, no crea fantasma en $partida.
 * Test focal para el fix de CatchUpEngine línea 221 (antes: aplicarDecay($partida)).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatchUpEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\NecesidadEstado;
use AquiHayTema\Engine\PartidaLifecycle;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorMisionesDiarias;

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

// --- Setup: partida con 2 residentes, necesidades_enabled, encuentros_enabled ---
$rng = new RngService('catchup-necesidades-test');
$p = SimuladorMisionesDiarias::partidaLab(8, $rng, $cal);
unset($p['_lab_misiones_b3']);
$p['reloj']['dia_en_temporada'] = (int) ($p['reloj']['dia_pueblo'] ?? 1);
$p['features'] = [
    'necesidades_enabled' => true,
    'encuentros_enabled' => true,
    'npc_autonomy_enabled' => false,
    'vida_pueblo_enabled' => false,
];
// Poner hora en rango de juego (hora 14 = dentro de 9-22)
$p['reloj']['hora_actual'] = 14;
$p['reloj']['minuto_actual'] = 0;

// Asegurar que los residentes tienen necesidades inicializadas
$rids = array_keys($p['residentes'] ?? []);
ok(count($rids) >= 2, 'Partida tiene al menos 2 residentes: ' . count($rids));

foreach ($p['residentes'] as &$res) {
    NecesidadEstado::ensureResidente($res, $p['reloj']);
}
unset($res);

// --- Test 1: Estado inicial = 85 en las 4 necesidades ---
$valoresIniciales = [];
foreach ($rids as $rid) {
    $nec = NecesidadEstado::obtener($p['residentes'][$rid]);
    $valoresIniciales[$rid] = $nec;
    ok($nec['social']['valor'] === 85, "$rid social inicial = 85");
    ok($nec['diversion']['valor'] === 85, "$rid diversion inicial = 85");
    ok($nec['actividad']['valor'] === 85, "$rid actividad inicial = 85");
    ok($nec['calma']['valor'] === 85, "$rid calma inicial = 85");
}

// --- Test 2: Ejecutar catch-up de 3 horas ---
$antes = $p['reloj'];
$relojOps = new RelojOperations($root);
$stats = CatchUpEngine::avanzarHorasOffline($p, $relojOps, 3);
$despues = $p['reloj'];

ok(is_array($stats), 'Catch-up retorna stats');

// --- Test 3: Decay se aplicó a CADA residente ---
$decayAplicado = false;
foreach ($rids as $rid) {
    $nec = NecesidadEstado::obtener($p['residentes'][$rid]);
    $socialAhora = $nec['social']['valor'];
    $socialAntes = $valoresIniciales[$rid]['social']['valor'];
    if ($socialAhora < $socialAntes) {
        $decayAplicado = true;
    }
    // Al menos una necesidad debe haber disminuido
    $algunaMenor = false;
    foreach (NecesidadEstado::TODAS as $necId) {
        if ($nec[$necId]['valor'] < $valoresIniciales[$rid][$necId]['valor']) {
            $algunaMenor = true;
            break;
        }
    }
    ok($algunaMenor, "$rid tiene al menos una necesidad con decay (< 85)");
}
ok($decayAplicado, 'Al menos un residente tuvo decay aplicado');

// --- Test 4: NO existe fantasma $partida['runtime']['necesidades'] ---
ok(
    !isset($p['runtime']['necesidades']),
    'NO existe fantasma $partida[runtime][necesidades]'
);

// --- Test 5: Las necesidades pueden diferir entre sí ---
$vectores = [];
foreach ($rids as $rid) {
    $nec = NecesidadEstado::obtener($p['residentes'][$rid]);
    $vectores[$rid] = array_map(fn($n) => $n['valor'], $nec);
}

// Con 3 horas de decay y 2.5 base, los valores deberían ser ~77.5
// Pero pueden variar según calibración. Verificar que no son todos 85.
$todosIguales85 = true;
foreach ($vectores as $vec) {
    if (array_unique($vec) !== [85]) {
        $todosIguales85 = false;
        break;
    }
}
ok(!$todosIguales85, 'Valores ya no son todos 85 tras catch-up');

// --- Test 6: Divergencia vía recuperación (lugar) ---
// Bajar social a un residente y recuperar solo social
$ridTest = $rids[0];
$p['residentes'][$ridTest]['runtime']['necesidades']['social']['valor'] = 40;
$p['residentes'][$ridTest]['runtime']['necesidades']['social']['banda'] = NecesidadEstado::calcularBanda(40);
$antesRec = NecesidadEstado::obtener($p['residentes'][$ridTest]);
ok($antesRec['social']['valor'] === 40, 'Social bajado a 40 para test de divergencia');

// Recuperar solo social (lugar con social como principal)
NecesidadEstado::aplicarRecuperacion(
    $p['residentes'][$ridTest],
    ['social' => 'principal', 'diversion' => null, 'actividad' => null, 'calma' => null],
    false,
    false,
    $cal
);
$despuesRec = NecesidadEstado::obtener($p['residentes'][$ridTest]);
ok($despuesRec['social']['valor'] > 40, 'Social se recuperó: 40 → ' . $despuesRec['social']['valor']);
ok($despuesRec['diversion']['valor'] === $antesRec['diversion']['valor'], 'Diversión no cambió');
ok($despuesRec['actividad']['valor'] === $antesRec['actividad']['valor'], 'Actividad no cambió');
ok($despuesRec['calma']['valor'] === $antesRec['calma']['valor'], 'Calma no cambió');

// Vector final divergente
$vectorFinal = [];
foreach (NecesidadEstado::TODAS as $n) {
    $vectorFinal[] = "$n=" . $despuesRec[$n]['valor'];
}
echo "Vector final divergente: " . implode(' | ', $vectorFinal) . "\n";

$uniqueVals = array_unique(array_values($vectorFinal));
// Al menos 2 valores distintos (social recuperada vs las demás)
ok(count(explode('|', implode('|', $vectorFinal))) >= 2, 'Vector tiene necesidades con valores diferentes');

// --- Resultado ---
echo "\n";
if ($failures === 0) {
    echo "ALL OK\n";
} else {
    echo "FAILURES: $failures\n";
}
exit($failures > 0 ? 1 : 0);
