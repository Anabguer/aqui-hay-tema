<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AforoEngine;
use AquiHayTema\Engine\AutonomiaSalidas;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ComplejoCatalog;
use AquiHayTema\Engine\CotilleoNarrativo;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\LugarAtributos;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorAutonomia;

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

DomainBootstrap::resetForTests();
DomainBootstrap::boot();

ok(ComplejoCatalog::estaAbierto('lug_cafeteria', 10), 'cafetería abierta a las 10');
ok(!ComplejoCatalog::estaAbierto('lug_cafeteria', 21), 'cafetería cerrada a las 21');
ok(!ComplejoCatalog::estaAbierto('lug_cafeteria', 20), 'cafetería cierra a las 20 (fin exclusive)');

$service = new PartidaService($root);
$clipP = $service->nuevaPartida('test_fixtures_v0', 'auto-clip');
$phClip = $service->crearResidentePlaceholderDev($clipP);
$clip = EncuentroEngine::programar(
    $clipP,
    ['per_qa_valid', $phClip['residente']['catalog_id']],
    1,
    19,
    'conocerse',
    'lug_cafeteria'
);
ok($clip['ok'] ?? false, 'cafetería a las 19h se puede programar');
ok((int) ($clip['encuentro']['duracion_horas'] ?? 0) === 1, 'a las 19h la duración se recorta al cierre');
$cerr20 = EncuentroEngine::programar(
    $clipP,
    ['per_qa_valid', $phClip['residente']['catalog_id']],
    1,
    20,
    'conocerse',
    'lug_cafeteria'
);
ok(($cerr20['error'] ?? '') === 'LUGAR_CERRADO', 'cafetería a las 20h rechazada');
ok(ComplejoCatalog::estaAbierto('lug_discoteca', 23), 'discoteca abierta a las 23');
ok(!ComplejoCatalog::estaAbierto('lug_discoteca', 12), 'discoteca cerrada al mediodía');
ok(ComplejoCatalog::estaAbierto('lug_restaurante', 14), 'restaurante comida');
ok(ComplejoCatalog::estaAbierto('lug_restaurante', 21), 'restaurante cena');
ok(!ComplejoCatalog::estaAbierto('lug_restaurante', 17), 'restaurante cerrado entre servicios');
ok((int) LugarAtributos::de('lug_parque')['aforo'] === 12, 'parque aforo 12');
ok(ComplejoCatalog::aforoComplejo('cafe_libros') === 10, 'complejo café máx 10');
ok(ComplejoCatalog::aforoComplejo('cine_game') === 12, 'complejo Cine Game máx 12');
ok(ComplejoCatalog::estaAbierto('lug_cine', 16), 'cine abre a las 16');
ok(!ComplejoCatalog::estaAbierto('lug_cine', 15), 'cine cerrado a las 15');
ok(!ComplejoCatalog::estaAbierto('lug_cine', 0), 'cine cierra a medianoche');
ok((int) LugarAtributos::de('lug_cine')['aforo'] === 8, 'cine aforo 8');
ok(ComplejoCatalog::estaAbierto('lug_arcade', 12), 'arcade abre a las 12');
ok(!ComplejoCatalog::estaAbierto('lug_arcade', 11), 'arcade cerrado a las 11');
ok(!ComplejoCatalog::estaAbierto('lug_arcade', 0), 'arcade cierra a medianoche');
ok((int) LugarAtributos::de('lug_arcade')['aforo'] === 8, 'arcade aforo 8');

$cal = CalibracionConfig::load($root);
$cupo8 = AutonomiaSalidas::cupoDia(8, $cal);
$cupo16 = AutonomiaSalidas::cupoDia(16, $cal);
$cupo32 = AutonomiaSalidas::cupoDia(32, $cal);
$cupo48 = AutonomiaSalidas::cupoDia(48, $cal);
ok($cupo8 >= 3 && $cupo8 <= 6, 'cupo n=8 en banda de estudio (' . $cupo8 . ')');
ok($cupo16 >= 5 && $cupo16 <= 10, 'cupo n=16 en banda de estudio (' . $cupo16 . ')');
ok($cupo32 >= 10 && $cupo32 <= 20, 'cupo n=32 en banda de estudio (' . $cupo32 . ')');
ok($cupo48 >= 18 && $cupo48 <= 30, 'cupo n=48 en banda de estudio (' . $cupo48 . ')');
ok($cupo48 > $cupo16 * 1.5, 'cupo crece de verdad con la población');

$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'auto-reglas');
$catalog = new Catalog($root);
$rng = new RngService('auto-reglas-1');
$ids = array_keys($partida['residentes']);
ok(count($ids) >= 4, 'playtest tiene residentes');

$partida['reloj']['dia_pueblo'] = 1;
$salidas = [];
for ($h = 7; $h <= 22; $h++) {
    $partida['reloj']['hora_actual'] = $h;
    $t = MotorVidaDiaria::tickHora($partida, $catalog, $cal, $rng, null);
    $lote = [];
    if (is_array($t['autonomo']['salidas'] ?? null)) {
        $lote = $t['autonomo']['salidas'];
    } elseif (is_array($t['autonomo'] ?? null) && isset($t['autonomo']['quien'])) {
        $lote = [$t['autonomo']];
    }
    foreach ($lote as $s) {
        if (is_array($s) && isset($s['quien'])) {
            $salidas[] = $s;
        }
    }
}
$por = [];
foreach ($salidas as $s) {
    $q = (string) $s['quien'];
    $por[$q] = (int) ($por[$q] ?? 0) + 1;
}
$max = $por === [] ? 0 : max($por);
ok($max <= 1, 'nadie sale dos veces el mismo día (max=' . $max . ')');
foreach ($salidas as $s) {
    ok(ComplejoCatalog::estaAbierto((string) $s['lugar'], (int) ($s['encuentro']['hora'] ?? 10)), 'salida en horario abierto');
}

$capCafe = (int) LugarAtributos::de('lug_cafeteria')['aforo'];
ok(AforoEngine::cabe($partida, 'lug_cafeteria', 2, 11, $capCafe), 'cabe exactamente el aforo del destino');
ok(!AforoEngine::cabe($partida, 'lug_cafeteria', 2, 11, $capCafe + 1), 'no cabe por encima del destino');

$fake = $partida;
$fake['historial_coincidencias'] = [
    ['dia' => 1, 'lugar_id' => 'lug_parque', 'residentes' => [$ids[0], $ids[1]]],
    ['dia' => 2, 'lugar_id' => 'lug_parque', 'residentes' => [$ids[0], $ids[1]]],
    ['dia' => 3, 'lugar_id' => 'lug_parque', 'residentes' => [$ids[0], $ids[1]]],
];
$env = ['dia' => 3, 'lugar_id' => 'lug_parque', 'residentes' => [$ids[0], $ids[1]], 'actores' => [$ids[0], $ids[1]]];
ok(!CotilleoNarrativo::coincidenciaDigna($partida, $env, $cal), 'una coincidencia suelta no es cotilleo');
ok(CotilleoNarrativo::coincidenciaDigna($fake, $env, $cal), 'tres tardes mismo par+lugar sí es cotilleo');

$lab = SimuladorAutonomia::ejecutar($root, [8], 3, 1, 'lab-auto-test');
ok(!empty($lab['_provisional']), 'lab autonomía provisional');
ok((int) ($lab['por_tamano']['8']['max_salidas_misma_persona_dia'] ?? 9) <= 1, 'lab n=8 max 1 salida/persona/día');
ok((int) ($lab['por_tamano']['8']['cafeteria_fuera_horario'] ?? 1) === 0, 'lab no manda a la cafetería cerrada');
ok((float) ($lab['por_tamano']['8']['salidas_por_dia'] ?? 0) >= 2.0, 'lab n=8 produce vida autónoma visible');

ok(AutonomiaSalidas::horaActiva(23, $cal), 'autonomía activa de noche');
ok(!AutonomiaSalidas::horaActiva(5, $cal), 'autonomía dormida a las 5');
ok(AutonomiaSalidas::pIntentar(8, $cal) < AutonomiaSalidas::pIntentar(19, $cal), 'tarde más probable que mañana');

exit($failures > 0 ? 1 : 0);
