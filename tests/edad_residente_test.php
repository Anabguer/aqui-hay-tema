<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CompatibilidadCalculator;
use AquiHayTema\Engine\EdadCanonica;
use AquiHayTema\Engine\EdadPolitica;
use AquiHayTema\Engine\GeneradorResidente;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\PoolJugableCanon;
use AquiHayTema\Engine\ResidenteRuntime;
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

$catalog = new Catalog($root);
$cal = CalibracionConfig::load($root);

$sinEdad = 0;
foreach (PoolJugableCanon::ids($root) as $id) {
    $p = $catalog->loadPersonaje($id);
    $edad = $p['identidad']['edad'] ?? null;
    if (!is_int($edad) || $edad < EdadCanonica::MIN || $edad > EdadCanonica::MAX) {
        $sinEdad++;
    }
}
ok($sinEdad === 0, 'los 200 personajes tienen edad jugable en catálogo');

$raul = $catalog->loadPersonaje('per_p004');
$eduardo = $catalog->loadPersonaje('per_p027');
$sandra = $catalog->loadPersonaje('per_p161');
ok(($raul['identidad']['edad'] ?? null) === 64, 'Raúl conserva edad 64');
ok(($eduardo['identidad']['edad'] ?? null) === 30, 'Eduardo edad 30 desde meta 28-32');
ok(($sandra['identidad']['edad'] ?? null) === 23, 'Sandra edad 23 desde meta 21-24');

$partida = [
    'residentes' => [
        'per_p004' => ResidenteRuntime::crearDesdeCatalogo($raul),
        'per_p027' => ResidenteRuntime::crearDesdeCatalogo($eduardo),
    ],
];
GeneradorResidente::aplicar($partida, 'per_p004', $catalog);
GeneradorResidente::aplicar($partida, 'per_p027', $catalog);
ok(
    ($partida['residentes']['per_p027']['runtime']['perfil_partida']['edad'] ?? null) === 30,
    'generación copia edad catálogo → perfil_partida (Eduardo)'
);

$pa = PerfilPartida::deOLegacy($partida, 'per_p004', $catalog);
$pb = PerfilPartida::deOLegacy($partida, 'per_p027', $catalog);
$compat = CompatibilidadCalculator::aHaciaB($pa, $pb, $cal);
ok(($compat['edad']['edad_a'] ?? null) === 64 && ($compat['edad']['edad_b'] ?? null) === 30, 'compat expone edades Raúl/Eduardo');
ok(($compat['edad']['delta'] ?? null) === 34, 'delta edad Raúl/Eduardo = 34');
ok(($compat['total'] ?? 0) > 0, 'compatibilidad social sigue calculándose (no bloqueada por edad)');
ok(($compat['romance_elegible'] ?? true) === false, 'romance_elegible false por límite duro (>10)');

$rom = RomanceElegibilidad::par($partida, 'per_p004', 'per_p027', $cal, $catalog);
ok(($rom['ok'] ?? true) === false, 'RomanceElegibilidad veta Raúl/Eduardo');
ok(($rom['motivo'] ?? '') === 'edad_limite_duro', 'motivo romance: edad_limite_duro');
ok(($rom['edad']['delta'] ?? null) === 34, 'romance edad delta 34');

$edadPolitica = EdadPolitica::clasificar(64, 30, $cal);
ok($edadPolitica['romance_elegible'] === false, 'EdadPolitica: 34 años de gap no romance');

$ok10 = EdadPolitica::clasificar(30, 40, $cal);
ok($ok10['romance_elegible'] === true, '30+40: romance permitido por edad');
$veto11 = EdadPolitica::clasificar(30, 41, $cal);
ok($veto11['romance_elegible'] === false, '30+41: romance vetado por edad');
$ok7 = EdadPolitica::clasificar(23, 30, $cal);
ok($ok7['romance_elegible'] === true, '23+30: romance permitido por edad');

exit($failures > 0 ? 1 : 0);
