<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\{CalibracionConfig, FeatureConfig, PartidaService, RelojOperations, VidaPuebloEngine};

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
$svc = new PartidaService($root);

// ============================================================
// 1. Sin residentes activos → no aplica penalty
// ============================================================
$p1 = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10, 'ultima_sesion_iso' => null],
    'features' => [VidaPuebloEngine::FLAG => true],
];
VidaPuebloEngine::ensure($p1, $cal);
VidaPuebloEngine::aplicar($p1, 30, [
    'causa' => VidaPuebloEngine::CAUSA_LAB,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
], $cal);
$antes1 = VidaPuebloEngine::valor($p1);
$r1 = VidaPuebloEngine::aplicarSobreextension($p1, $cal);
ok($r1['ok'] === true, 'sin residentes: retorna ok');
ok($r1['delta_aplicado'] === 0, 'sin residentes: delta es 0');
ok(VidaPuebloEngine::valor($p1) === $antes1, 'sin residentes: corazón no cambia');

// ============================================================
// 2. Con residentes, corazón vs stateHeart continuo
//    needs=75 → score=0.5, neutro=0.2, sin relaciones=0
//    health=0.5×0.5+0.2×0.3=0.31, SH=50+0.31×40=62.4
//    heart(65) > stateHeart(62.4) → pequeña sobreextensión
// ============================================================
$p2 = $svc->nuevaPartida('juego_v1', 'sobreext-test-bajo');
VidaPuebloEngine::ensure($p2, $cal);
$antes2 = VidaPuebloEngine::valor($p2);
$estado2 = VidaPuebloEngine::calcularEstadoPueblo($p2, $cal);
$cfg2 = VidaPuebloEngine::cfg($cal);
$sh2 = VidaPuebloEngine::stateHeart($estado2, $cfg2);
ok($sh2 < $antes2, 'corazón(' . $antes2 . ') > stateHeart(' . round($sh2, 1) . '): pequeña sobreextensión en partida nueva');
$r2 = VidaPuebloEngine::aplicarSobreextension($p2, $cal);
ok($r2['ok'] === true, 'bajo stateHeart: retorna ok');
ok($r2['delta_aplicado'] < 0, 'bajo stateHeart: penalty pequeño aplicado (' . $r2['delta_aplicado'] . ')');

// ============================================================
// 3. Con residentes, corazón alto → penalty se aplica
// ============================================================
$p3 = $svc->nuevaPartida('juego_v1', 'sobreext-test-alto');
VidaPuebloEngine::ensure($p3, $cal);
// Forzar corazón alto via lab
VidaPuebloEngine::aplicar($p3, 35, [
    'causa' => VidaPuebloEngine::CAUSA_LAB,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
    'lab' => true,
], $cal);
$antes3 = VidaPuebloEngine::valor($p3);
$estado3 = VidaPuebloEngine::calcularEstadoPueblo($p3, $cal);
$cfg3 = VidaPuebloEngine::cfg($cal);
$sh3 = VidaPuebloEngine::stateHeart($estado3, $cfg3);
ok($antes3 > $sh3, 'corazón(' . $antes3 . ') > stateHeart(' . round($sh3, 1) . '): con sobreextensión');
$r3 = VidaPuebloEngine::aplicarSobreextension($p3, $cal);
ok($r3['ok'] === true, 'alto stateHeart: retorna ok');
ok($r3['delta_aplicado'] < 0, 'alto stateHeart: delta es negativo (' . ($r3['delta_aplicado'] ?? 0) . ')');
ok(VidaPuebloEngine::valor($p3) < $antes3, 'corazón baja tras sobreextensión');

// ============================================================
// 4. Factor configurable
// ============================================================
$cfg0 = array_merge($cal, ['vida_pueblo' => ['sobreextension_factor' => 0]]);
$p4 = $svc->nuevaPartida('juego_v1', 'sobreext-test-factor0');
VidaPuebloEngine::ensure($p4, $cal);
VidaPuebloEngine::aplicar($p4, 35, [
    'causa' => VidaPuebloEngine::CAUSA_LAB,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
    'lab' => true,
], $cal);
$antes4 = VidaPuebloEngine::valor($p4);
$r4 = VidaPuebloEngine::aplicarSobreextension($p4, $cfg0);
ok($r4['delta_aplicado'] === 0, 'factor 0: no aplica penalty');

// ============================================================
// 5. Feature flag off → no aplica
// ============================================================
$p5 = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10, 'ultima_sesion_iso' => null],
    'features' => [VidaPuebloEngine::FLAG => false],
];
VidaPuebloEngine::ensure($p5, $cal);
$r5 = VidaPuebloEngine::aplicarSobreextension($p5, $cal);
ok($r5['ok'] === false, 'feature flag off: retorna ok=false');

// ============================================================
// 6. Cálculo de estado del pueblo
// ============================================================
$p6 = $svc->nuevaPartida('juego_v1', 'sobreext-test-estado');
$estado6 = VidaPuebloEngine::calcularEstadoPueblo($p6, $cal);
ok($estado6['score'] >= -1 && $estado6['score'] <= 1, 'score en rango [-1, 1]');
ok($estado6['necesidades'] >= -1 && $estado6['necesidades'] <= 1, 'necesidades en rango [-1, 1]');
ok($estado6['emociones'] >= -1 && $estado6['emociones'] <= 1, 'emociones en rango [-1, 1]');
ok($estado6['relaciones'] >= -1 && $estado6['relaciones'] <= 1, 'relaciones en rango [-1, 1]');

// ============================================================
// 7. stateHeart mapea correctamente
// ============================================================
$cfg7 = VidaPuebloEngine::cfg($cal);
$shMin = VidaPuebloEngine::stateHeart(['score' => -1.0, 'necesidades' => 0, 'emociones' => 0, 'relaciones' => 0], $cfg7);
$shMax = VidaPuebloEngine::stateHeart(['score' => 1.0, 'necesidades' => 0, 'emociones' => 0, 'relaciones' => 0], $cfg7);
$shZero = VidaPuebloEngine::stateHeart(['score' => 0.0, 'necesidades' => 0, 'emociones' => 0, 'relaciones' => 0], $cfg7);
ok($shMin === 10.0, 'stateHeart(-1) = 10');
ok($shMax === 90.0, 'stateHeart(+1) = 90');
ok($shZero === 50.0, 'stateHeart(0) = 50');

// ============================================================
// 8. Game over no se activa por sobreextensión
// ============================================================
$p8 = $svc->nuevaPartida('juego_v1', 'sobreext-test-go');
VidaPuebloEngine::ensure($p8, $cal);
VidaPuebloEngine::aplicar($p8, 35, [
    'causa' => VidaPuebloEngine::CAUSA_LAB,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
    'lab' => true,
], $cal);
// Llevar a 0
VidaPuebloEngine::aplicar($p8, -100, [
    'causa' => VidaPuebloEngine::CAUSA_LAB,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
], $cal);
ok(VidaPuebloEngine::valor($p8) === 0, 'corazón llega a 0');
// Sobreextensión no debería empeorar el game over
$r8 = VidaPuebloEngine::aplicarSobreextension($p8, $cal);
ok($r8['ok'] === true, 'sobreextensión en heart=0: retorna ok');

// ============================================================
// 9. Clamp funciona tras sobreextensión
// ============================================================
$p9 = $svc->nuevaPartida('juego_v1', 'sobreext-test-clamp');
VidaPuebloEngine::ensure($p9, $cal);
// Heart en 0, force a 0
VidaPuebloEngine::aplicar($p9, -65, [
    'causa' => VidaPuebloEngine::CAUSA_LAB,
    'origen' => VidaPuebloEngine::ORIGEN_LAB,
    'atribuible_celestine' => true,
], $cal);
ok(VidaPuebloEngine::valor($p9) === 0, 'heart en 0 antes de clamp test');
$r9 = VidaPuebloEngine::aplicarSobreextension($p9, $cal);
ok(VidaPuebloEngine::valor($p9) >= 0, 'heart no baja de 0 tras sobreextensión');

// ============================================================
// 10. SimuladorVidaPueblo aplica sobreextensión
// ============================================================
use AquiHayTema\Engine\SimuladorVidaPueblo;

$lab = SimuladorVidaPueblo::ejecutar($root, [7], 2, 'sobreext-sim-test');
$g7 = $lab['por_perfil']['G']['por_horizonte']['7'];
// Con sobreextensión, el perfil G no debería llegar a 99 de forma estable
// (max puede tocar 99 dentro del día, pero final baja con sobreextensión)
ok(($g7['final_media'] ?? 0) < 99, 'simulador G 7d final < 99 con sobreextensión (' . round((float)($g7['final_media'] ?? 0), 1) . ')');

// Perfil A (balance): el final debería estar por debajo del máximo teórico 90
$a7 = $lab['por_perfil']['A']['por_horizonte']['7'];
ok(($a7['final_media'] ?? 0) < 90, 'simulador A 7d final < 90 con sobreextensión (' . round((float)($a7['final_media'] ?? 0), 1) . ')');

echo "\n";
exit($failures > 0 ? 1 : 0);
