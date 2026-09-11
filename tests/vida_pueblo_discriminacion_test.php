<?php
/**
 * Tests: discriminación farmer vs competente
 * Verifica que el sistema distingue entre farmer y competente
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\{CalibracionConfig, NecesidadEstado, VidaPuebloEngine, RngService, PartidaService};

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$cfg = VidaPuebloEngine::cfg($cal);
$failures = 0;

function ok(bool $c, string $m): void {
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) $failures++;
}

function calc(array $p): array {
    global $cal, $cfg;
    $e = VidaPuebloEngine::calcularEstadoPueblo($p, $cal);
    $sh = VidaPuebloEngine::stateHeart($e, $cfg);
    return ['need_score' => round($e['necesidades'],4), 'emotional_score' => round($e['emociones'],4), 'relationship_score' => round($e['relaciones'],4), 'health_score' => round($e['score'],4), 'stateHeart' => round($sh,2)];
}

function promNec(array $p): float {
    $t=0;$c=0;
    foreach($p['residentes'] as $r){if(($r['presencia']??'')!=='residente')continue;foreach(NecesidadEstado::obtener($r) as $n){$t+=$n['valor'];$c++;}}
    return $c>0?$t/$c:75;
}

// ============================================================
// 1. Farmer puede sufrir SE frecuente
// ============================================================
echo "--- 1. Farmer: SE frecuente ---\n";
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'test-farmer-se');
VidaPuebloEngine::ensure($p, $cal);
VidaPuebloEngine::aplicar($p, 10, ['causa' => VidaPuebloEngine::CAUSA_LAB, 'origen' => VidaPuebloEngine::ORIGEN_LAB, 'atribuible_celestine' => true, 'lab' => true], $cal);
$heartAlto = VidaPuebloEngine::valor($p);
$sh = calc($p);
ok($heartAlto > $sh['stateHeart'], "Farmer con heart alto ($heartAlto) > SH ({$sh['stateHeart']}): gap existe");
$se = VidaPuebloEngine::aplicarSobreextension($p, $cal);
ok($se['delta_aplicado'] < 0, "Farmer recibe penalty ({$se['delta_aplicado']})");

// ============================================================
// 2. Competente NO sufre SE estructural diaria
// ============================================================
echo "\n--- 2. Competente: sin SE estructural ---\n";
$p2 = $svc->nuevaPartida('juego_v1', 'test-comp-sin-se');
VidaPuebloEngine::ensure($p2, $cal);
// Heart en 65 (inicial), SH baseline 65.2
$heart2 = VidaPuebloEngine::valor($p2);
$sh2 = calc($p2);
ok($heart2 <= $sh2['stateHeart'] + 1, "Competente heart ($heart2) ≈ SH ({$sh2['stateHeart']}): sin gap significativo");
$se2 = VidaPuebloEngine::aplicarSobreextension($p2, $cal);
ok($se2['delta_aplicado'] === 0, "Competente sin penalty en baseline (delta={$se2['delta_aplicado']})");

// ============================================================
// 3. Cuidado real sube stateHeart
// ============================================================
echo "\n--- 3. Cuidado real sube SH ---\n";
$p3 = $svc->nuevaPartida('juego_v1', 'test-cuidado-sh');
VidaPuebloEngine::ensure($p3, $cal);
$sh3a = calc($p3);
// Simular visita a lugar (recuperación)
foreach ($p3['residentes'] as &$res) {
    NecesidadEstado::ensureResidente($res);
    NecesidadEstado::aplicarRecuperacion($res, ['social' => 'principal', 'actividad' => 'principal', 'diversion' => 'secundaria', 'calma' => 'secundaria'], true, false, $cal);
}
unset($res);
$sh3b = calc($p3);
ok($sh3b['stateHeart'] > $sh3a['stateHeart'], "SH sube tras cuidado ({$sh3a['stateHeart']} → {$sh3b['stateHeart']})");

// ============================================================
// 4. Acción que solo infla Heart puede crear gap
// ============================================================
echo "\n--- 4. Acción solo Heart crea gap ---\n";
$p4 = $svc->nuevaPartida('juego_v1', 'test-heart-gap');
VidaPuebloEngine::ensure($p4, $cal);
$sh4a = calc($p4);
VidaPuebloEngine::aplicar($p4, 5, ['causa' => VidaPuebloEngine::CAUSA_LAB, 'origen' => VidaPuebloEngine::ORIGEN_LAB, 'atribuible_celestine' => true, 'lab' => true], $cal);
$heart4 = VidaPuebloEngine::valor($p4);
$sh4b = calc($p4);
ok($heart4 > $sh4b['stateHeart'], "Heart ($heart4) > SH ({$sh4b['stateHeart']}): gap creado por acción solo-Heart");
ok($sh4b['stateHeart'] === $sh4a['stateHeart'], "SH no cambia por acción solo-Heart ({$sh4a['stateHeart']} = {$sh4b['stateHeart']})");

// ============================================================
// 5. Deterioro sostenido baja stateHeart
// ============================================================
echo "\n--- 5. Deterioro baja SH ---\n";
$p5 = $svc->nuevaPartida('juego_v1', 'test-det-sh');
VidaPuebloEngine::ensure($p5, $cal);
$sh5a = calc($p5);
foreach ($p5['residentes'] as &$res) {
    NecesidadEstado::ensureResidente($res);
    for ($h = 0; $h < 14; $h++) {
        NecesidadEstado::aplicarDecay($res, $cal);
    }
}
unset($res);
$sh5b = calc($p5);
ok($sh5b['stateHeart'] < $sh5a['stateHeart'], "SH baja por decay ({$sh5a['stateHeart']} → {$sh5b['stateHeart']})");

// ============================================================
// 6. Misión sube Heart sin subir SH
// ============================================================
echo "\n--- 6. Misión: Heart↑, SH=0 ---\n";
$p6 = $svc->nuevaPartida('juego_v1', 'test-mision-sh');
VidaPuebloEngine::ensure($p6, $cal);
$sh6a = calc($p6);
VidaPuebloEngine::aplicar($p6, 2, ['causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA, 'origen' => VidaPuebloEngine::ORIGEN_LAB, 'atribuible_celestine' => true, 'positivo_valido_latido' => true, 'lab' => true], $cal);
$sh6b = calc($p6);
ok($sh6b['stateHeart'] === $sh6a['stateHeart'], "Misión NO cambia SH ({$sh6a['stateHeart']} = {$sh6b['stateHeart']})");

// ============================================================
// 7. Misión cumple Heart up, needs unchanged
// ============================================================
echo "\n--- 7. Misión: needs unchanged ---\n";
$nec6a = promNec($p6);
VidaPuebloEngine::aplicar($p6, 2, ['causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA, 'origen' => VidaPuebloEngine::ORIGEN_LAB, 'atribuible_celestine' => true, 'positivo_valido_latido' => true, 'lab' => true], $cal);
$nec6b = promNec($p6);
ok(abs($nec6b - $nec6a) < 0.1, "Misión NO cambia necesidades ($nec6a → $nec6b)");

// ============================================================
// 8. stateHeart mínimo/máximo
// ============================================================
echo "\n--- 8. stateHeart rango ---\n";
$cfg8 = VidaPuebloEngine::cfg($cal);
$shMin = VidaPuebloEngine::stateHeart(['score' => -1.0, 'necesidades' => 0, 'emociones' => 0, 'relaciones' => 0], $cfg8);
$shMax = VidaPuebloEngine::stateHeart(['score' => 1.0, 'necesidades' => 0, 'emociones' => 0, 'relaciones' => 0], $cfg8);
ok($shMin === 18.0, "stateHeart(-1) = 18 (min configurado)");
ok($shMax === 90.0, "stateHeart(+1) = 90 (max configurado)");

echo "\n";
exit($failures > 0 ? 1 : 0);
