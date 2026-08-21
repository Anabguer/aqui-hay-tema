<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CompatibilidadCalculator;
use AquiHayTema\Engine\CompatibilidadOculta;
use AquiHayTema\Engine\DiscoveryProjection;
use AquiHayTema\Engine\EdadPolitica;
use AquiHayTema\Engine\EncuentroPonderacion;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\GeneradorResidente;
use AquiHayTema\Engine\IndicadoresVisuales;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\PlanAfinidad;
use AquiHayTema\Engine\QuimicaEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SchemaMigrator;
use AquiHayTema\Engine\SimuladorPueblos;

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
ok(!empty($cal['_provisional']), 'calibración marcada provisional');
ok((int) CalibracionConfig::get($cal, 'generacion.hobbies_por_residente', 0) === 3, '3 hobbies por residente');

$store = (new Catalog($root))->store();
$idsHob = GeneradorResidente::idsGenerables($store, 'hobbies');
$idsRas = GeneradorResidente::idsGenerables($store, 'rasgos');
ok(count($idsHob) >= 10, 'catálogo hobbies generable');
ok(count($idsRas) >= 8, 'catálogo rasgos generable');
ok($store->accepts('indicadores_visuales', 'gafas'), 'indicador gafas');
ok(!$store->accepts('indicadores_visuales', 'gamer'), 'gamer no es indicador visual');

$catRocio = JsonFile_read_rocio($root);
$vis = IndicadoresVisuales::desdeCatalogo($catRocio, $store);
ok(in_array('gafas', $vis, true), 'Rocío: gafas desde identidad visual');
ok(in_array('canas', $vis, true), 'Rocío: canas desde identidad visual');

function JsonFile_read_rocio(string $root): array
{
    return \AquiHayTema\Engine\JsonFile::read($root . '/data/personajes/per_i03.json');
}

$rngA = new RngService('gen-same');
$rngB = new RngService('gen-same');
$p1 = GeneradorResidente::generar($rngA, $store, $cal, $catRocio);
$p2 = GeneradorResidente::generar($rngB, $store, $cal, $catRocio);
ok($p1['hobbies'] === $p2['hobbies'] && $p1['rasgos'] === $p2['rasgos'], 'misma seed mismo perfil');
ok(count($p1['hobbies']) === 3, '3 hobbies');
ok(count($p1['rasgos']) === 3, '3 rasgos');
ok(count($p1['preferencias']['personalidad_pos']) === 2, '2 prefs personalidad +');
ok(count($p1['preferencias']['personalidad_neg']) === 2, '2 prefs personalidad -');
ok(count(array_intersect($p1['preferencias']['personalidad_pos'], $p1['preferencias']['personalidad_neg'])) === 0, 'pos/neg personalidad no solapan');
ok(count($p1['preferencias']['hobbies_pos']) === 2, '2 prefs hobbies +');
ok(count($p1['preferencias']['hobbies_neg']) === 2, '2 prefs hobbies -');
ok(count(array_intersect($p1['preferencias']['hobbies_pos'], $p1['preferencias']['hobbies_neg'])) === 0, 'pos/neg hobbies no solapan');
ok(count(array_intersect($p1['preferencias']['hobbies_neg'], $p1['hobbies'])) === 0, 'no rechaza un hobby propio');

$rngC = new RngService('gen-other');
$p3 = GeneradorResidente::generar($rngC, $store, $cal, $catRocio);
$over = DiscoveryProjection::conPerfilPartida(
    ['vida.hobby_principal' => 'bingo', 'vida.hobbies_secundarios' => [], 'vida.rasgos_publicos' => []],
    ['runtime' => ['perfil_partida' => $p1]]
);
ok($over['vida.hobby_principal'] === $p1['hobbies'][0], 'overlay interno usa hobby generado');

ok($p1 !== $p3, 'otra seed otro perfil');

$edad = EdadPolitica::clasificar(47, 57, $cal);
ok($edad['en_preferencia'] === true, '±10 dentro de preferencia');
ok($edad['romance_elegible'] === true, 'dentro de límite duro');
$edadLejos = EdadPolitica::clasificar(22, 72, $cal);
ok($edadLejos['romance_elegible'] === false, 'límite duro provisional excluye 50 años de gap');

$baseA = [
    'hobbies' => ['bingo', 'cine', 'costura'],
    'rasgos' => ['sociable', 'tranquilo', 'perezoso'],
    'indicadores_visuales' => ['gafas', 'pelo_largo'],
    'edad' => 47,
    'preferencias' => [
        'personalidad_pos' => ['bromista', 'tranquilo'],
        'personalidad_neg' => ['cabezota', 'nervioso'],
        'visual_pos' => ['gafas', 'pelo_largo'],
        'visual_neg' => ['bigote', 'fade'],
        'hobbies_pos' => [],
        'hobbies_neg' => [],
    ],
];
$jose = [
    'hobbies' => ['cine', 'deporte', 'musica'],
    'rasgos' => ['bromista', 'directo', 'leal'],
    'indicadores_visuales' => ['gafas', 'pelo_corto'],
    'edad' => 50,
    'preferencias' => [
        'personalidad_pos' => ['sociable', 'leal'],
        'personalidad_neg' => ['perezoso', 'vanidoso'],
        'visual_pos' => ['fade', 'barba'],
        'visual_neg' => ['gafas', 'canas'],
        'hobbies_pos' => [],
        'hobbies_neg' => [],
    ],
];
$ab = CompatibilidadCalculator::aHaciaB($baseA, $jose, $cal);
$ba = CompatibilidadCalculator::aHaciaB($jose, $baseA, $cal);
ok(isset($ab['total'], $ba['total']), 'totales A→B y B→A');
ok($ab['total'] !== $ba['total'], 'compatibilidad direccional puede ser asimétrica');
ok(in_array('cine', $ab['hobbies']['compartidos'], true), 'hobby compartido suma');
ok($ab['hobbies']['aporte'] > 0, 'compartir hobbies aporta > 0');

$joseSinCine = $jose;
$joseSinCine['hobbies'] = ['deporte', 'musica', 'correr'];
$ab0 = CompatibilidadCalculator::aHaciaB($baseA, $joseSinCine, $cal);
ok($ab0['hobbies']['aporte'] === 0, 'no compartir hobbies no resta');
ok($ab0['hobbies']['compartidos'] === [], 'cero compartidos');

$baseHobPos = $baseA;
$baseHobPos['preferencias']['hobbies_pos'] = ['deporte'];
$abHobPos = CompatibilidadCalculator::aHaciaB($baseHobPos, $joseSinCine, $cal);
ok($abHobPos['hobbies']['aporte'] > 0, 'hobbies_pos coincidente suma');
$baseHobNeg = $baseA;
$baseHobNeg['preferencias']['hobbies_neg'] = ['deporte'];
$abHobNeg = CompatibilidadCalculator::aHaciaB($baseHobNeg, $joseSinCine, $cal);
ok($abHobNeg['hobbies']['aporte'] < 0, 'hobbies_neg coincidente resta');

$joseSinBromista = $jose;
$joseSinBromista['rasgos'] = ['directo', 'leal', 'timido'];
$abNoPos = CompatibilidadCalculator::aHaciaB($baseA, $joseSinBromista, $cal);
$abConPos = CompatibilidadCalculator::aHaciaB($baseA, $jose, $cal);
ok($abNoPos['personalidad']['aporte'] <= $abConPos['personalidad']['aporte'], 'pref positiva ausente no penaliza más que tenerla');

$joseCabezota = $jose;
$joseCabezota['rasgos'] = ['bromista', 'cabezota', 'leal'];
$abNeg = CompatibilidadCalculator::aHaciaB($baseA, $joseCabezota, $cal);
ok(in_array('cabezota', $abNeg['personalidad']['negativos_coincidentes'], true), 'rechazo explícito coincide');
ok($abNeg['personalidad']['aporte'] < $abConPos['personalidad']['aporte'], 'rechazo explícito penaliza');

$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'gen-partida-1');
$rocio = $partida['residentes']['per_i03'] ?? $partida['residentes']['per_qa_valid'] ?? null;
ok(is_array($rocio), 'hay residente inicial');
$rid = isset($partida['residentes']['per_qa_valid']) ? 'per_qa_valid' : array_key_first($partida['residentes']);
$perfilNacido = PerfilPartida::de($partida, $rid);
ok(is_array($perfilNacido), 'perfil generado al nacer');
ok(($perfilNacido['fuente'] ?? '') === 'generado', 'fuente generado');
$ph = $service->crearResidentePlaceholderDev($partida);
$idb = $ph['residente']['catalog_id'];
ok(is_array(PerfilPartida::de($partida, $idb)), 'placeholder también genera perfil');
ok(QuimicaEngine::obtener($partida, $rid, $idb) !== null, 'química al coexistir');
$q1 = QuimicaEngine::valorHacia($partida, $rid, $idb);
$q2 = QuimicaEngine::valorHacia($partida, $idb, $rid);
ok($q1 !== null && $q1 === $q2, 'V1 química simétrica persistida');
$rowQui = QuimicaEngine::obtener($partida, $rid, $idb);
$partida['quimica']['pares'][$rowQui['id']]['a_hacia_b'] = 1;
$rng = RngService::fromPartida($partida);
QuimicaEngine::asegurarPar($partida, $rid, $idb, $rng, $cal);
ok((int) QuimicaEngine::obtener($partida, $rid, $idb)['a_hacia_b'] === 1, 'química no se rerolea al recargar');

$dir = CompatibilidadOculta::hacia($partida, $rid, $idb);
ok(is_array($dir) && array_key_exists('total', $dir), 'compatibilidad persistida A→B');
$ficha = $service->fichaResidente($partida, $rid);
$js = json_encode($ficha);
ok($js !== false && strpos($js, 'compatibilidad_oculta') === false, 'ficha sin compatibilidad_oculta');
ok($js !== false && strpos($js, '"quimica"') === false, 'ficha sin química');
ok($js !== false && strpos($js, 'preferencias') === false, 'ficha sin preferencias internas');
if ($rid === 'per_qa_valid') {
    $hpFicha = $ficha['hobbies']['conocidos'][0] ?? ($ficha['discovery']['campos']['vida.hobby_principal']['valor'] ?? null);
    $perfilHob = $partida['residentes'][$rid]['runtime']['perfil_partida']['hobbies'][0] ?? null;
    ok($hpFicha === $perfilHob, 'ficha pública revela 1 hobby generado');
    ok(count($ficha['hobbies']['conocidos'] ?? []) <= 1, 'solo 1 hobby inicial');
}
$est = $service->estadoResumido($partida);
ok(!isset($est['compatibilidad_oculta'], $est['quimica']), 'estado resumido no expone encaje');

$ponder = EncuentroPonderacion::snapshot($partida, [
    'participantes' => [$rid, $idb],
    'lugar' => 'lug_cafeteria',
    'tipo' => 'conocerse',
], $service->getCatalog());
ok(isset($ponder['factores']['compat_ab'], $ponder['factores']['quimica']), 'ponderación tiene factores');
ok(array_key_exists('satisfaccion', $ponder['por_participante'][$rid] ?? [])
    && $ponder['por_participante'][$rid]['satisfaccion'] === null, 'satisfacción direccional no inventada');
$plan = PlanAfinidad::paraParticipante($partida, $rid, 'lug_cafeteria', $service->getCatalog());
ok($plan['aporte'] !== null, 'aporte hobby/lugar calibrado (provisional)');

MemoriaEventos::registrar($partida, 'bronca', [$rid, $idb], 3, 'bronca_fuerte');
ok(MemoriaEventos::enCooldown($partida, 'bronca', [$rid, $idb], $cal) === false, 'sin ventana configurada no suprime');
ok(count(MemoriaEventos::recientes($partida, [$rid], 3)) >= 1, 'memoria registra');

$v2 = SchemaMigrator::migrate(['meta' => ['schema_version' => 2, 'seed' => 'x'], 'residentes' => []]);
ok((int) $v2['meta']['schema_version'] === 3, 'schema sigue v3');
ok(isset($v2['quimica'], $v2['memoria_eventos']), 'campos aditivos química/memoria');

$lab = SimuladorPueblos::ejecutar($root, 1000, 16, 'lab-1000');
ok(($lab['pueblos'] ?? 0) === 1000, 'simulador 1000 pueblos');
ok(($lab['compatibilidad']['n'] ?? 0) > 1000, 'muestras de compatibilidad');
ok(isset($lab['hobbies'][0]['id']), 'ranking hobbies');
echo 'SIM n_hob=' . ($lab['catalogo_hobbies'] ?? '?')
    . ' media_compat=' . ($lab['compatibilidad']['media'] ?? '?')
    . ' min=' . ($lab['compatibilidad']['min'] ?? '?')
    . ' max=' . ($lab['compatibilidad']['max'] ?? '?')
    . ' p50=' . ($lab['compatibilidad']['p50'] ?? '?')
    . ' p90=' . ($lab['compatibilidad']['p90'] ?? '?')
    . ' media_qui=' . ($lab['quimica']['media'] ?? '?')
    . ' asim_ge20=' . ($lab['asimetria_ge20'] ?? '?')
    . ' pares=' . ($lab['pares_unicos'] ?? '?')
    . ' alta=' . ($lab['pares_media_alta_ge70'] ?? '?')
    . ' media=' . ($lab['pares_media_media'] ?? '?')
    . ' baja=' . ($lab['pares_media_baja_le35'] ?? '?')
    . ' clones_pueblo=' . ($lab['pueblos_con_hobby_clon_ge3'] ?? '?')
    . ' aislados=' . ($lab['residentes_max_salida_lt40'] ?? '?')
    . ' no_elegible_edad=' . ($lab['pares_romance_no_elegible_edad'] ?? '?')
    . "\n";

$src = file_get_contents($root . '/api/handlers/EncuentrosHandler.php');
ok(is_string($src) && strpos($src, 'requireDev()') !== false && strpos($src, 'decidirPropuesta') !== false, 'decidir propuesta es DEV');

exit($failures > 0 ? 1 : 0);
