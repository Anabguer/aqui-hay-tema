<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\{CalibracionConfig, FeatureConfig, NecesidadEstado, PartidaService, VidaPuebloEngine};

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

function info(string $m): void
{
    echo "  INFO: $m\n";
}

$cal = CalibracionConfig::load($root);
$svc = new PartidaService($root);

function partidaEst(): array
{
    global $svc, $cal;
    $p = $svc->nuevaPartida('juego_v1', 'estancamiento-test-' . substr(md5(uniqid('', true)), 0, 8));
    VidaPuebloEngine::ensure($p, $cal);
    $p['features'][VidaPuebloEngine::FLAG] = true;
    return $p;
}

function cerrarDia(array &$p): array
{
    global $cal;
    $se = VidaPuebloEngine::aplicarSobreextension($p, $cal);
    $est = VidaPuebloEngine::aplicarEstancamiento($p, $cal);
    return ['sobreextension' => $se, 'estancamiento' => $est];
}

function forzarHeart(array &$p, int $valor): void
{
    global $cal;
    VidaPuebloEngine::aplicar($p, $valor - VidaPuebloEngine::valor($p), [
        'causa' => VidaPuebloEngine::CAUSA_LAB,
        'origen' => VidaPuebloEngine::ORIGEN_LAB,
        'atribuible_celestine' => true,
        'lab' => true,
    ], $cal);
}

/**
 * Ajusta el valor de TODAS las necesidades de TODOS los residentes activos.
 */
function setNecesidades(array &$p, int $valor): void
{
    $residentes = $p['residentes'] ?? [];
    foreach (array_keys($residentes) as $rid) {
        $res = &$p['residentes'][$rid];
        if (($res['presencia'] ?? '') !== 'residente') {
            continue;
        }
        NecesidadEstado::ensureResidente($res);
        foreach (NecesidadEstado::TODAS as $nec) {
            $res['runtime']['necesidades'][$nec]['valor'] = $valor;
            if ($valor >= 75) {
                $res['runtime']['necesidades'][$nec]['banda'] = 'bien';
            } elseif ($valor >= 50) {
                $res['runtime']['necesidades'][$nec]['banda'] = 'le_vendria_bien';
            } elseif ($valor >= 25) {
                $res['runtime']['necesidades'][$nec]['banda'] = 'lo_necesita';
            } else {
                $res['runtime']['necesidades'][$nec]['banda'] = 'en_rojo';
            }
        }
    }
}

echo "=== TESTS: ESTANCAMIENTO DEL PUEBLO ===\n\n";

// ============================================================
// A. Pueblo sano / stateHeart > 60 → nunca activa presión
// ============================================================
echo "--- A: Pueblo sano ---\n";
$pA = partidaEst();
$estadoA = VidaPuebloEngine::calcularEstadoPueblo($pA, $cal);
$shA = VidaPuebloEngine::stateHeart($estadoA, VidaPuebloEngine::cfg($cal));
info("SH inicial = " . round($shA, 1));

for ($d = 0; $d < 10; $d++) {
    $pA['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pA, 80); // bien
    cerrarDia($pA);
}
$estA = $pA['vida_pueblo']['estancamiento'];
ok($estA['dias_bajo_umbral'] === 0, 'A: pueblo sano: dias_bajo_umbral = 0');
ok($estA['activo'] === false, 'A: pueblo sano: estancamiento no activo');

// ============================================================
// B. stateHeart <= 60 durante menos de 5 días → no activa presión
// ============================================================
echo "\n--- B: Período corto bajo umbral ---\n";
$pB = partidaEst();
setNecesidades($pB, 20); // en_rojo → SH bajo
$estadoB = VidaPuebloEngine::calcularEstadoPueblo($pB, $cal);
$shB = VidaPuebloEngine::stateHeart($estadoB, VidaPuebloEngine::cfg($cal));
info("SH con necesidades en rojo = " . round($shB, 1));

for ($d = 0; $d < 4; $d++) {
    $pB['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pB, 20);
    cerrarDia($pB);
}
$estB = $pB['vida_pueblo']['estancamiento'];
ok($estB['dias_bajo_umbral'] >= 1, 'B: al menos 1 día bajo umbral (' . $estB['dias_bajo_umbral'] . ')');
ok($estB['activo'] === false, 'B: <5 días: estancamiento NO activo');

// ============================================================
// C. stateHeart <= 60 durante 5+ días sin mejora → activa -1/día
// ============================================================
echo "\n--- C: 5+ días sin mejora ---\n";
$pC = partidaEst();
setNecesidades($pC, 20);
$heartAntes = VidaPuebloEngine::valor($pC);
info("Heart antes: $heartAntes");

for ($d = 0; $d < 8; $d++) {
    $pC['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pC, 20);
    cerrarDia($pC);
}
$heartDespues = VidaPuebloEngine::valor($pC);
$estC = $pC['vida_pueblo']['estancamiento'];
info("Heart después: $heartDespues (delta: " . ($heartDespues - $heartAntes) . ")");
info("Días bajo umbral: " . $estC['dias_bajo_umbral']);
info("Activo: " . ($estC['activo'] ? 'SÍ' : 'NO'));
ok($estC['activo'] === true, 'C: 5+ días sin mejora: estancamiento ACTIVO');
ok($heartDespues < $heartAntes, 'C: heart bajó por presión (' . ($heartDespues - $heartAntes) . ')');

// ============================================================
// D. Oscilación diaria sin tendencia real: 58→60→58→60→58
// ============================================================
echo "\n--- D: Oscilación sin tendencia ---\n";
$pD = partidaEst();
// Simular SH oscilante alrededor de 55-60
for ($d = 0; $d < 10; $d++) {
    $pD['reloj']['dia_pueblo'] = $d + 1;
    $target = ($d % 2 === 0) ? 28 : 32; // Oscilar entre lo_necesita-bajo y lo_necesita-medio
    setNecesidades($pD, $target);
    cerrarDia($pD);
}
$estD = $pD['vida_pueblo']['estancamiento'];
info("Días bajo umbral: " . $estD['dias_bajo_umbral']);
info("Activo: " . ($estD['activo'] ? 'SÍ' : 'NO'));
ok($estD['dias_bajo_umbral'] >= 5, 'D: oscilación cuenta como estancamiento (' . $estD['dias_bajo_umbral'] . ' días)');

// ============================================================
// E. Mejora real: desactiva presión
// ============================================================
echo "\n--- E: Mejora real desactiva presión ---\n";
$pE = partidaEst();
// Activar estancamiento primero
for ($d = 0; $d < 8; $d++) {
    $pE['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pE, 20);
    cerrarDia($pE);
}
$estE1 = $pE['vida_pueblo']['estancamiento'];
ok($estE1['activo'] === true, 'E: estancamiento activado correctamente');

// Mejorar gradualmente
for ($d = 0; $d < 8; $d++) {
    $pE['reloj']['dia_pueblo'] = 100 + $d;
    $target = 20 + ($d * 8); // 20→28→36→44→52→60→68→76
    setNecesidades($pE, $target);
    cerrarDia($pE);
}
$estE2 = $pE['vida_pueblo']['estancamiento'];
$shE2 = VidaPuebloEngine::stateHeart(
    VidaPuebloEngine::calcularEstadoPueblo($pE, $cal),
    VidaPuebloEngine::cfg($cal)
);
info("SH final: " . round($shE2, 1));
info("Después de mejora — Activo: " . ($estE2['activo'] ? 'SÍ' : 'NO'));
ok($estE2['activo'] === false, 'E: mejora real desactiva estancamiento');

// ============================================================
// F. Recuperación desde heart 10
// ============================================================
echo "\n--- F: Recuperación desde HF=10 ---\n";
$pF = partidaEst();
forzarHeart($pF, 10);
$infoF = VidaPuebloEngine::vista($pF, $cal);
ok($infoF['corazon_pct'] === 10, 'F: heart forzado a 10');
ok($infoF['banda'] === 'critico', 'F: en banda crítico');

// Simular recuperación
forzarHeart($pF, 65);
for ($d = 0; $d < 5; $d++) {
    $pF['reloj']['dia_pueblo'] = 200 + $d;
    setNecesidades($pF, 75 + ($d * 5));
    cerrarDia($pF);
}
$infoF2 = VidaPuebloEngine::vista($pF, $cal);
ok($infoF2['corazon_pct'] >= 50, 'F: recuperación a HF≥50 (' . $infoF2['corazon_pct'] . ')');
ok($infoF2['banda'] !== 'critico', 'F: salió de crítico');

// ============================================================
// G. Jugador mínimo: actividad superficial sin mejora real → ALERTA
// ============================================================
echo "\n--- G: Jugador mínimo → ALERTA ---\n";
$pG = partidaEst();
for ($d = 0; $d < 40; $d++) {
    $pG['reloj']['dia_pueblo'] = $d + 1;
    $target = max(15, 75 - ($d * 2)); // Decay gradual
    setNecesidades($pG, $target);
    cerrarDia($pG);
}
$estG = $pG['vida_pueblo']['estancamiento'];
$heartG = VidaPuebloEngine::valor($pG);
$shG = VidaPuebloEngine::stateHeart(
    VidaPuebloEngine::calcularEstadoPueblo($pG, $cal),
    VidaPuebloEngine::cfg($cal)
);
info("Heart: $heartG, SH: " . round($shG, 1));
info("Estancamiento activo: " . ($estG['activo'] ? 'SÍ' : 'NO'));
ok($estG['activo'] === true, 'G: estancamiento activo por falta de mejora real');
ok($heartG < 65, 'G: heart bajó del inicial (' . $heartG . ')');

// ============================================================
// H. No producir GO artificial cuando stateHeart está mejorando
// ============================================================
echo "\n--- H: Sin GO artificial con mejora ---\n";
$pH = partidaEst();
forzarHeart($pH, 25);
$infoH = VidaPuebloEngine::vista($pH, $cal);
ok($infoH['corazon_pct'] === 25, 'H: heart inicial = 25');

for ($d = 0; $d < 10; $d++) {
    $pH['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pH, min(100, 30 + ($d * 8)));
    cerrarDia($pH);
}
$heartH = VidaPuebloEngine::valor($pH);
$estH = $pH['vida_pueblo']['estancamiento'];
info("Heart final: $heartH");
info("Estancamiento activo: " . ($estH['activo'] ? 'SÍ' : 'NO'));
ok($heartH > 0, 'H: no hubo game over');
ok($estH['activo'] === false, 'H: estancamiento no se activó con mejora');

// ============================================================
// I. Catch-up: comportamiento durante ausencia
// ============================================================
echo "\n--- I: Catch-up / ausencia ---\n";
$pI = partidaEst();
forzarHeart($pI, 40);
setNecesidades($pI, 20);
for ($d = 0; $d < 8; $d++) {
    $pI['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pI, 20);
    cerrarDia($pI);
}
$estI1 = $pI['vida_pueblo']['estancamiento'];
ok($estI1['activo'] === true, 'I: estancamiento activado antes de ausencia');

// Vista no debería resetear el estado
$infoI = VidaPuebloEngine::vista($pI, $cal);
ok($infoI['estancamiento']['activo'] === true, 'I: estancamiento persiste tras vista');

// ============================================================
// J. Vista expone datos de estancamiento
// ============================================================
echo "\n--- J: Vista expone estancamiento ---\n";
$pJ = partidaEst();
forzarHeart($pJ, 30);
setNecesidades($pJ, 20);
for ($d = 0; $d < 7; $d++) {
    $pJ['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pJ, 20);
    cerrarDia($pJ);
}
$infoJ = VidaPuebloEngine::vista($pJ, $cal);
ok(isset($infoJ['estancamiento']), 'J: vista incluye estancamiento');
ok($infoJ['estancamiento']['activo'] === true, 'J: estancamiento.activo = true');
ok(is_int($infoJ['estancamiento']['dias_bajo_umbral']), 'J: dias_bajo_umbral es entero');
ok($infoJ['estancamiento']['dias_bajo_umbral'] > 0, 'J: dias_bajo_umbral > 0');

// ============================================================
// K. Estado nuevo incluye estancamiento
// ============================================================
echo "\n--- K: Estado nuevo ---\n";
$pK = partidaEst();
$estK = $pK['vida_pueblo']['estancamiento'];
ok(is_array($estK), 'K: estancamiento existe en estado nuevo');
ok($estK['activo'] === false, 'K: estancamiento.inactivo al inicio');
ok($estK['dias_bajo_umbral'] === 0, 'K: dias_bajo_umbral = 0 al inicio');
ok(is_array($estK['historial_sh']), 'K: historial_sh es array');

// ============================================================
// RESUMEN
// ============================================================
echo "\n" . str_repeat('=', 50) . "\n";
if ($failures === 0) {
    echo "TODOS LOS TESTS PASARON\n";
} else {
    echo "FALLOS: $failures\n";
}
echo str_repeat('=', 50) . "\n";

exit($failures > 0 ? 1 : 0);
