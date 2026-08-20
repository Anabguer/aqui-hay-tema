<?php
require __DIR__ . '/../src/autoload.php';
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\SchemaFields;

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
foreach (['lab_a', 'lab_b'] as $id) {
    $p['residentes'][$id] = ['catalog_id' => $id, 'runtime' => ['perfil_partida' => ['edad' => 30]]];
}
SchemaFields::ensure($p);
RelacionEngine::registrarContacto($p, 'lab_a', 'lab_b', AquiHayTema\Engine\ContactoCalidad::NORMAL, $cal);
RelacionEngine::setRomanceHacia($p, 'lab_a', 'lab_b', 60);
RelacionEngine::setRomanceHacia($p, 'lab_b', 'lab_a', 55);
$form = ParejaEngine::formar($p, 'lab_a', 'lab_b', true, true, RelacionBitacora::INICIO_PAREJA, $cal);
echo json_encode($form, JSON_PRETTY_PRINT) . "\n";
echo 'estado=' . ParejaEngine::estado($p, 'lab_a', 'lab_b') . "\n";
echo 'nrom=' . count($p['relaciones_romanticas']) . "\n";
foreach ($p['relaciones_romanticas'] as $i => $rel) {
    echo "$i id=" . ($rel['id'] ?? '?') . ' est=' . ($rel['estado_pareja'] ?? '?') . "\n";
}
