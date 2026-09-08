<?php
declare(strict_types=1);

/**
 * T-06: E2E cumpleanos — traza completa ficha → JS → DOM.
 *
 * Cubre:
 *   A. ResidenteCumpleanosEngine::obtener() → fuente canónica
 *   B. PartidaService::fichaResidente(respuestaLigera=true) → JSON como el handler
 *   C. FichaPlayVista::de() → vista_play incluye cumpleanos
 *   D. Estructura JSON exacta que recibe el frontend
 *   E. Simula la condición JS: cp && cp.dia && cp.mes
 *   F. Verifica que [data-ficha-cumple] + [data-ficha-cumple-txt] existen en play.php
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\FichaPlayVista;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ResidenteCumpleanosEngine;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $failures++; }
}

$catalog = new Catalog($root);
$svc = new PartidaService($root);

echo "--- T-06: E2E cumpleanos ficha ---\n\n";

$p = $svc->nuevaPartida('juego_v1', 't06-cumple-' . time());
$rid = (string) array_key_first($p['residentes'] ?? []);

// A. Fuente canónica
$cp = ResidenteCumpleanosEngine::obtener($p, $rid, $catalog);
ok($cp !== null, "A: obtener() = " . json_encode($cp));
ok(isset($cp['dia']) && $cp['mes'], "A: tiene dia=" . ($cp['dia'] ?? '?') . " mes=" . ($cp['mes'] ?? '?'));

// B. fichaResidente con respuestaLigera (como lo hace el handler)
$ficha = $svc->fichaResidente($p, $rid, true);

// B1. vista_play cumpleanos
$vpCumple = $ficha['vista_play']['cumpleanos'] ?? null;
ok($vpCumple !== null, "B1: vista_play.cumpleanos = " . json_encode($vpCumple));
ok($vpCumple === $cp, "B1: coincide con fuente canónica");

// B2. identidad cumpleanos en light response
$idCumple = $ficha['identidad']['cumpleanos'] ?? null;
ok($idCumple !== null, "B2: identidad.cumpleanos = " . json_encode($idCumple));

// C. Estructura JSON exacta (como la ve el frontend)
$json = json_encode($ficha, JSON_UNESCAPED_UNICODE);
$jsonObj = json_decode($json, true);

// C1. f.vista_play existe
ok(isset($jsonObj['vista_play']), "C1: JSON tiene vista_play");

// C2. f.vista_play.cumpleanos es objeto con dia y mes
$vpObj = $jsonObj['vista_play'] ?? [];
ok(isset($vpObj['cumpleanos']['dia']), "C2: vista_play.cumpleanos.dia = " . ($vpObj['cumpleanos']['dia'] ?? 'MISSING'));
ok(isset($vpObj['cumpleanos']['mes']), "C2: vista_play.cumpleanos.mes = " . ($vpObj['cumpleanos']['mes'] ?? 'MISSING'));

// D. Simula condición JS: var cp = vista.cumpleanos || (f.identidad && f.identidad.cumpleanos)
// vista = f.vista_play
$cpJs = $vpObj['cumpleanos'] ?? null;
if ($cpJs === null) {
    $cpJs = ($jsonObj['identidad']['cumpleanos'] ?? null);
}
ok($cpJs !== null, "D: JS cp = " . json_encode($cpJs));
ok(is_array($cpJs) && isset($cpJs['dia']) && $cpJs['dia'] > 0, "D: cp.dia truthy = " . ($cpJs['dia'] ?? '?'));
ok(is_array($cpJs) && isset($cpJs['mes']) && $cpJs['mes'] > 0, "D: cp.mes truthy = " . ($cpJs['mes'] ?? '?'));
$passJsCondition = ($cpJs !== null && !empty($cpJs['dia']) && !empty($cpJs['mes']));
ok($passJsCondition, "D: condición JS cp && cp.dia && cp.mes = " . ($passJsCondition ? 'TRUE' : 'FALSE'));

// E. Verifica HTML play.php tiene los elementos
$htmlPath = $root . '/play.php';
$html = file_get_contents($htmlPath);
ok(strpos($html, 'data-ficha-cumple') !== false, "E1: play.php tiene data-ficha-cumple");
ok(strpos($html, 'data-ficha-cumple-txt') !== false, "E2: play.php tiene data-ficha-cumple-txt");
ok(strpos($html, 'ficha-hero-cumple') !== false, "E3: play.php tiene class ficha-hero-cumple");

// F. Verifica JS play-v3.js tiene la lógica de render
$jsPath = $root . '/assets/js/play-v3.js';
$js = file_get_contents($jsPath);
ok(strpos($js, 'data-ficha-cumple') !== false, "F1: JS referencia data-ficha-cumple");
ok(strpos($js, 'cumpleanos') !== false, "F2: JS menciona cumpleanos");
ok(preg_match('/vista\.cumpleanos\s*\|\|/', $js) === 1, "F3: JS tiene fallback vista.cumpleanos || ...");
ok(preg_match('/cp\.dia.*cp\.mes|cp\.mes.*cp\.dia/', $js) >= 1, "F4: JS valida cp.dia && cp.mes");
ok(preg_match('/cumpleTagEl\.hidden\s*=\s*false/', $js) === 1, "F5: JS tiene cumpleTagEl.hidden = false");
ok(preg_match('/cumpleTagEl\.hidden\s*=\s*true/', $js) === 1, "F6: JS tiene cumpleTagEl.hidden = true");

// G. Verifica CSS tiene regla de display
$cssPath = $root . '/assets/css/v4/screens.css';
$css = file_get_contents($cssPath);
ok(strpos($css, 'ficha-hero-cumple') !== false, "G1: CSS regla ficha-hero-cumple existe");
ok(preg_match('/ficha-hero-cumple\s*\{[^}]*display/', $css) === 1, "G2: CSS tiene display para ficha-hero-cumple");

// H. Verificar que no hay display:none que interfiera
preg_match('/ficha-hero-cumple\s*\{([^}]*)\}/', $css, $match);
if (isset($match[1])) {
    $hasDisplayNone = strpos($match[1], 'display: none') !== false || strpos($match[1], 'display:none') !== false;
    ok(!$hasDisplayNone, "H: CSS ficha-hero-cumple NO tiene display:none");
} else {
    ok(false, "H: No se pudo extraer regla CSS");
}

echo "\n--- JSON ficha (vista_play) ---\n";
echo json_encode($jsonObj['vista_play'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n" . str_repeat('=', 50) . "\n";
echo $failures === 0 ? "TODOS LOS TESTS PASARON\n" : "FALLOS: $failures\n";
exit($failures > 0 ? 1 : 0);
