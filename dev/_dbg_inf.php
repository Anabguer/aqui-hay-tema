<?php
require __DIR__ . '/../src/autoload.php';
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\HitoRelacionalEngine;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SchemaFields;
use AquiHayTema\Engine\ContactoCalidad;

$cal = CalibracionConfig::load(dirname(__DIR__));
$p = [
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 12],
    'residentes' => [],
    'relaciones_sociales' => [],
    'relaciones_romanticas' => [],
    'relaciones_conflicto' => [],
    'parentesco' => [],
    'bitacora_relaciones' => [],
];
foreach (['lab_a', 'lab_b', 'lab_c'] as $id) {
    $p['residentes'][$id] = [
        'catalog_id' => $id,
        'runtime' => ['perfil_partida' => ['edad' => 30, 'rasgos' => ['leal'], 'nombre' => $id]],
    ];
}
SchemaFields::ensure($p);
$rng = new RngService('esc-inf');

// Step through like escenario
RelacionEngine::registrarContacto($p, 'lab_a', 'lab_b', ContactoCalidad::SIGNIFICATIVO, $cal);
RelacionEngine::setRomanceHacia($p, 'lab_a', 'lab_b', 60);
RelacionEngine::setRomanceHacia($p, 'lab_b', 'lab_a', 55);
echo "after romance n=" . count($p['relaciones_romanticas']) . " estado=" . ParejaEngine::estado($p, 'lab_a', 'lab_b') . "\n";
$form = ParejaEngine::formar($p, 'lab_a', 'lab_b', true, true, RelacionBitacora::INICIO_PAREJA, $cal);
echo "after formar ok=" . (!empty($form['ok']) ? '1' : '0') . " estado=" . ParejaEngine::estado($p, 'lab_a', 'lab_b') . " n=" . count($p['relaciones_romanticas']) . "\n";
RelacionEngine::registrarContacto($p, 'lab_a', 'lab_c', ContactoCalidad::NORMAL, $cal);
RelacionEngine::setRomanceHacia($p, 'lab_a', 'lab_c', 48);
RelacionEngine::setRomanceHacia($p, 'lab_c', 'lab_a', 40);
echo "after tercero n=" . count($p['relaciones_romanticas']) . " estado_ab=" . ParejaEngine::estado($p, 'lab_a', 'lab_b') . "\n";
$rel = ParejaEngine::ensureRomance($p, 'lab_a', 'lab_b');
$rel['estabilidad_pareja']['valor'] = 18;
RelacionEngine::persistirRomance($p, $rel);
echo "after ensure n=" . count($p['relaciones_romanticas']) . " estado=" . ParejaEngine::estado($p, 'lab_a', 'lab_b') . "\n";

// Reflect aplicarInfidelidad via private by calling escenario on fresh copy
$p2 = $p;
$r = HitoRelacionalEngine::escenarioDirigido($p2, 'infidelidad_rara', ['a' => 'lab_a', 'b' => 'lab_b', 'c' => 'lab_c'], $cal, new RngService('esc-inf2'));
echo "escenario: " . json_encode($r) . "\n";
foreach ($p2['relaciones_romanticas'] as $rel) {
    echo json_encode([
        'id' => $rel['id'] ?? null,
        'a' => $rel['persona_a'] ?? null,
        'b' => $rel['persona_b'] ?? null,
        'estado' => $rel['estado_pareja'] ?? null,
        'rom_ab' => $rel['romance_a_hacia_b'] ?? null,
        'rom_ba' => $rel['romance_b_hacia_a'] ?? null,
    ], JSON_UNESCAPED_UNICODE) . "\n";
}
echo "bitacora tipos: ";
foreach ($p2['bitacora_relaciones'] as $h) {
    echo ($h['tipo'] ?? '?') . ' ';
}
echo "\n";
