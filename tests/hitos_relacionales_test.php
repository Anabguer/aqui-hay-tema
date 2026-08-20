<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\HitoRelacionalEngine;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorHitosRelacionales;

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

$cal = CalibracionConfig::load($root);
ok((bool) CalibracionConfig::get($cal, 'hitos_relacionales.activo', false), 'hitos_relacionales.activo');
ok((bool) CalibracionConfig::get($cal, 'pareja.nunca_auto_por_umbral', false), 'pareja nunca auto por umbral');
ok((bool) CalibracionConfig::get($cal, 'crisis.nunca_auto_por_umbral', false), 'crisis nunca auto por umbral');
ok(is_numeric(CalibracionConfig::get($cal, 'hitos_relacionales.inicio_pareja.p_base', null)), 'inicio_pareja.p_base en config');
ok(is_numeric(CalibracionConfig::get($cal, 'hitos_relacionales.infidelidad.p_base', null)), 'infidelidad.p_base en config');

$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'hitos-rel-1');
$ph = $service->crearResidentePlaceholderDev($partida);
$a = 'per_qa_valid';
$b = $ph['residente']['catalog_id'];

RelacionEngine::upsertSocial($partida, $a, $b, 'amigo', 4);
RelacionEngine::setRomanceHacia($partida, $a, $b, 90);
RelacionEngine::setRomanceHacia($partida, $b, $a, 90);
ok(ParejaEngine::estado($partida, $a, $b) === ParejaEngine::NINGUNA, 'romance alto NO crea pareja');

$rng = new RngService('hitos-umbral');
// forzar muchos ticks: aún no debe aparecer pareja sin trayectoria+tirada con setup mínimo
for ($i = 0; $i < 5; $i++) {
    $partida['reloj']['dia_pueblo'] = 10 + $i;
    HitoRelacionalEngine::alCerrarDia($partida, $cal, $rng);
}
ok(ParejaEngine::estado($partida, $a, $b) !== ParejaEngine::PAREJA
    || RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::INICIO_PAREJA)
    || RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::BESO)
    || RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::COQUETEO)
    || RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::CONFESION),
    'si hay pareja, fue vía hito (no umbral mudo)');

// Escenarios dirigidos
$ids = ['lab_a', 'lab_b', 'lab_c'];
$p2 = [
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 12],
    'residentes' => [],
    'relaciones_sociales' => [],
    'relaciones_romanticas' => [],
    'relaciones_conflicto' => [],
    'parentesco' => [],
    'bitacora_relaciones' => [],
];
foreach ($ids as $id) {
    $p2['residentes'][$id] = [
        'catalog_id' => $id,
        'runtime' => ['perfil_partida' => ['edad' => 30, 'rasgos' => ['leal'], 'nombre' => $id]],
    ];
}
$rng2 = new RngService('esc-beso');
$rBeso = HitoRelacionalEngine::escenarioDirigido($p2, 'beso_sin_pareja', ['a' => 'lab_a', 'b' => 'lab_b'], $cal, $rng2);
ok(!empty($rBeso['ok']), 'escenario beso_sin_pareja');
ok(RelacionBitacora::tienenHito($p2, 'lab_a', 'lab_b', RelacionBitacora::BESO), 'beso registrado');
ok(ParejaEngine::estado($p2, 'lab_a', 'lab_b') === ParejaEngine::NINGUNA, 'beso no implica pareja');

$p3 = $p2;
$rng3 = new RngService('esc-inf');
$rInf = HitoRelacionalEngine::escenarioDirigido(
    $p3,
    'infidelidad_rara',
    ['a' => 'lab_a', 'b' => 'lab_b', 'c' => 'lab_c'],
    $cal,
    $rng3
);
ok(!empty($rInf['ok']), 'escenario infidelidad_rara');
ok(RelacionBitacora::tienenHito($p3, 'lab_a', 'lab_c', RelacionBitacora::INFIDELIDAD), 'infidelidad registrada con tercero');
ok(ParejaEngine::estado($p3, 'lab_a', 'lab_b') === ParejaEngine::CRISIS
    || ParejaEngine::estado($p3, 'lab_a', 'lab_b') === ParejaEngine::PAREJA, 'pareja original sigue existiendo o en crisis');

$p4 = $p2;
$rng4 = new RngService('esc-ami');
$rAmi = HitoRelacionalEngine::escenarioDirigido($p4, 'amistad_sin_romance', ['a' => 'lab_a', 'b' => 'lab_b'], $cal, $rng4);
ok(!empty($rAmi['ok']), 'amistad sin romance');
ok((int) ($rAmi['romance_ab'] ?? 1) === 0 && (int) ($rAmi['romance_ba'] ?? 1) === 0, 'romance 0 con amistad');

// Regresión foreach &$rel: upsert tercero no debe pisar pareja
$pBug = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8],
    'residentes' => [
        'x1' => ['catalog_id' => 'x1'],
        'x2' => ['catalog_id' => 'x2'],
        'x3' => ['catalog_id' => 'x3'],
    ],
    'relaciones_sociales' => [],
    'relaciones_romanticas' => [],
    'relaciones_conflicto' => [],
    'parentesco' => [],
    'bitacora_relaciones' => [],
];
RelacionEngine::setRomanceHacia($pBug, 'x1', 'x2', 50);
ParejaEngine::formar($pBug, 'x1', 'x2', true, true, RelacionBitacora::INICIO_PAREJA, $cal);
RelacionEngine::setRomanceHacia($pBug, 'x1', 'x3', 40);
ok(ParejaEngine::estado($pBug, 'x1', 'x2') === ParejaEngine::PAREJA, 'upsert tercero no destruye pareja (foreach ref)');
ok(count($pBug['relaciones_romanticas']) === 2, 'dos romances distintos tras tercero');

// Sim corto smoke
$mini = SimuladorHitosRelacionales::ejecutar($root, [8], [30], 1, ['normal'], 'hitos-smoke');
ok(!empty($mini['matriz']['n8_d30_normal']), 'sim smoke matriz');
ok(!empty($mini['escenarios_dirigidos']['reconciliacion']['ok']), 'escenarios dirigidos en sim');

echo $failures === 0 ? "OK hitos_relacionales\n" : "FAIL hitos_relacionales ($failures)\n";
exit($failures === 0 ? 0 : 1);
