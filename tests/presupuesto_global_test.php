<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\ActividadPresupuesto;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;

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

DomainBootstrap::boot();
$service = new PartidaService($root);

// ============================================================
// TEST 1: Presupuesto global se inicializa al comenzar día
// ============================================================
$partida = $service->nuevaPartida('playtest_01', 'test-budget-init');
$cal = CalibracionConfig::load($root);
$rng = new RngService('test-42');

// Day 1
MotorVidaDiaria::alComenzarDia($partida, $cal, $rng);
ok(isset($partida['presupuesto_actividad']), 'budget inicializado en día 1');
ok($partida['presupuesto_actividad']['dia'] === 1, 'budget día 1');
ok($partida['presupuesto_actividad']['total'] >= 1, 'budget total >= 1');

// ============================================================
// TEST 2: Día 1 tiene budget reducido (dia_1_factor)
// ============================================================
$n = count($partida['residentes'] ?? []);
$budgetNormal = ActividadPresupuesto::calcularPresupuesto($n, 2, $cal);
$budgetDia1 = ActividadPresupuesto::calcularPresupuesto($n, 1, $cal);
ok($budgetDia1 <= $budgetNormal, "día 1 budget ($budgetDia1) <= día 2 ($budgetNormal)");

// ============================================================
// TEST 3: consumir() funciona correctamente
// ============================================================
$partidaTest = [
    'presupuesto_actividad' => [
        'dia' => 1,
        'total' => 3,
        'consumido' => 0,
        'por_canal' => ['hueco_vida' => 0, 'iniciativa_social' => 0, 'salida_individual' => 0, 'evento_pueblo' => 0],
        'bloqueados' => [],
    ],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
];

ok(ActividadPresupuesto::consumir($partidaTest, 'salida_individual', 1), 'consumir 1ra unidad');
ok($partidaTest['presupuesto_actividad']['consumido'] === 1, 'consumido = 1');
ok($partidaTest['presupuesto_actividad']['por_canal']['salida_individual'] === 1, 'canal salida_individual = 1');

ok(ActividadPresupuesto::consumir($partidaTest, 'iniciativa_social', 2), 'consumir 2da unidad');
ok(ActividadPresupuesto::consumir($partidaTest, 'iniciativa_social', 2), 'consumir 3ra unidad');
ok(!ActividadPresupuesto::consumir($partidaTest, 'evento_pueblo', 1), 'consumir 4ta unidad FALLA (agotado)');
ok(count($partidaTest['presupuesto_actividad']['bloqueados']) === 1, '1 bloqueado registrado');

// ============================================================
// TEST 4: agotado() y restante()
// ============================================================
ok(ActividadPresupuesto::agotado($partidaTest), 'agotado después de 3/3');
ok(ActividadPresupuesto::restante($partidaTest) === 0, 'restante = 0');

// ============================================================
// TEST 5: Sin presupuesto activo → permitir todo
// ============================================================
$partidaSinBudget = ['reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10]];
ok(ActividadPresupuesto::consumir($partidaSinBudget, 'salida_individual', 1), 'sin budget → permitir');
ok(ActividadPresupuesto::restante($partidaSinBudget) === 999, 'sin budget → restante 999');
ok(!ActividadPresupuesto::agotado($partidaSinBudget), 'sin budget → no agotado');

// ============================================================
// TEST 6: Descubrimiento inicial reducido (0+0 por defecto)
// ============================================================
$partidaD = $service->nuevaPartida('playtest_01', 'test-discovery-init');
// Default in calibracion_vida.json: hobbies_iniciales=0, rasgos_iniciales=0
$calD = CalibracionConfig::load($root);
$nHob = (int) CalibracionConfig::get($calD, 'discovery.hobbies_iniciales', 0);
$nRas = (int) CalibracionConfig::get($calD, 'discovery.rasgos_iniciales', 0);
ok($nHob === 1, "hobbies_iniciales = $nHob (debe ser 1)");
ok($nRas === 0, "rasgos_iniciales = $nRas (debe ser 0)");

// ============================================================
// TEST 7: Cap diario de descubrimientos
// ============================================================
$maxDia = (int) CalibracionConfig::get($calD, 'discovery.max_por_dia', 3);
ok($maxDia === 1, "max_por_dia = $maxDia (debe ser 1)");

// ============================================================
// TEST 8: Probabilidad por encuentro
// ============================================================
$probEnc = (float) CalibracionConfig::get($calD, 'discovery.prob_por_encuentro', 0.5);
ok(abs($probEnc - 0.5) < 0.01, "prob_por_encuentro = $probEnc (debe ser 0.5)");

// ============================================================
// TEST 9: Cooldown por residente
// ============================================================
$cooldown = (int) CalibracionConfig::get($calD, 'discovery.cooldown_dias_por_residente', 2);
ok($cooldown === 2, "cooldown_dias_por_residente = $cooldown (debe ser 2)");

// ============================================================
// TEST 10: Presupuesto calibración
// ============================================================
$base = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.base', 1.5);
$factor = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.factor_sqrt', 0.8);
$dia1F = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.dia_1_factor', 0.5);
ok(abs($base - 1.5) < 0.01, "presupuesto base = $base");
ok(abs($factor - 0.8) < 0.01, "presupuesto factor_sqrt = $factor");
ok(abs($dia1F - 0.5) < 0.01, "presupuesto dia_1_factor = $dia1F");

// Hueco ratio
$maxHuecoRatio = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.max_hueco_ratio', 0.6);
ok(abs($maxHuecoRatio - 0.6) < 0.01, "presupuesto max_hueco_ratio = $maxHuecoRatio");

// ============================================================
// TEST 10b: puedeCanal anti-monopolio
// ============================================================
$partidaAnti = [
    'presupuesto_actividad' => [
        'dia' => 1, 'total' => 3, 'consumido' => 0,
        'por_canal' => ['hueco_vida' => 0, 'iniciativa_social' => 0, 'salida_individual' => 0, 'evento_pueblo' => 0],
        'bloqueados' => [],
    ],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
];
ok(ActividadPresupuesto::puedeCanal($partidaAnti, 'hueco_vida', $cal), 'hueco puede (0/2)');
$partidaAnti['presupuesto_actividad']['por_canal']['hueco_vida'] = 1;
ok(ActividadPresupuesto::puedeCanal($partidaAnti, 'hueco_vida', $cal), 'hueco puede (1/2)');
$partidaAnti['presupuesto_actividad']['por_canal']['hueco_vida'] = 2;
ok(!ActividadPresupuesto::puedeCanal($partidaAnti, 'hueco_vida', $cal), 'hueco bloqueado (2/2 = 67% > 60%)');
ok(ActividadPresupuesto::puedeCanal($partidaAnti, 'salida_individual', $cal), 'salida puede (no es hueco)');

// ============================================================
// TEST 11: Debug output
// ============================================================
$debug = ActividadPresupuesto::debug($partida);
ok($debug['activo'] === true, 'debug activo = true');
ok(isset($debug['total']), 'debug tiene total');
ok(isset($debug['consumido']), 'debug tiene consumido');

// ============================================================
// TEST 12: Feature flags existen en playtest_01
// ============================================================
ok(FeatureConfig::isEnabled($partida, 'npc_autonomy_enabled'), 'autonomía flag ON');
ok(FeatureConfig::isEnabled($partida, 'discovery_enabled'), 'discovery flag ON');

// ============================================================
// RESULT
// ============================================================
echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "$failures FAILURES") . "\n";
exit($failures === 0 ? 0 : 1);
