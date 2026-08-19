<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AccionRomantica;
use AquiHayTema\Engine\AcontecimientoDiario;
use AquiHayTema\Engine\AcontecimientoElegibilidad;
use AquiHayTema\Engine\AzarPonderado;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ConsejoEngine;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\ParentescoVeto;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionDesgaste;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SchemaMigrator;
use AquiHayTema\Engine\SimuladorRelacional;

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
ok($cal['_provisional'] === true, 'calibración vida mezclada y provisional');
ok(CalibracionConfig::get($cal, 'pareja.nunca_auto_por_umbral', false) === true, 'pareja nunca auto por umbral');
ok(CalibracionConfig::get($cal, 'crisis.nunca_auto_por_umbral', false) === true, 'crisis nunca auto');
ok(is_numeric(CalibracionConfig::get($cal, 'desgaste_social.base_diaria', null)), 'desgaste social tiene fórmula');
ok(is_numeric(CalibracionConfig::get($cal, 'flechazo.probabilidad', null)), 'flechazo tiene % provisional');

$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'vida-rel-1');
$ph = $service->crearResidentePlaceholderDev($partida);
$a = 'per_qa_valid';
$b = $ph['residente']['catalog_id'];

ok(!RelacionEngine::seConocen($partida, $a, $b), 'sin contacto = desconocidos');

RelacionEngine::upsertSocial($partida, $a, $b, 'amigo', 4);
RelacionEngine::upsertSocial($partida, $b, $a, 'conocido', 1);
ok(RelacionEngine::seConocen($partida, $a, $b), 'tras contacto se conocen');
$ab = RelacionEngine::socialHacia($partida, $a, $b);
$ba = RelacionEngine::socialHacia($partida, $b, $a);
ok(($ab['banda'] ?? '') === 'amigo' && ($ba['banda'] ?? '') === 'conocido', 'social direccional distinto');
ok(RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::SE_CONOCIERON), 'hito se conocieron');

RelacionEngine::setRomanceHacia($partida, $a, $b, 80);
RelacionEngine::setRomanceHacia($partida, $b, $a, 20);
ok(RelacionEngine::romanceHacia($partida, $a, $b) === 80, 'romance A→B');
ok(RelacionEngine::romanceHacia($partida, $b, $a) === 20, 'romance B→A independiente');
ok(ParejaEngine::estado($partida, $a, $b) === ParejaEngine::NINGUNA, 'romance alto NO crea pareja');

$no = ParejaEngine::formar($partida, $a, $b, true, false);
ok(!($no['ok'] ?? true), 'un solo sí no forma pareja');

$si = ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::DECLARACION, $cal);
ok($si['ok'] ?? false, 'hito + ambos sí → pareja');
ok(ParejaEngine::estado($partida, $a, $b) === ParejaEngine::PAREJA, 'estado pareja');
ok(!empty($partida['relaciones_romanticas'][0]['estabilidad_pareja']['activa']), 'estabilidad activa');
ok(is_numeric($partida['relaciones_romanticas'][0]['estabilidad_pareja']['valor']), 'estabilidad inicial calibrada (provisional)');

$romAntes = RelacionEngine::romanceHacia($partida, $a, $b);
ParejaEngine::crisis($partida, $a, $b);
ok(ParejaEngine::estado($partida, $a, $b) === ParejaEngine::CRISIS, 'crisis por hito');
ok(RelacionEngine::romanceHacia($partida, $a, $b) === $romAntes, 'crisis no borra romance');

ParejaEngine::romper($partida, $a, $b, 'lab');
ok(ParejaEngine::estado($partida, $a, $b) === ParejaEngine::EX, 'ex tras ruptura');
ok(RelacionEngine::seConocen($partida, $a, $b), 'tras romper siguen conociéndose');
ok(RelacionEngine::romanceHacia($partida, $a, $b) === 80, 'romance se conserva');
ok($partida['relaciones_romanticas'][0]['estabilidad_pareja']['activa'] === false, 'estabilidad deja de ser operativa');
ok(array_key_exists('memoria', $partida['relaciones_romanticas'][0]['estabilidad_pareja']), 'memoria de estabilidad');

$rec = ParejaEngine::reconciliar($partida, $a, $b, true, true, $cal);
ok($rec['ok'] ?? false, 'reconciliación es vuelta no pareja nueva');
ok(!empty($rec['vuelta']), 'marca vuelta');
ok(RelacionBitacora::familiaCopy($partida, $a, $b) === 'ex_reconexion' || RelacionBitacora::familiaCopy($partida, $a, $b) === 'pareja', 'copy familia reconexión');

$partida['parentesco'][] = ['persona_a' => $a, 'persona_b' => $b, 'tipo' => 'hermano'];
ok(ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal), 'parentesco veta romance');
$store = (new Catalog($root))->store();
$fl = AccionRomantica::evaluar($partida, 'flechazo', $a, $b, $store, $cal);
ok(($fl['elegible'] ?? true) === false && !empty($fl['quimica_no_atraviesa']), 'química no atraviesa veto');
array_pop($partida['parentesco']);

$emo = EstadoEmocional::modificadores('triste');
ok($emo['no_modifica_romance_automatico'] === true, 'tristeza no baja romance sola');
ok(EstadoEmocional::canonId('neutral') === 'neutro', 'alias neutral→neutro');

$ocAntes = $partida['residentes'][$a]['runtime']['ocupacion'] ?? null;
$pj = AcontecimientoDiario::ejecutar($partida, 'perder_trabajo', [$a], $store, $cal);
ok($pj['ok'] ?? false, 'perder trabajo ejecuta');
ok(($partida['residentes'][$a]['runtime']['ocupacion'] ?? '') === 'desempleado', 'pasa a desempleado');
ok(($partida['residentes'][$a]['runtime']['estado_emocional']['id'] ?? '') === 'triste', 'triste tras perder trabajo');
ok(RelacionEngine::romanceHacia($partida, $a, $b) === 80, 'perder trabajo NO toca romance');
$pj2 = AcontecimientoDiario::ejecutar($partida, 'perder_trabajo', [$a], $store, $cal);
ok(!($pj2['ok'] ?? true), 'no pierde trabajo otra vez si ya está desempleado');
ok(AcontecimientoElegibilidad::cumple($partida, $store->item('acontecimientos', 'buscar_trabajo'), [$a], $cal)['ok'], 'desbloquea buscar trabajo');

$plan = AcontecimientoDiario::planificar($partida, $store, $cal, new RngService('dia'));
ok($plan['presupuesto'] !== null && $plan['presupuesto'] > 0, 'presupuesto diario escala con población');
ok((bool) CalibracionConfig::get($cal, 'acontecimientos_dia.activo_en_play', true) === false, 'diario inactivo en play');

$des = RelacionDesgaste::alCerrarDia($partida, $cal);
ok($des['ok'] === true, 'desgaste cierra día');

$rng = new RngService('azar-1');
$t1 = AzarPonderado::tirar($rng, ['malo', 'regular', 'bueno', 'excelente'], 0.8, $cal);
$rngB = new RngService('azar-1');
$t2 = AzarPonderado::tirar($rngB, ['malo', 'regular', 'bueno', 'excelente'], 0.8, $cal);
ok($t1['resultado'] === $t2['resultado'], 'azar ponderado reproducible');
ok($t1['compensacion_obligatoria'] === false, 'sin compensación obligatoria');
ok(AzarPonderado::rachaArtificial(['malo', 'malo'], 'malo', null) === false, 'sin umbral no hay racha forzada');

$ind = EncuentroEngine::programar($partida, [$a], 1, 21, 'individual', 'lug_cafeteria');
ok($ind['ok'] ?? false, 'actividad individual 1 participante');
$dos = EncuentroEngine::validarContexto($partida, [$a], 'conocerse');
ok(!($dos['ok'] ?? true), 'conocerse sigue exigiendo 2');

$msg = BuzonEngine::crear($partida, [
    'texto' => '',
    'clasificacion' => BuzonEngine::IMPORTANTE,
    'de_persona' => $a,
]);
ok(($msg['mensaje']['clasificacion'] ?? '') === 'importante', 'clasificación importante');
$mid = $msg['mensaje']['id'];
$esp = BuzonEngine::marcarEstado($partida, $mid, 'en_espera');
ok(($esp['mensaje']['estado'] ?? '') === 'en_espera', 'buzón en espera');

$cons = ConsejoEngine::responder($partida, $a, 'lanzate', $b, 'romance');
ok($cons['ok'] ?? false, 'consejo registrado');
ok($cons['inclinacion']['efecto_barra'] === false, 'consejo no toca barras');
ok(RelacionEngine::romanceHacia($partida, $a, $b) === 80, 'lánzate no sube romance');

$fl2 = AccionRomantica::ejecutar($partida, 'flechazo', $a, $b, $store, $cal, true);
ok($fl2['ok'] ?? false, 'flechazo forzado (lab) unilateral');
ok(is_int($fl2['delta_romance']) && $fl2['delta_romance'] > 0, 'delta flechazo provisional aplicado');
ok(!empty($fl2['no_crea_pareja']), 'flechazo no crea pareja');
ok(RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::FLECHAZO), 'hito flechazo');

$flores = AccionRomantica::evaluar($partida, 'mandar_flores', $a, $b, $store, $cal);
ok($flores['elegible'] ?? false, 'flores exige historia/interés');

$v2 = SchemaMigrator::migrate(['meta' => ['schema_version' => 2], 'residentes' => [], 'relaciones_sociales' => [
    ['id' => 'soc_x_y', 'persona_a' => 'x', 'persona_b' => 'y', 'tipo' => 'amigos', 'intensidad' => 2],
]]);
ok(isset($v2['bitacora_relaciones'], $v2['parentesco']), 'campos aditivos schema v2');
ok(($v2['relaciones_sociales'][0]['a_hacia_b']['valor'] ?? null) === 2, 'save antiguo espeja dirección');

$lab = SimuladorRelacional::ejecutar($root, 8, [16, 32], 7, 'lab-rel-test');
ok(isset($lab['por_tamano'][16], $lab['por_tamano'][32]), 'lab 16 y 32');
echo 'LAB16 flechazo_el=' . ($lab['por_tamano'][16]['elegibilidad_media_pueblo']['flechazo'] ?? '?')
    . ' perder_trab=' . ($lab['por_tamano'][16]['elegibilidad_media_pueblo']['perder_trabajo'] ?? '?')
    . ' techo_imp=' . ($lab['por_tamano'][16]['techo_mensajes_si_todo_candidato_avisara']['importante_por_pueblo'] ?? '?')
    . ' techo32_imp=' . ($lab['por_tamano'][32]['techo_mensajes_si_todo_candidato_avisara']['importante_por_pueblo'] ?? '?')
    . "\n";

exit($failures > 0 ? 1 : 0);
