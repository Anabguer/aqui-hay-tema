<?php
/**
 * TESTS DE FRONTERA: scoring continuo de necesidades
 * AGENTE 3 — HEART / STATEHEART — POST PLAYTEST
 *
 * Verifica que:
 * - 75.0 vs 74.9: stateHeart casi idéntico
 * - 50.0 vs 49.9: casi idéntico
 * - 25.0 vs 24.9: casi idéntico
 * - todas 100 > todas 75 > todas 50 > todas 25 > todas 0
 * - neutral emocional = baseline
 * - sin relaciones = baseline neutral
 * - relaciones malas bajan, buenas suben
 * - una emoción negativa aislada no hunde el pueblo
 * - jugador activo no pierde sistemáticamente todas sus ganancias
 * - sobreextensión sigue existiendo y puede doler
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\{CalibracionConfig, NecesidadEstado, VidaPuebloEngine};

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

function info(string $m): void
{
    echo "  INFO: $m\n";
}

function makeResident(array $overrides = []): array
{
    $res = [
        'id' => 'test_r',
        'nombre' => 'Test',
        'presencia' => 'residente',
        'relaciones' => [],
        'runtime' => [
            'ocupacion' => 'empleado',
            'estado_emocional' => ['id' => 'neutro'],
        ],
    ];
    NecesidadEstado::ensureResidente($res);
    foreach (NecesidadEstado::TODAS as $nec) {
        $res['runtime']['necesidades'][$nec]['valor'] = 75;
        $res['runtime']['necesidades'][$nec]['banda'] = NecesidadEstado::calcularBanda(75.0);
    }
    foreach ($overrides as $k => $v) {
        $res[$k] = $v;
    }
    return $res;
}

function makePartida(array $residentes): array
{
    global $cal;
    $p = [
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12, 'ultima_sesion_iso' => null],
        'meta' => ['seed' => 'test'],
        'features' => [VidaPuebloEngine::FLAG => true],
        'residentes' => $residentes,
    ];
    VidaPuebloEngine::ensure($p, $cal);
    return $p;
}

function scoreResident(array $residente): float
{
    $necesidades = NecesidadEstado::obtener($residente);
    $suma = 0.0;
    $n = 0;
    foreach ($necesidades as $nec) {
        $v = (float) $nec['valor'];
        $suma += ($v - 50.0) / 50.0;
        $n++;
    }
    return $n > 0 ? $suma / $n : 0.0;
}

function stateHeartForNeeds(array $needVals, array $emociones = [], array $relValores = []): array
{
    global $cal;
    $residentes = [];
    $nombres = ['A', 'B', 'C'];
    for ($i = 0; $i < count($needVals); $i++) {
        $rid = 'r' . $i;
        $res = makeResident();
        $res['id'] = $rid;
        $res['nombre'] = $nombres[$i] ?? $rid;
        NecesidadEstado::ensureResidente($res);
        foreach (NecesidadEstado::TODAS as $nec) {
            $res['runtime']['necesidades'][$nec]['valor'] = (int) $needVals[$i];
            $res['runtime']['necesidades'][$nec]['banda'] = NecesidadEstado::calcularBanda((float) $needVals[$i]);
        }
        if (isset($emociones[$i])) {
            $res['runtime']['estado_emocional'] = ['id' => $emociones[$i]];
        }
        $residentes[$rid] = $res;
    }

    $p = makePartida($residentes);

    // Relaciones — stored in partida-level relations
    $p['relaciones_sociales'] = [];
    $ids = array_keys($residentes);
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $val = $relValores[$i . '_' . $j] ?? 0;
            $a = min($ids[$i], $ids[$j]);
            $b = max($ids[$i], $ids[$j]);
            $p['relaciones_sociales'][] = [
                'id' => "soc_{$a}_{$b}",
                'persona_a' => $a,
                'persona_b' => $b,
                'conocidos' => abs($val) > 0,
                'a_hacia_b' => ['valor' => $val],
                'b_hacia_a' => ['valor' => $val],
            ];
        }
    }

    $estado = VidaPuebloEngine::calcularEstadoPueblo($p, $cal);
    $cfg = VidaPuebloEngine::cfg($cal);
    $sh = VidaPuebloEngine::stateHeart($estado, $cfg);

    return [
        'estado' => $estado,
        'stateHeart' => round($sh, 2),
    ];
}

echo "=== TESTS DE FRONTERA: SCORING CONTINUO ===\n\n";

// ============================================================
// 1. FRONTERA 75.0 vs 74.9
// ============================================================
echo "--- 1. Frontera 75.0 vs 74.9 ---\n";
$r75 = stateHeartForNeeds([75, 75, 75, 75]);
$r749 = stateHeartForNeeds([74.9, 74.9, 74.9, 74.9]);
$diff75 = abs($r75['stateHeart'] - $r749['stateHeart']);
info("75.0 → SH={$r75['stateHeart']}, score_nec={$r75['estado']['necesidades']}");
info("74.9 → SH={$r749['stateHeart']}, score_nec={$r749['estado']['necesidades']}");
info("Diferencia SH: $diff75");
ok($diff75 < 1.0, "75.0 vs 74.9: diferencia SH < 1.0 (real: $diff75)");
ok($diff75 > 0, "75.0 vs 74.9: hay diferencia mínima (real: $diff75)");

// ============================================================
// 2. FRONTERA 50.0 vs 49.9
// ============================================================
echo "\n--- 2. Frontera 50.0 vs 49.9 ---\n";
$r50 = stateHeartForNeeds([50, 50, 50, 50]);
$r499 = stateHeartForNeeds([49.9, 49.9, 49.9, 49.9]);
$diff50 = abs($r50['stateHeart'] - $r499['stateHeart']);
info("50.0 → SH={$r50['stateHeart']}, score_nec={$r50['estado']['necesidades']}");
info("49.9 → SH={$r499['stateHeart']}, score_nec={$r499['estado']['necesidades']}");
info("Diferencia SH: $diff50");
ok($diff50 < 1.0, "50.0 vs 49.9: diferencia SH < 1.0 (real: $diff50)");

// ============================================================
// 3. FRONTERA 25.0 vs 24.9
// ============================================================
echo "\n--- 3. Frontera 25.0 vs 24.9 ---\n";
$r25 = stateHeartForNeeds([25, 25, 25, 25]);
$r249 = stateHeartForNeeds([24.9, 24.9, 24.9, 24.9]);
$diff25 = abs($r25['stateHeart'] - $r249['stateHeart']);
info("25.0 → SH={$r25['stateHeart']}, score_nec={$r25['estado']['necesidades']}");
info("24.9 → SH={$r249['stateHeart']}, score_nec={$r249['estado']['necesidades']}");
info("Diferencia SH: $diff25");
ok($diff25 < 1.0, "25.0 vs 24.9: diferencia SH < 1.0 (real: $diff25)");

// ============================================================
// 4. ORDEN: 100 > 75 > 50 > 25 > 0
// ============================================================
echo "\n--- 4. Orden: 100 > 75 > 50 > 25 > 0 ---\n";
$r100 = stateHeartForNeeds([100, 100, 100, 100]);
$r0 = stateHeartForNeeds([0, 0, 0, 0]);
info("Todas 100 → SH={$r100['stateHeart']}");
info("Todas 75 → SH={$r75['stateHeart']}");
info("Todas 50 → SH={$r50['stateHeart']}");
info("Todas 25 → SH={$r25['stateHeart']}");
info("Todas 0 → SH={$r0['stateHeart']}");
ok($r100['stateHeart'] > $r75['stateHeart'], "100 > 75");
ok($r75['stateHeart'] > $r50['stateHeart'], "75 > 50");
ok($r50['stateHeart'] > $r25['stateHeart'], "50 > 25");
ok($r25['stateHeart'] > $r0['stateHeart'], "25 > 0");

// ============================================================
// 5. EMOCIONES: neutral = baseline razonable
// ============================================================
echo "\n--- 5. Emociones: neutral vs alegre vs triste ---\n";
$rNeutro = stateHeartForNeeds([75, 75, 75, 75], ['neutro', 'neutro', 'neutro']);
$rAlegre = stateHeartForNeeds([75, 75, 75, 75], ['alegre', 'alegre', 'alegre']);
$rTriste = stateHeartForNeeds([75, 75, 75, 75], ['triste', 'triste', 'triste']);
info("Neutro → SH={$rNeutro['stateHeart']}, emo={$rNeutro['estado']['emociones']}");
info("Alegre → SH={$rAlegre['stateHeart']}, emo={$rAlegre['estado']['emociones']}");
info("Triste → SH={$rTriste['stateHeart']}, emo={$rTriste['estado']['emociones']}");
ok($rAlegre['stateHeart'] > $rNeutro['stateHeart'], "Alegre > Neutro");
ok($rNeutro['stateHeart'] > $rTriste['stateHeart'], "Neutro > Triste");
ok($rNeutro['stateHeart'] >= 50, "Neutro produce SH ≥ 50 (baseline razonable)");

// ============================================================
// 6. UNA EMOCIÓN NEGATIVA AISLADA NO HUNDE EL PUEBLO
// ============================================================
echo "\n--- 6. Una emoción negativa aislada ---\n";
$r1Triste = stateHeartForNeeds([75, 75, 75, 75], ['triste', 'neutro', 'neutro']);
$r3Triste = stateHeartForNeeds([75, 75, 75, 75], ['triste', 'triste', 'triste']);
info("1 triste + 2 neutros → SH={$r1Triste['stateHeart']}");
info("3 tristes → SH={$r3Triste['stateHeart']}");
ok($r1Triste['stateHeart'] > $r3Triste['stateHeart'], "1 triste hunde menos que 3 tristes");
ok($r1Triste['stateHeart'] > 55, "1 triste aislada: SH > 55 (no hunde)");

// ============================================================
// 7. RELACIONES: baseline neutral, malas bajan, buenas suben
// ============================================================
echo "\n--- 7. Relaciones ---\n";
$rSinRel = stateHeartForNeeds([75, 75, 75, 75]);
$rBuenas = stateHeartForNeeds([75, 75, 75, 75], ['neutro', 'neutro', 'neutro'], ['0_1' => 60, '0_2' => 60, '1_2' => 60]);
$rMalas = stateHeartForNeeds([75, 75, 75, 75], ['neutro', 'neutro', 'neutro'], ['0_1' => -60, '0_2' => -60, '1_2' => -60]);
info("Sin relaciones → SH={$rSinRel['stateHeart']}, rel={$rSinRel['estado']['relaciones']}");
info("Buenas (+60) → SH={$rBuenas['stateHeart']}, rel={$rBuenas['estado']['relaciones']}");
info("Malas (-60) → SH={$rMalas['stateHeart']}, rel={$rMalas['estado']['relaciones']}");
ok($rSinRel['estado']['relaciones'] === 0.0, "Sin relaciones: score = 0.0 (neutral)");
ok($rBuenas['stateHeart'] > $rSinRel['stateHeart'], "Relaciones buenas suben SH");
ok($rMalas['stateHeart'] < $rSinRel['stateHeart'], "Relaciones malas bajan SH");

// ============================================================
// 8. SOBREEXTENSIÓN ALINEADA — partida nueva sin SE
// ============================================================
echo "\n--- 8. Sobreextensión con scoring continuo ---\n";
$shInfo = stateHeartForNeeds([75, 75, 75, 75]);
info("Partida nueva: heart=65, SH={$shInfo['stateHeart']}");
ok($shInfo['stateHeart'] >= 65, "stateHeart(" . $shInfo['stateHeart'] . ") ≥ heart(65): SH alineado, sin SE en partida nueva");

// ============================================================
// 9. JUGADOR ACTIVO: necesidades 80 sube SH
// ============================================================
echo "\n--- 9. Jugador activo: ganancias parcialmente retenidas ---\n";
// Con necesidades 80 (bien por encima de 75):
$rActivo = stateHeartForNeeds([80, 80, 80, 80]);
info("Necesidades 80 → SH={$rActivo['stateHeart']}");
ok($rActivo['stateHeart'] > 60, "Necesidades 80 → SH > 60: sobreextensión moderada");

// Con necesidades 75 (baseline alineado):
info("Necesidades 75 → SH={$r75['stateHeart']}");
ok($r75['stateHeart'] >= 65, "Necesidades 75 → SH ≥ 65: SH alineado con heart inicial");

// ============================================================
// 10. DETERIORO REAL SOSTENIDO SÍ BAJA STATEHEART
// ============================================================
echo "\n--- 10. Deterioro sostenido ---\n";
$rAlto = stateHeartForNeeds([90, 90, 90, 90]);
$rMedio = stateHeartForNeeds([60, 60, 60, 60]);
$rBajo = stateHeartForNeeds([30, 30, 30, 30]);
info("90 → SH={$rAlto['stateHeart']}");
info("60 → SH={$rMedio['stateHeart']}");
info("30 → SH={$rBajo['stateHeart']}");
ok($rAlto['stateHeart'] > $rMedio['stateHeart'], "90 > 60");
ok($rMedio['stateHeart'] > $rBajo['stateHeart'], "60 > 30");

// ============================================================
// RESUMEN
// ============================================================
echo "\n" . str_repeat('=', 50) . "\n";
if ($failures === 0) {
    echo "TODOS LOS TESTS DE FRONTERA PASARON\n";
} else {
    echo "FALLOS: $failures\n";
}
echo str_repeat('=', 50) . "\n";

exit($failures > 0 ? 1 : 0);
