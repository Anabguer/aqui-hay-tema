<?php
declare(strict_types=1);

/**
 * T-07: Verifica que la transformación FichaPlayVista incluye cumpleanos.
 *
 * Simula el flujo exacto del handler: fichaResidente(respuestaLigera=true)
 * y verifica la ruta JS: f.vista_play.cumpleanos → cp → DOM.
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

echo "--- T-07: FichaPlayVista transformación ---\n\n";

$p = $svc->nuevaPartida('juego_v1', 't07-ficha-' . time());
$rid = (string) array_key_first($p['residentes'] ?? []);

// Paso 1: obtener canónico
$cp = ResidenteCumpleanosEngine::obtener($p, $rid, $catalog);
ok($cp !== null && isset($cp['dia'], $cp['mes']), "1: obtener()=" . json_encode($cp));

// Paso 2: fichaResidente light
$ficha = $svc->fichaResidente($p, $rid, true);

// Paso 3: verificar que vista_play tiene cumpleanos
$vp = $ficha['vista_play'] ?? null;
ok($vp !== null, "2: vista_play no es null");
ok(isset($vp['cumpleanos']), "3: vista_play.cumpleanos EXISTE");
ok($vp['cumpleanos'] === $cp, "4: vista_play.cumpleanos = obtener()");

// Paso 4: verificar identidad (light response: solo nombre)
ok(isset($ficha['identidad']['nombre']), "5: identidad.nombre existe");
// cumpleanos NO debería estar en identidad del light response
// (solo se incluye en el full response)

// Paso 5: simular JSON que recibe el frontend
$json = json_encode($ficha, JSON_UNESCAPED_UNICODE);
$jsonObj = json_decode($json, true);

// Ruta JS: r.ficha → f → vista = f.vista_play || f
$f = $jsonObj;
$vista = $f['vista_play'] ?? $f;

// JS: cp = vista.cumpleanos || (f.identidad && f.identidad.cumpleanos)
$cpJs = $vista['cumpleanos'] ?? null;
if ($cpJs === null && isset($f['identidad']['cumpleanos'])) {
    $cpJs = $f['identidad']['cumpleanos'];
}

ok($cpJs !== null, "6: ruta JS cp = " . json_encode($cpJs));
ok(is_array($cpJs) && ($cpJs['dia'] ?? 0) > 0, "7: cp.dia truthy=" . ($cpJs['dia'] ?? 'null'));
ok(is_array($cpJs) && ($cpJs['mes'] ?? 0) > 0, "8: cp.mes truthy=" . ($cpJs['mes'] ?? 'null'));

$passCond = ($cpJs !== null && !empty($cpJs['dia']) && !empty($cpJs['mes']));
ok($passCond, "9: cp && cp.dia && cp.mes = " . ($passCond ? 'TRUE' : 'FALSE'));

// Paso 6: verificar que el texto JS se generaría correctamente
if ($passCond) {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $txt = 'Cumpleaños: ' . $cpJs['dia'] . ' de ' . ($meses[($cpJs['mes'] | 0) - 1] ?? '');
    ok(strlen($txt) > 10, "10: texto generado='$txt'");
} else {
    ok(false, "10: No se genera texto (condición falla)");
}

// Paso 7: verificar play.php tiene data-ficha-cumple dentro de data-aht-screen="ficha"
$html = file_get_contents($root . '/play.php');
$fichaIdx = strpos($html, 'data-aht-screen="ficha"');
$cumpleIdx = strpos($html, 'data-ficha-cumple');
ok($fichaIdx !== false && $cumpleIdx !== false && $cumpleIdx > $fichaIdx,
   "11: data-ficha-cumple DENTRO de ficha screen");

// Paso 8: verificar que $ selector lo encontraría
$hasAttr = preg_match('/<[^>]*data-ficha-cumple[^>]*/', $html);
ok($hasAttr, "12: elemento con data-ficha-cumple existe en HTML");

// Paso 9: verificar que CSS no tiene display:none para .ficha-hero-cumple
$css = file_get_contents($root . '/assets/css/v4/screens.css');
preg_match_all('/\.ficha-hero-cumple[^{]*\{([^}]*)\}/', $css, $matches);
$hasNone = false;
foreach ($matches[1] as $block) {
    if (preg_match('/display\s*:\s*none/', $block)) {
        $hasNone = true;
    }
}
ok(!$hasNone, "13: CSS NO tiene display:none para .ficha-hero-cumple");

// Paso 10: verificar que el CSS display:inline-flex tiene autoridad sobre [hidden]
preg_match('/\.aht-screen\[data-aht-screen="ficha"\]\s+\.ficha-hero-cumple\s*\{([^}]*)\}/', $css, $m);
$hasInlineFlex = isset($m[1]) && strpos($m[1], 'display: inline-flex') !== false;
ok($hasInlineFlex, "14: CSS tiene display:inline-flex (autoridad > [hidden])");

echo "\n--- Resumen ruta de datos ---\n";
echo "obtener() → " . json_encode($cp) . "\n";
echo "fichaResidente.vista_play.cumpleanos → " . json_encode($vp['cumpleanos'] ?? null) . "\n";
echo "JSON.ruta → vista = f.vista_play → vista.cumpleanos = " . json_encode($cpJs) . "\n";
echo "condición JS → cp && cp.dia && cp.mes = " . ($passCond ? 'TRUE' : 'FALSE') . "\n";

echo "\n" . str_repeat('=', 50) . "\n";
echo $failures === 0 ? "TODOS LOS TESTS PASARON — backend OK, ruta JS OK, HTML OK, CSS OK\n" : "FALLOS: $failures\n";
exit($failures > 0 ? 1 : 0);
