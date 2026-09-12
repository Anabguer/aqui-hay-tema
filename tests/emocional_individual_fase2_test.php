<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AzarPonderado;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\EmotionalRecovery;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\EncuentroExperiencia;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\VidaPuebloEngine;

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);

$passed = 0;
$failed = 0;

function assert_true(bool $cond, string $msg): void
{
    global $passed, $failed;
    if ($cond) { $passed++; echo "  ✓ $msg\n"; }
    else { $failed++; echo "  ✗ $msg\n"; }
}

function assert_eq($actual, $expected, string $msg): void
{
    assert_true($actual === $expected, "$msg — expected " . var_export($expected, true) . ", got " . var_export($actual, true));
}

function assert_range(float $val, float $min, float $max, string $msg): void
{
    assert_true($val >= $min && $val <= $max, "$msg ($val en [$min, $max])");
}

echo "=== INDIVIDUALES FASE 2: Distribución + regresión ===\n\n";

// ── Helper: tiradas masivas ──
function simularDistribucion(float $afinidad, int $n = 10000): array
{
    global $cal;
    $resultados = ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'];
    $counts = array_fill_keys($resultados, 0);
    for ($i = 0; $i < $n; $i++) {
        $rng = new RngService('ind_test_' . $afinidad, $i * 7 + 13);
        $t = AzarPonderado::tirarIndividual($rng, $resultados, $afinidad, $cal);
        $counts[$t['resultado']]++;
    }
    $pcts = [];
    foreach ($counts as $k => $v) {
        $pcts[$k] = round($v / $n * 100, 1);
    }
    return $pcts;
}

// ── TEST 1: Distribución neutral (sin match, sin rechazo) ──
echo "TEST 1: Distribución neutral (afinidad=0)\n";
$dist = simularDistribucion(0.0);
assert_range($dist['muy_mal'], 0, 3, "muy_mal ≤ 3%");
assert_range($dist['normal'], 40, 70, "normal domina (40-70%)");
assert_range($dist['bien'], 12, 30, "bien presente (12-30%)");
echo "  → " . json_encode($dist) . "\n";

// ── TEST 2: Distribución con match fuerte ──
echo "\nTEST 2: Distribución con match fuerte (afinidad=+0.4)\n";
$distMatch = simularDistribucion(0.4);
assert_range($distMatch['muy_mal'], 0, 2, "muy_mal muy raro con match");
assert_true($distMatch['muy_bien'] > $dist['muy_bien'], "muy_bien > neutral con match");
echo "  → " . json_encode($distMatch) . "\n";

// ── TEST 3: Distribución con rechazo fuerte ──
echo "\nTEST 3: Distribución con rechazo fuerte (afinidad=-0.4)\n";
$distRechazo = simularDistribucion(-0.4);
assert_range($distRechazo['muy_mal'], 3, 15, "muy_mal presente pero raro con rechazo");
assert_true($distRechazo['muy_mal'] > $dist['muy_mal'], "muy_mal > neutral con rechazo");
assert_true($distRechazo['muy_bien'] < $dist['muy_bien'], "muy_bien < neutral con rechazo");
echo "  → " . json_encode($distRechazo) . "\n";

// ── TEST 4: Comparación neutral vs compatible ──
echo "\nTEST 4: Las distribuciones neutral y compatible deben ser distintas\n";
assert_true($dist !== $distMatch, "neutral ≠ compatible");
assert_true($dist['muy_bien'] < $distMatch['muy_bien'], "compatible tiene más muy_bien");
assert_true($dist['muy_mal'] > $distMatch['muy_mal'], "compatible tiene menos muy_mal");

// ── TEST 5: Social mantiene distribución actual ──
echo "\nTEST 5: Social mantiene AzarPonderado estándar\n";
$rngSocial = new RngService('social_test', 42);
$socialResult = AzarPonderado::tirar($rngSocial, ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'], 0.3, $cal);
assert_true(in_array($socialResult['resultado'], ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien']), "social devuelve resultado válido");
assert_eq($socialResult['carga'], 0.3, "social usa carga pasada");

// ── TEST 6: Emotional — individual muy_mal sin tristeza ──
echo "\nTEST 6: Emotional — individual muy_mal sin tristeza\n";
$eval = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_mal', false, true);
assert_true($eval === null, "individual muy_mal → sin emoción");

$eval2 = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_mal', false, false);
assert_true($eval2 !== null && ($eval2['estado'] ?? '') === 'triste', "social muy_mal → triste");

// ── TEST 7: Heart delta individual ──
echo "\nTEST 7: Heart delta individual coherente\n";
assert_eq(VidaPuebloEngine::deltaIndividual('muy_bien'), 2, "individual muy_bien → +2");
assert_eq(VidaPuebloEngine::deltaIndividual('bien'), 1, "individual bien → +1");
assert_eq(VidaPuebloEngine::deltaIndividual('normal'), 0, "individual normal → 0");
assert_eq(VidaPuebloEngine::deltaIndividual('mal'), 0, "individual mal → 0");
assert_eq(VidaPuebloEngine::deltaIndividual('muy_mal'), -1, "individual muy_mal → -1");

// Heart social sin cambios
assert_eq(VidaPuebloEngine::deltaResultadoEncuentro('muy_mal'), -2, "social muy_mal → -2 (sin cambios)");
assert_eq(VidaPuebloEngine::deltaResultadoEncuentro('mal'), -1, "social mal → -1 (sin cambios)");

// ── TEST 8: Coherencia Heart — muy_mal ≤ mal en consecuencia ──
echo "\nTEST 8: Coherencia Heart — muy_mal ≤ mal\n";
assert_true(
    VidaPuebloEngine::deltaIndividual('muy_mal') <= VidaPuebloEngine::deltaIndividual('mal'),
    "muy_mal (-1) ≤ mal (0) en individuales"
);

// ── TEST 9: caso Wendy — carga individual ──
echo "\nTEST 9: caso Wendy — cargaIndividual con sin match\n";
// Wendy tiene escribir, cine, música. Gimnasio no es su hobby.
// Simulamos: plan sin aporte, sin rechazo explícito, estado neutro
$cargaWendy = EncuentroExperiencia::cargaIndividual(
    ['residentes' => ['wendy' => ['runtime' => ['estado_emocional' => ['id' => 'neutro']]]]],
    'wendy', 'lug_gimnasio', null, $cal
);
assert_range($cargaWendy, -0.1, 0.2, "Wendy gimnasio sin match → carga ~0");

// ── TEST 10: residente con hobby match ──
echo "\nTEST 10: cargaIndividual con hobby match (simulado)\n";
// Un residente con plan de aporte=8 (hobby match)
$cargaMatch = EncuentroExperiencia::cargaIndividual(
    ['residentes' => ['r1' => ['runtime' => ['estado_emocional' => ['id' => 'neutro']]]]],
    'r1', 'lug_biblioteca', null, $cal
);
// Sin catálogo no puede calcular PlanAfinidad, pero cargaIndividual usa el plan del snapshot
// Verificamos que el método existe y 返回 float
assert_true(is_float($cargaMatch), "cargaIndividual devuelve float");

// ── TEST 11: Ausencia de match NO equivale a penalización ──
echo "\nTEST 11: Ausencia de match ≠ penalización\n";
assert_true($dist['muy_mal'] <= 3, "sin match: muy_mal ≤ 3% (no es penalización)");

echo "\n=== RESULTADO: $passed OK / $failed FAIL ===\n";
exit($failed > 0 ? 1 : 0);
