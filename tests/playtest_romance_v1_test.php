<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CompatibilidadCalculator;
use AquiHayTema\Engine\Compatibility\PlaceholderEvaluator;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\ParentescoVeto;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\RomanceElegibilidad;

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

$service = new PartidaService($root);
$catalog = new Catalog($root);
$cal = CalibracionConfig::load($root);
$partida = $service->nuevaPartida('playtest_01', 'playtest-01');
$partida['reloj']['hora_actual'] = 8;
$partida['reloj']['minuto_actual'] = 0;

$ids = ['per_p001', 'per_p002', 'per_p003', 'per_p004', 'per_p005', 'per_p006', 'per_p007', 'per_p008'];
foreach ($ids as $id) {
    $ficha = $catalog->loadPersonaje($id);
    $ident = is_array($ficha['identidad'] ?? null) ? $ficha['identidad'] : [];
    ok(!array_key_exists('atraido_por', $ident), "$id sin atraido_por");
    ok(!array_key_exists('etiqueta_orientacion_visible', $ident), "$id sin etiqueta_orientacion_visible");
}

$src = array_unique(array_merge(
    glob($root . '/src/Engine/*.php') ?: [],
    glob($root . '/src/Engine/*/*.php') ?: []
));
$fugas = [];
foreach ($src as $file) {
    $base = basename($file);
    if ($base === 'PersonajeValidator.php' || $base === 'RomanceElegibilidad.php' || $base === 'IdentidadCanon.php') {
        continue;
    }
    $code = (string) file_get_contents($file);
    if (strpos($code, 'atraido_por') !== false || strpos($code, 'etiqueta_orientacion') !== false) {
        $fugas[] = $base;
    }
}
ok($fugas === [], 'ningún evaluator/motor filtra por orientación: ' . implode(',', $fugas));

$paresOk = [
    ['per_p002', 'per_p006', 'P002-P006 hombre-hombre'],
    ['per_p001', 'per_p005', 'P001-P005 mujer-mujer'],
    ['per_p001', 'per_p002', 'P001-P002 hombre-mujer'],
];
foreach ($paresOk as $par) {
    $a = $par[0];
    $b = $par[1];
    $label = $par[2];
    $el = RomanceElegibilidad::par($partida, $a, $b, $cal);
    ok(($el['ok'] ?? false) === true, "$label elegible");
    ok(empty($el['motivo']), "$label sin veto");
    ok(!ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal), "$label sin parentesco");
    $pa = PerfilPartida::deOLegacy($partida, $a, $catalog);
    $pb = PerfilPartida::deOLegacy($partida, $b, $catalog);
    $cmp = CompatibilidadCalculator::aHaciaB($pa, $pb, $cal);
    ok(($cmp['romance_elegible'] ?? false) === true, "$label compatibilidad edad ok");
    $pTmp = $partida;
    $enc = EncuentroEngine::programar($pTmp, [$a, $b], 1, 19, 'romantico', 'lug_cafeteria');
    ok(($enc['ok'] ?? false) === true, "$label programar romantico no rechaza por género");
}

$eval = new PlaceholderEvaluator();
$romHH = $eval->evaluateRomantic($partida, 'per_p002', 'per_p006', ['tipo_encuentro' => 'romantico']);
ok(($romHH['aplicado'] ?? true) !== false && isset($romHH['atraccion_a_hacia_b']), 'evaluator romantico no descarta hombre-hombre');

$p004 = RomanceElegibilidad::par($partida, 'per_p004', 'per_p007', $cal);
ok(($p004['ok'] ?? true) === false, 'P004-P007 no elegible');
ok(($p004['motivo'] ?? '') === 'parentesco_veto', 'P004-P007 veto es parentesco');
ok(ParentescoVeto::bloqueaRomance($partida, 'per_p004', 'per_p007', $cal), 'P004-P007 ParentescoVeto');
$form = ParejaEngine::formar($partida, 'per_p004', 'per_p007', true, true, 'declaracion', $cal);
ok(($form['ok'] ?? true) === false, 'P004-P007 no forma pareja');
ok(strpos((string) json_encode($form), 'parentesco') !== false, 'P004-P007 motivo parentesco en formar');

exit($failures > 0 ? 1 : 0);
