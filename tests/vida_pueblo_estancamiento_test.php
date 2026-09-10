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
    setNecesidades($pA, 80);
    cerrarDia($pA);
}
$estA = $pA['vida_pueblo']['estancamiento'];
ok($estA['dias_bajo_umbral'] === 0, 'A: pueblo sano: dias_bajo_umbral = 0');
ok($estA['activo'] === false, 'A: pueblo sano: estancamiento no activo');

// ============================================================
// B. GRACE PERIOD: day-by-day boundary test
// Día 1-5: no activa. Día 6: SÍ activa.
// ============================================================
echo "\n--- B: Grace period día a día (boundary exacto) ---\n";
$pB = partidaEst();
setNecesidades($pB, 20);

for ($d = 1; $d <= 8; $d++) {
    $pB['reloj']['dia_pueblo'] = $d;
    setNecesidades($pB, 20);
    cerrarDia($pB);
    $est = $pB['vida_pueblo']['estancamiento'];
    $heart = VidaPuebloEngine::valor($pB);
    info("Día $d: dias_bajo={$est['dias_bajo_umbral']} activo=" . ($est['activo'] ? 'SÍ' : 'NO') . " heart=$heart");

    if ($d <= 5) {
        ok($est['activo'] === false, "B: día $d: estancamiento NO activo (grace)");
    }
    if ($d === 6) {
        ok($est['activo'] === true, "B: día 6: estancamiento SÍ activo (grace expiró)");
    }
}

// ============================================================
// C. 5+ días sin mejora → activa -1/día
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
ok($estC['activo'] === true, 'C: estancamiento ACTIVO tras 8 días');
ok($heartDespues < $heartAntes, 'C: heart bajó por presión (' . ($heartDespues - $heartAntes) . ')');

// ============================================================
// D. Oscilación diaria sin tendencia real
// ============================================================
echo "\n--- D: Oscilación sin tendencia ---\n";
$pD = partidaEst();
for ($d = 0; $d < 10; $d++) {
    $pD['reloj']['dia_pueblo'] = $d + 1;
    $target = ($d % 2 === 0) ? 28 : 32;
    setNecesidades($pD, $target);
    cerrarDia($pD);
}
$estD = $pD['vida_pueblo']['estancamiento'];
ok($estD['dias_bajo_umbral'] >= 5, 'D: oscilación cuenta como estancamiento (' . $estD['dias_bajo_umbral'] . ' días)');
ok($estD['activo'] === true, 'D: estancamiento activo por oscilación');

// ============================================================
// E. Mejora real desactiva presión
// ============================================================
echo "\n--- E: Mejora real desactiva presión ---\n";
$pE = partidaEst();
for ($d = 0; $d < 8; $d++) {
    $pE['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pE, 20);
    cerrarDia($pE);
}
ok($pE['vida_pueblo']['estancamiento']['activo'] === true, 'E: estancamiento activado');

for ($d = 0; $d < 8; $d++) {
    $pE['reloj']['dia_pueblo'] = 100 + $d;
    setNecesidades($pE, 20 + ($d * 8));
    cerrarDia($pE);
}
ok($pE['vida_pueblo']['estancamiento']['activo'] === false, 'E: mejora desactiva estancamiento');

// ============================================================
// F. Recuperación desde heart 10
// ============================================================
echo "\n--- F: Recuperación desde HF=10 ---\n";
$pF = partidaEst();
forzarHeart($pF, 10);
ok(VidaPuebloEngine::vista($pF, $cal)['corazon_pct'] === 10, 'F: heart forzado a 10');
ok(VidaPuebloEngine::vista($pF, $cal)['banda'] === 'critico', 'F: en banda crítico');

forzarHeart($pF, 65);
for ($d = 0; $d < 5; $d++) {
    $pF['reloj']['dia_pueblo'] = 200 + $d;
    setNecesidades($pF, 75 + ($d * 5));
    cerrarDia($pF);
}
$infoF = VidaPuebloEngine::vista($pF, $cal);
ok($infoF['corazon_pct'] >= 50, 'F: recuperación a HF≥50 (' . $infoF['corazon_pct'] . ')');
ok($infoF['banda'] !== 'critico', 'F: salió de crítico');

// ============================================================
// G. Jugador mínimo → ALERTA
// ============================================================
echo "\n--- G: Jugador mínimo → ALERTA ---\n";
$pG = partidaEst();
for ($d = 0; $d < 40; $d++) {
    $pG['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pG, max(15, 75 - ($d * 2)));
    cerrarDia($pG);
}
$estG = $pG['vida_pueblo']['estancamiento'];
ok($estG['activo'] === true, 'G: estancamiento activo por falta de mejora real');
ok(VidaPuebloEngine::valor($pG) < 65, 'G: heart bajó del inicial (' . VidaPuebloEngine::valor($pG) . ')');

// ============================================================
// H. Sin GO artificial con mejora
// ============================================================
echo "\n--- H: Sin GO artificial con mejora ---\n";
$pH = partidaEst();
forzarHeart($pH, 25);
for ($d = 0; $d < 10; $d++) {
    $pH['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pH, min(100, 30 + ($d * 8)));
    cerrarDia($pH);
}
ok(VidaPuebloEngine::valor($pH) > 0, 'H: no hubo game over');
ok($pH['vida_pueblo']['estancamiento']['activo'] === false, 'H: estancamiento no se activó');

// ============================================================
// I. Catch-up: ausencia no acumula presión
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
ok($pI['vida_pueblo']['estancamiento']['activo'] === true, 'I: estancamiento activado antes de ausencia');

// Simular que el jugador estuvo ausente (no se llama cerrarDia)
// El estado no cambia — el estancamiento persiste pero no acumula días nuevos
$antesVista = VidaPuebloEngine::vista($pI, $cal);
ok($antesVista['estancamiento']['activo'] === true, 'I: estancamiento persiste sin tick');

// Al volver: siguiente cerrarDia re-evalúa normalmente
$pI['reloj']['dia_pueblo'] = 100;
setNecesidades($pI, 20);
cerrarDia($pI);
ok($pI['vida_pueblo']['estancamiento']['activo'] === true, 'I: al volver, estancamiento sigue activo');

// Si el jugador mejora, se desactiva
$pI['reloj']['dia_pueblo'] = 101;
setNecesidades($pI, 80);
cerrarDia($pI);
ok($pI['vida_pueblo']['estancamiento']['activo'] === false, 'I: mejora al volver desactiva estancamiento');

// ============================================================
// J. Vista expone estancamiento
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
// L. FEEDBACK: primera activación → notificar=true
// ============================================================
echo "\n--- L: Feedback: notificar en primera activación ---\n";
$pL = partidaEst();
forzarHeart($pL, 30);
setNecesidades($pL, 20);

// Avanzar hasta activación (7 días)
for ($d = 0; $d < 7; $d++) {
    $pL['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pL, 20);
    cerrarDia($pL);
}
ok($pL['vida_pueblo']['estancamiento']['activo'] === true, 'L: estancamiento activado');

// Primera vista: notificar debe ser true
$infoL1 = VidaPuebloEngine::vista($pL, $cal);
ok(($infoL1['estancamiento']['notificar'] ?? false) === true, 'L: primera vista → notificar=true');
ok(($infoL1['estancamiento']['dia_activacion'] ?? 0) > 0, 'L: dia_activacion expuesto');

// Segunda vista: notificar sigue true (server no puede mutar por copy-value)
// La deduplication ocurre en JS con Set por dia_activacion
$infoL2 = VidaPuebloEngine::vista($pL, $cal);
ok(($infoL2['estancamiento']['notificar'] ?? false) === true, 'L: segunda vista → notificar=true (dedup en JS)');

// Si no está activo, notificar no aparece
$pL2 = partidaEst();
forzarHeart($pL2, 80);
setNecesidades($pL2, 80);
$infoL3 = VidaPuebloEngine::vista($pL2, $cal);
ok(!isset($infoL3['estancamiento']['notificar']), 'L: pueblo sano → sin notificar');

// ============================================================
// M. FEEDBACK: sigue activo varios días → notificar siempre presente
// (deduplication ocurre en JS con Set, no server-side)
// ============================================================
echo "\n--- M: Activo varios días → notificar siempre presente ---\n";
$pM = partidaEst();
forzarHeart($pM, 30);
setNecesidades($pM, 20);
for ($d = 0; $d < 7; $d++) {
    $pM['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pM, 20);
    cerrarDia($pM);
}

// Simular 5 días más activo: notificar sigue true (dedup en JS)
for ($d = 0; $d < 5; $d++) {
    $pM['reloj']['dia_pueblo'] = 100 + $d;
    setNecesidades($pM, 20);
    cerrarDia($pM);
    $info = VidaPuebloEngine::vista($pM, $cal);
    ok(($info['estancamiento']['notificar'] ?? false) === true, "M: día " . (100 + $d) . " activo → notificar presente");
    ok(($info['estancamiento']['activo'] ?? false) === true, "M: día " . (100 + $d) . " → activo=true");
}

// Sin estancamiento → sin notificar
$pM2 = partidaEst();
forzarHeart($pM2, 80);
setNecesidades($pM2, 80);
$infoM2 = VidaPuebloEngine::vista($pM2, $cal);
ok(!isset($infoM2['estancamiento']['notificar']), 'M: pueblo sano → sin notificar');

// ============================================================
// N. FEEDBACK: se recupera → vuelve a estancarse → notifica otra vez
// ============================================================
echo "\n--- N: Re-activación tras recuperación ---\n";
$pN = partidaEst();
forzarHeart($pN, 30);
setNecesidades($pN, 20);

// Activar estancamiento
for ($d = 0; $d < 7; $d++) {
    $pN['reloj']['dia_pueblo'] = $d + 1;
    setNecesidades($pN, 20);
    cerrarDia($pN);
}
ok($pN['vida_pueblo']['estancamiento']['activo'] === true, 'N: primera activación');

// Consumir notificación
$infoN1 = VidaPuebloEngine::vista($pN, $cal);
ok(($infoN1['estancamiento']['notificar'] ?? false) === true, 'N: notifica en primera activación');

// Recuperar (mejora real)
for ($d = 0; $d < 8; $d++) {
    $pN['reloj']['dia_pueblo'] = 50 + $d;
    setNecesidades($pN, 20 + ($d * 10));
    cerrarDia($pN);
}
ok($pN['vida_pueblo']['estancamiento']['activo'] === false, 'N: estancamiento desactivado');

// Volver a estancarse
for ($d = 0; $d < 8; $d++) {
    $pN['reloj']['dia_pueblo'] = 200 + $d;
    setNecesidades($pN, 20);
    cerrarDia($pN);
}
ok($pN['vida_pueblo']['estancamiento']['activo'] === true, 'N: re-activación');

// Debe notificar de nuevo
$infoN2 = VidaPuebloEngine::vista($pN, $cal);
ok(($infoN2['estancamiento']['notificar'] ?? false) === true, 'N: notifica en re-activación');

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
