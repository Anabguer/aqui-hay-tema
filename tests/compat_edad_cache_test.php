<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CompatibilidadCalculator;
use AquiHayTema\Engine\CompatibilidadOculta;
use AquiHayTema\Engine\GeneradorResidente;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\ResidenteRuntime;

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
$raul = $catalog->loadPersonaje('per_p004');
$eduardo = $catalog->loadPersonaje('per_p027');

$partida = [
    'residentes' => [
        'per_p004' => ResidenteRuntime::crearDesdeCatalogo($raul),
        'per_p027' => ResidenteRuntime::crearDesdeCatalogo($eduardo),
    ],
    'compatibilidad_oculta' => ['pares' => []],
];
GeneradorResidente::aplicar($partida, 'per_p004', $catalog);
GeneradorResidente::aplicar($partida, 'per_p027', $catalog);

// Simula caché antigua (edad desconocida) con totales ya calculados.
CompatibilidadOculta::asegurarDireccional($partida, 'per_p004', 'per_p027', $catalog);
$id = CompatibilidadOculta::parId('per_p004', 'per_p027');
$partida['compatibilidad_oculta']['pares'][$id]['direccional']['a_hacia_b']['edad'] = [
    'delta' => null,
    'en_preferencia' => null,
    'romance_elegible' => true,
    'nota' => 'edad desconocida: no se aplica filtro',
];
$partida['compatibilidad_oculta']['pares'][$id]['direccional']['b_hacia_a']['edad'] = [
    'delta' => null,
    'en_preferencia' => null,
    'romance_elegible' => true,
    'nota' => 'edad desconocida: no se aplica filtro',
];

CompatibilidadOculta::asegurarDireccional($partida, 'per_p004', 'per_p027', $catalog);
$hacia = CompatibilidadOculta::hacia($partida, 'per_p004', 'per_p027');
ok(($hacia['edad']['delta'] ?? null) === 34, 'caché obsoleta refresca delta edad');
ok(($hacia['romance_elegible'] ?? true) === false, 'caché obsoleta refresca romance_elegible');

$pa = PerfilPartida::deOLegacy($partida, 'per_p027', $catalog);
$pb = PerfilPartida::deOLegacy($partida, 'per_p160', $catalog);
if (($pb['edad'] ?? null) === null) {
    $benito = $catalog->loadPersonaje('per_p160');
    $partida['residentes']['per_p160'] = ResidenteRuntime::crearDesdeCatalogo($benito);
    GeneradorResidente::aplicar($partida, 'per_p160', $catalog);
    $pb = PerfilPartida::deOLegacy($partida, 'per_p160', $catalog);
}
$eb = CompatibilidadCalculator::aHaciaB($pa, $pb, $cal);
ok(($eb['edad']['delta'] ?? null) === 4, 'Eduardo+Benito delta 4');

exit($failures > 0 ? 1 : 0);
