<?php
require __DIR__ . '/../src/autoload.php';
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\ContactoCalidad;
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
foreach (['lab_a', 'lab_b', 'lab_c'] as $id) {
    $p['residentes'][$id] = ['catalog_id' => $id, 'runtime' => ['perfil_partida' => ['edad' => 30]]];
}
SchemaFields::ensure($p);
RelacionEngine::registrarContacto($p, 'lab_a', 'lab_b', ContactoCalidad::SIGNIFICATIVO, $cal);
RelacionEngine::setRomanceHacia($p, 'lab_a', 'lab_b', 60);
RelacionEngine::setRomanceHacia($p, 'lab_b', 'lab_a', 55);
ParejaEngine::formar($p, 'lab_a', 'lab_b', true, true, RelacionBitacora::INICIO_PAREJA, $cal);
echo "1) " . ParejaEngine::estado($p, 'lab_a', 'lab_b') . " n=" . count($p['relaciones_romanticas']) . "\n";
RelacionEngine::registrarContacto($p, 'lab_a', 'lab_c', ContactoCalidad::NORMAL, $cal);
echo "2 after contact c) " . ParejaEngine::estado($p, 'lab_a', 'lab_b') . " n=" . count($p['relaciones_romanticas']) . "\n";
foreach ($p['relaciones_romanticas'] as $rel) {
    echo "  " . ($rel['id'] ?? '?') . ' est=' . ($rel['estado_pareja'] ?? '?') . "\n";
}
RelacionEngine::setRomanceHacia($p, 'lab_a', 'lab_c', 48);
echo "3 after setRomance a>c) " . ParejaEngine::estado($p, 'lab_a', 'lab_b') . " n=" . count($p['relaciones_romanticas']) . "\n";
foreach ($p['relaciones_romanticas'] as $rel) {
    echo "  " . ($rel['id'] ?? '?') . ' est=' . ($rel['estado_pareja'] ?? '?') . ' romAB=' . json_encode($rel['romance_a_hacia_b'] ?? null) . "\n";
}
