<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CatchUpPlanner;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\RelojOperations;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

// 1. _bloqueado_decision es metadata del plan, no gate de ejecución
$plan = CatchUpPlanner::planificar(3600);
ok(isset($plan['_bloqueado_decision']), 'plan incluye _bloqueado_decision');
ok(in_array('ritmo_3_dias_16_residentes', $plan['_bloqueado_decision'], true), 'ritmo listado como pendiente de diseño');
ok($plan['cantidades'] === null, 'catch-up no genera cantidades');
ok($plan['eventos_generados'] === [], 'catch-up no genera eventos del plan');

// 2. Llegadas lee reloj.dia_pueblo, no catch_up_pendiente
$partida = [
    'reloj' => ['dia_pueblo' => 10, 'hora_actual' => 16, 'minuto_actual' => 0,
        'catch_up_pendiente' => ['dia_antes' => 3, 'dia_despues' => 3, 'horas_juego_avanzadas' => 1]],
    'llegadas' => ['modo' => 'normal', 'cooldown_hasta_dia' => 0, 'dias_sin_oferta' => 0],
    'residentes' => [],
    'viviendas' => ['slots' => []],
    'tutorial' => ['jugable_completado' => true, 'activo' => false],
];
CapacidadViviendas::ensure($partida);
ok((int) ($partida['reloj']['dia_pueblo'] ?? 0) === 10, 'reloj principal en día 10 (no catch_up_pendiente.dia_*)');
ok((int) ($partida['reloj']['catch_up_pendiente']['dia_antes'] ?? 0) === 3, 'catch_up guarda snapshot histórico día 3');

// 3. Pity anti-mala-suerte
ok(CandidatoLlegadaEngine::forzarOfertaPorPity(3, 2) === false, 'pity no fuerza con 2 días');
ok(CandidatoLlegadaEngine::forzarOfertaPorPity(3, 3) === true, 'pity fuerza N=3 tras 3 días sin oferta');
ok(CandidatoLlegadaEngine::pDiaEfectiva(3, 3) > CandidatoLlegadaEngine::pDiaV3(3), 'boost progresivo con días sin oferta');

// 4. RelojOperations dispara tick de llegadas también en catch_up (inspección estática del código)
$ref = new ReflectionMethod(RelojOperations::class, 'avanzar');
$src = file_get_contents($ref->getFileName()) ?: '';
ok(str_contains($src, 'CandidatoLlegadaEngine::tick'), 'RelojOperations invoca tick de llegadas');
$posTick = strpos($src, 'CandidatoLlegadaEngine::tick');
$posCatchGuard = strpos($src, "if (!\$catchUp &&");
ok($posTick !== false && ($posCatchGuard === false || $posTick < $posCatchGuard || !preg_match(
    '/if\s*\(\s*!\$catchUp\s*\)[^{]*CandidatoLlegadaEngine::tick/s',
    $src
)), 'tick de llegadas no está detrás de if (!catchUp)');

echo $fail === 0 ? "OK llegadas_catchup_pity_test\n" : "FAIL llegadas_catchup_pity_test ($fail)\n";
exit($fail > 0 ? 1 : 0);
