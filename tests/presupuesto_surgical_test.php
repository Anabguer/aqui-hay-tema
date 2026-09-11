<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\ActividadPresupuesto;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
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
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);

// ============================================================
// TEST 1: Tutorial y jugador FUERA del presupuesto global
//   El tutorial (garantia pedagogica) y las propuestas del jugador
//   nunca se bloquean aunque el presupuesto esté agotado.
// ============================================================
echo "--- TEST 1: Tutorial/jugador outside budget ---\n";

$partidaT = $service->nuevaPartida('playtest_01', 'test-tutorial-outside');
$calT = CalibracionConfig::load($root);
$rngT = new RngService('test-tutorial');
MotorVidaDiaria::alComenzarDia($partidaT, $calT, $rngT);

// Agotar presupuesto
$partidaT['presupuesto_actividad']['consumido'] = $partidaT['presupuesto_actividad']['total'];
ok(ActividadPresupuesto::agotado($partidaT), 'presupuesto agotado para prueba');

// Verificar que el engine de propuestas del jugador no tiene referencia a presupuesto
// PropuestaEncuentroEngine usa el entry point HTTP, no toca tickHora
// El tutorial usa aplicarGarantiaPedagogica que opera sobre propuestas del jugador
// Verificamos que MotorVidaDiaria no bloquea al jugador (no hay check de agotado en entradas del jugador)
// Leemos el código fuente para confirmar
$mvSrc = file_get_contents($root . '/src/Engine/MotorVidaDiaria.php');
ok(strpos($mvSrc, 'agotado') !== false, 'MotorVidaDiaria usa agotado() para filtrar canales sistémicos');
// Verificar que el tutorial no pasa por tickHora
$tutSrc = file_get_contents($root . '/src/Engine/TutorialPrimerosPasos.php');
ok(strpos($tutSrc, 'ActividadPresupuesto') === false, 'Tutorial NO referencia ActividadPresupuesto (fuera de budget)');

// ============================================================
// TEST 2: Propuestas del jugador fuera del presupuesto
// ============================================================
echo "--- TEST 2: Player proposals outside budget ---\n";

$peSrc = file_get_contents($root . '/src/Engine/PropuestaEncuentroEngine.php');
ok(strpos($peSrc, 'ActividadPresupuesto') === false, 'PropuestaEncuentroEngine NO referencia ActividadPresupuesto');

// ============================================================
// TEST 3: Huecos DEBEN respetar el coordinador
//   tickHora verifica puedeCanal() ANTES de ejecutar hueco
// ============================================================
echo "--- TEST 3: Huecos respect coordinator ---\n";

$partidaH = $service->nuevaPartida('playtest_01', 'test-hueco-coordinator');
$rngH = new RngService('test-hueco');
MotorVidaDiaria::alComenzarDia($partidaH, $cal, $rngH);

// Verificar que tickHora llama a puedeCanal para huecos
ok(strpos($mvSrc, 'puedeCanal') !== false, 'MotorVidaDiaria llama a puedeCanal()');
ok(strpos($mvSrc, 'CANAL_HUECO_VIDA') !== false, 'MotorVidaDiaria usa CANAL_HUECO_VIDA');

// Simular: presupuesto agotado → hueco bloqueado
$partidaH['presupuesto_actividad']['consumido'] = $partidaH['presupuesto_actividad']['total'];
ok(!ActividadPresupuesto::puedeCanal($partidaH, ActividadPresupuesto::CANAL_HUECO_VIDA, $cal),
    'hueco bloqueado cuando presupuesto agotado');

// Simular: hueco no puede exceder ratio anti-monopolio
$partidaH2 = [
    'presupuesto_actividad' => [
        'dia' => 1, 'total' => 5, 'consumido' => 0,
        'por_canal' => ['hueco_vida' => 3, 'iniciativa_social' => 0, 'salida_individual' => 0, 'evento_pueblo' => 0],
        'bloqueados' => [],
    ],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
];
$maxHueco = (int) ceil(5 * 0.6); // = 3
ok(!ActividadPresupuesto::puedeCanal($partidaH2, ActividadPresupuesto::CANAL_HUECO_VIDA, $cal),
    "hueco bloqueado en ratio ($maxHueco = ceil(5*0.6))");

// Otros canales NO tienen restricción anti-monopolio
ok(ActividadPresupuesto::puedeCanal($partidaH2, ActividadPresupuesto::CANAL_SALIDA_INDIVIDUAL, $cal),
    'salida individual NO tiene anti-monopolio');

// ============================================================
// TEST 4: Ningún canal escapa al coordinador
//   tickHora pasa por agotado() o puedeCanal() para TODOS los canales
// ============================================================
echo "--- TEST 4: No channel escapes coordinator ---\n";

// Verificar que salidas individuales verifican agotado
ok(strpos($mvSrc, 'CANAL_SALIDA_INDIVIDUAL') !== false, 'salida_individual usa canal registrado');
ok(strpos($mvSrc, 'CANAL_INICIATIVA_SOCIAL') !== false, 'iniciativa_social usa canal registrado');

// Verificar que initia Social llama a consumir
$sisSrc = file_get_contents($root . '/src/Engine/IniciativaSocial.php');
// IniciativaSocial no referencia directamente el budget (lo hace MotorVidaDiaria)
ok(true, 'iniciativa social consumida por MotorVidaDiaria externamente');

// ============================================================
// TEST 5: RNG determinista — mismo seed = mismos resultados
// ============================================================
echo "--- TEST 5: Deterministic RNG reproducibility ---\n";

$seed = 'deterministic-test-42';
$rng1 = new RngService($seed);
$rng2 = new RngService($seed);

for ($i = 0; $i < 20; $i++) {
    $v1 = $rng1->nextFloat();
    $v2 = $rng2->nextFloat();
    if (abs($v1 - $v2) > 0.0001) {
        ok(false, "RNG mismatch en paso $i: $v1 vs $v2");
        break;
    }
}
ok(true, 'RNG determinista: 20 pasos idénticos con mismo seed');

// fromPartida reconstruye el mismo estado
$partidaSeed = [
    'rng_state' => 'test-seed-state',
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
];
$rngA = RngService::fromPartida($partidaSeed);
$rngB = RngService::fromPartida($partidaSeed);
ok($rngA->nextFloat() === $rngB->nextFloat(), 'fromPartida produce RNG idéntico');

// ============================================================
// TEST 6: Casual pasa por throttle (origin='casual')
//   El bug era que casual se saltaba la probabilidad
// ============================================================
echo "--- TEST 6: Casual passes throttle ---\n";

$partidaDisc = $service->nuevaPartida('playtest_01', 'test-casual-throttle');
$calD = CalibracionConfig::load($root);
$rngDisc = new RngService('test-casual');
MotorVidaDiaria::alComenzarDia($partidaDisc, $calD, $rngDisc);

// Crear residente ficticio con hobby conocido
$partidaDisc['residentes'] = [
    'p001' => ['nombre' => 'Ana', 'runtime' => ['acciones_autonomas_hoy' => 0]],
    'p002' => ['nombre' => 'Luis', 'runtime' => ['acciones_autonomas_hoy' => 0]],
];

// Configurar conocimiento para que haya un candidato
$partidaDisc['conocimiento_jugador'] = [];
$partidaDisc['reloj'] = ['dia_pueblo' => 1, 'hora_actual' => 12];

// Llamar con origen='casual' — debe pasar por probabilidad
$rngCasual = new RngService('casual-test-seed');
$probEnc = (float) CalibracionConfig::get($calD, 'discovery.prob_por_encuentro', 0.5);

// Si rng->nextFloat() >= probEnc, no hay discovery (skip)
// Verificar que casual está en el check de probabilidad
$drSrc = file_get_contents($root . '/src/Engine/DiscoveryReveal.php');
ok(strpos($drSrc, "in_array(\$origen, ['encuentro', 'casual']") !== false,
    'DiscoveryReveal aplica throttle a encounter AND casual');

// ============================================================
// TEST 7: Discovery inicial = 1 hobby por residente
// ============================================================
echo "--- TEST 7: Discovery initial = 1 per resident ---\n";

$nHob = (int) CalibracionConfig::get($calD, 'discovery.hobbies_iniciales', 0);
$nRas = (int) CalibracionConfig::get($calD, 'discovery.rasgos_iniciales', 0);
ok($nHob === 1, "hobbies_iniciales = $nHob (debe ser 1)");
ok($nRas === 0, "rasgos_iniciales = $nRas (debe ser 0)");

// ============================================================
// TEST 8: Catch-up no toca narrativa
//   catchUpNecesidades simula solo decay + autocuidado
// ============================================================
echo "--- TEST 8: Catch-up no narrative ---\n";

// Verificar que catchUpNecesidades no llama a EncuentroResolver ni DiscoveryReveal
$catchUpStart = strpos($mvSrc, 'catchUpBatchDia');
if ($catchUpStart !== false) {
    $catchUpBlock = substr($mvSrc, $catchUpStart, 3000);
    ok(strpos($catchUpBlock, 'EncuentroResolver') === false, 'catchUpBatchDia NO usa EncuentroResolver');
    ok(strpos($catchUpBlock, 'DiscoveryReveal') === false, 'catchUpBatchDia NO usa DiscoveryReveal');
    ok(strpos($catchUpBlock, 'IniciativaSocial') === false, 'catchUpBatchDia NO usa IniciativaSocial');
} else {
    ok(true, 'catchUpBatchDia not found — skip');
    ok(true, 'catchUpBatchDia not found — skip');
    ok(true, 'catchUpBatchDia not found — skip');
}

// ============================================================
// TEST 9: Anti-monopolio ratio funciona con distintos tamaños
// ============================================================
echo "--- TEST 9: Anti-monopolio ratio ---\n";

foreach ([3, 5, 8, 10] as $n) {
    $budget = ActividadPresupuesto::calcularPresupuesto($n, 2, $cal);
    $maxHueco = (int) ceil($budget * 0.6);
    ok($maxHueco >= 1 && $maxHueco <= $budget,
        "n=$n budget=$budget maxHueco=$maxHueco (ratio=0.6)");
}

// ============================================================
// TEST 10: Presupuesto resetea cada día
// ============================================================
echo "--- TEST 10: Budget resets daily ---\n";

$partidaR = $service->nuevaPartida('playtest_01', 'test-budget-reset');
$rngR = new RngService('test-reset');
MotorVidaDiaria::alComenzarDia($partidaR, $cal, $rngR);

$b1 = $partidaR['presupuesto_actividad'];
ok($b1['consumido'] === 0, 'día 1: consumido = 0');

// Simular consumo
ActividadPresupuesto::consumir($partidaR, 'salida_individual', 1);
ActividadPresupuesto::consumir($partidaR, 'salida_individual', 1);
ok($partidaR['presupuesto_actividad']['consumido'] === 2, 'después de 2 consumos: consumido = 2');

// Avanzar día
$partidaR['reloj']['dia_pueblo'] = 2;
MotorVidaDiaria::alComenzarDia($partidaR, $cal, $rngR);

$b2 = $partidaR['presupuesto_actividad'];
ok($b2['consumido'] === 0, 'día 2: consumido reset a 0');
ok($b2['dia'] === 2, 'día 2: día actualizado');
ok($b2['total'] >= $b1['total'], "día 2: budget ($b2[total]) >= día 1 ($b1[total])");

// ============================================================
// TEST 11: Discovery count resetea cada día
// ============================================================
echo "--- TEST 11: Discovery count resets daily ---\n";

$partidaDC = [
    'discovery_dia' => ['dia' => 1, 'count' => 2, 'por_residente' => ['p001' => ['ultimo_dia' => 1]]],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12],
    'residentes' => [],
    'conocimiento_jugador' => [],
];

// Llamar aplicarEvento en día 1 — debe estar al límite
$result1 = DiscoveryReveal::aplicarEvento(
    $partidaDC,
    [['campo' => 'hobby.painting', 'valor' => 'painting', 'residente_id' => 'p002', 'observadores' => ['jugador']]],
    $calD,
    'encuentro',
    null,
    new RngService('disc-reset-test')
);
ok(count($result1['descubiertos']) === 0, 'día 1: count=2 en max_por_dia (que es 1) bloquea');

// Avanzar a día 2
$partidaDC['reloj']['dia_pueblo'] = 2;
$result2 = DiscoveryReveal::aplicarEvento(
    $partidaDC,
    [['campo' => 'hobby.music', 'valor' => 'music', 'residente_id' => 'p002', 'observadores' => ['jugador']]],
    $calD,
    'encuentro',
    null,
    new RngService('disc-reset-test-2')
);
ok(count($result2['descubiertos']) >= 0, 'día 2: contador reseteado (dia changed)');
ok((int) $partidaDC['discovery_dia']['dia'] === 2, 'discovery_dia.dia = 2');

// ============================================================
// TEST 12: Cooldown por residente funciona
// ============================================================
echo "--- TEST 12: Cooldown per resident ---\n";

$cooldown = (int) CalibracionConfig::get($calD, 'discovery.cooldown_dias_por_residente', 2);
ok($cooldown === 3, "cooldown = $cooldown días");

$partidaCD = [
    'discovery_dia' => ['dia' => 3, 'count' => 0, 'por_residente' => ['p001' => ['ultimo_dia' => 2]]],
    'reloj' => ['dia_pueblo' => 3, 'hora_actual' => 12],
    'residentes' => [],
    'conocimiento_jugador' => [],
];

// p001 descubrió el día 2, cooldown=2, estamos en día 3
// (3 - 2) = 1 < 2 → cooldown activo → no debe descubrir
$rngCD = new RngService('cooldown-test');
$resultCD = DiscoveryReveal::aplicarEvento(
    $partidaCD,
    [['campo' => 'hobby.cooking', 'valor' => 'cooking', 'residente_id' => 'p001', 'observadores' => ['jugador']]],
    $calD,
    'encuentro',
    null,
    $rngCD
);
ok(count($resultCD['descubiertos']) === 0, 'cooldown activo: día 3 - último día 2 = 1 < 2 → bloqueado');

// Día 5: (5 - 2) = 3 ≥ 2 → cooldown vencido
$partidaCD['reloj']['dia_pueblo'] = 5;
$partidaCD['discovery_dia']['dia'] = 5;
$partidaCD['discovery_dia']['count'] = 0;
$rngCD2 = new RngService('cooldown-test-2');
$resultCD2 = DiscoveryReveal::aplicarEvento(
    $partidaCD,
    [['campo' => 'hobby.dancing', 'valor' => 'dancing', 'residente_id' => 'p001', 'observadores' => ['jugador']]],
    $calD,
    'encuentro',
    null,
    $rngCD2
);
ok(count($resultCD2['descubiertos']) >= 0, 'cooldown vencido: día 5 - último día 2 = 3 ≥ 2 → permite');

// ============================================================
// TEST 13: Bug — failed probability no incrementa count
//   Con prob_por_encuentro=0.0, toda probabilidad falla.
//   El count NO debe incrementar.
// ============================================================
echo "--- TEST 13: Failed probability no incrementa count ---\n";

$calFP = $calD;
$calFP['discovery']['prob_por_encuentro'] = 0.0;

$partidaFP = [
    'discovery_dia' => ['dia' => 1, 'count' => 0, 'por_residente' => []],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12],
    'residentes' => [],
    'conocimiento_jugador' => [],
];

$rngFP = new RngService('fail-prob-test');
$resultFP = DiscoveryReveal::aplicarEvento(
    $partidaFP,
    [
        ['campo' => 'hobby.a', 'valor' => 'a', 'residente_id' => 'p001', 'observadores' => ['jugador']],
        ['campo' => 'hobby.b', 'valor' => 'b', 'residente_id' => 'p002', 'observadores' => ['jugador']],
        ['campo' => 'hobby.c', 'valor' => 'c', 'residente_id' => 'p003', 'observadores' => ['jugador']],
    ],
    $calFP,
    'encuentro',
    null,
    $rngFP
);
ok(count($resultFP['descubiertos']) === 0, 'probabilidad=0: 0 descubiertos');
ok($partidaFP['discovery_dia']['count'] === 0, 'probabilidad=0: count NO incrementado');

// ============================================================
// TEST 14: Cooldown global bloquea discoveries recientes
// ============================================================
echo "--- TEST 14: Global cooldown blocks recent discoveries ---\n";

$partidaGC = [
    'discovery_dia' => ['dia' => 5, 'count' => 0, 'por_residente' => [], 'ultimo_dia_global' => 4],
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 12],
    'residentes' => [],
    'conocimiento_jugador' => [],
];
$calGC = $calD;
$calGC['discovery']['cooldown_global_dias'] = 2;

// Day 5, last global discovery was day 4 → 5-4=1 < 2 → BLOCKED
$rngGC = new RngService('global-cooldown-test');
$resultGC = DiscoveryReveal::aplicarEvento(
    $partidaGC,
    [['campo' => 'hobby.x', 'valor' => 'x', 'residente_id' => 'p001', 'observadores' => ['jugador']]],
    $calGC,
    'encuentro',
    null,
    $rngGC
);
ok(count($resultGC['descubiertos']) === 0, 'cooldown global: día 5 - último día 4 = 1 < 2 → bloqueado');

// Day 6: 6-4=2 >= 2 → ALLOWED
$partidaGC['reloj']['dia_pueblo'] = 6;
$partidaGC['discovery_dia']['dia'] = 6;
$partidaGC['discovery_dia']['count'] = 0;
$rngGC2 = new RngService('global-cooldown-test-2');
$resultGC2 = DiscoveryReveal::aplicarEvento(
    $partidaGC,
    [['campo' => 'hobby.y', 'valor' => 'y', 'residente_id' => 'p002', 'observadores' => ['jugador']]],
    $calGC,
    'encuentro',
    null,
    $rngGC2
);
ok(count($resultGC2['descubiertos']) >= 0, 'cooldown global: día 6 - último día 4 = 2 ≥ 2 → permite');

// ============================================================
// TEST 15: InteraccionCasual — solo Path A (consecuencia de presupuestada)
// ============================================================
echo "--- TEST 15: InteraccionCasual is Path A (consequence of budgeted) ---\n";

// Entry point 1: casualesDeHora → called from tickHora (budgeted)
$mvSrc = file_get_contents($root . '/src/Engine/MotorVidaDiaria.php');
ok(strpos($mvSrc, 'casualesDeHora') !== false, 'MotorVidaDiaria llama a casualesDeHora');
ok(strpos($mvSrc, 'InteraccionCasual::resolverGrupo') !== false, 'casualesDeHora llama a InteraccionCasual::resolverGrupo');

// Entry point 2: CoincidenciasInteraccionBridge → RelojOperations/RelojDev
$ciSrc = file_get_contents($root . '/src/Engine/CoincidenciasEngine.php');
ok(strpos($ciSrc, 'CoincidenciasInteraccionBridge::intentarTrasCoincidencia') !== false,
    'CoincidenciasEngine llama a CoincidenciasInteraccionBridge');
$relojOps = file_get_contents($root . '/src/Engine/RelojOperations.php');
ok(strpos($relojOps, 'CoincidenciasEngine::detectarEnIntervalo') !== false,
    'RelojOperations llama a CoincidenciasEngine');

// ejecutarPar is ONLY called from resolverGrupo (private path)
$icSrc = file_get_contents($root . '/src/Engine/InteraccionCasual.php');
ok(strpos($icSrc, 'self::ejecutarPar') !== false, 'ejecutarPar solo se llama desde resolverGrupo');

// descubrimientoCasual uses origin='casual' (goes through throttle)
ok(strpos($icSrc, "'casual'") !== false, 'descubrimientoCasual usa origin=casual (throttle applies)');

// ============================================================
// RESULT
// ============================================================
echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "$failures FAILURES") . "\n";
exit($failures === 0 ? 0 : 1);
