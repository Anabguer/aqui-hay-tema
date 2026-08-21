<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AccionRomantica;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CompatibilidadCalculator;
use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\CopyDescubrimiento;
use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionBandas;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionVistaJugador;
use AquiHayTema\Engine\RomanceElegibilidad;
use AquiHayTema\Engine\SenalRomantica;
use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;

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

DomainBootstrap::boot();
$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$store = $catalog->store();

$p = $service->nuevaPartida('playtest_01', 'rel-v1-a');
$a = 'per_p001';
$b = 'per_p002';

// A) desconocidos → solo Conocerse
ok(PropuestaNivel::tiposPermitidos($p, $a, $b, $cal) === ['conocerse'], 'A desconocidos solo conocerse');
ok(PropuestaNivel::permite($p, $a, $b, 'quedar', $cal) === false, 'A no quedar');
ok(PropuestaNivel::permite($p, $a, $b, 'primera_cita', $cal) === false, 'A no primera cita');

// B) se conocen → Quedar
RelacionEngine::registrarContacto($p, $a, $b, 'normal', $cal);
RelacionEngine::registrarContacto($p, $b, $a, 'normal', $cal);
$tiposB = PropuestaNivel::tiposPermitidos($p, $a, $b, $cal);
ok(in_array('quedar', $tiposB, true), 'B aparece Quedar');
ok(!in_array('conocerse', $tiposB, true), 'B ya no conocerse');

// C) sin señal → no Primera cita
ok(!in_array('primera_cita', $tiposB, true), 'C sin señal no primera cita');
ok(!in_array('cita', $tiposB, true), 'C sin señal no cita');
ok(!SenalRomantica::desbloqueaPrimeraCita($p, $a, $b, $cal), 'C gate cerrado');

// D) señal A→B → Primera cita
RelacionEngine::setRomanceHacia($p, $a, $b, 10);
ok(SenalRomantica::desbloqueaPrimeraCita($p, $a, $b, $cal), 'D gate abierto');
$tiposD = PropuestaNivel::tiposPermitidos($p, $a, $b, $cal);
ok(in_array('primera_cita', $tiposD, true), 'D aparece Primera cita');
ok(in_array('quedar', $tiposD, true), 'D Quedar sigue');

// E) unilateral
ok(SenalRomantica::desdeHacia($p, $a, $b, $cal)['ok'] === true, 'E A→B hay señal');
ok(SenalRomantica::desdeHacia($p, $b, $a, $cal)['ok'] === false, 'E B→A no hay señal');
ok((RelacionEngine::romanceHacia($p, $b, $a) ?? 0) < 8, 'E valor B→A bajo');

// F) primera cita aceptada → encuentro real → deltas
$pF = $service->nuevaPartida('playtest_01', 'rel-v1-f');
$pF['reloj']['hora_actual'] = 8;
$pF['reloj']['minuto_actual'] = 0;
RelacionEngine::registrarContacto($pF, $a, $b, 'normal', $cal);
RelacionEngine::registrarContacto($pF, $b, $a, 'normal', $cal);
RelacionEngine::setRomanceHacia($pF, $a, $b, 12);
$horaF = 19;
$encF = EncuentroEngine::programar($pF, [$a, $b], 1, $horaF, 'primera_cita', 'lug_cafeteria');
ok(($encF['ok'] ?? false) === true, 'F programa primera cita ' . (string) ($encF['error'] ?? ''));
$horaNow = (int) ($pF['reloj']['hora_actual'] ?? 8);
$diaNow = (int) ($pF['reloj']['dia_pueblo'] ?? 1);
$horasF = (1 - $diaNow) * 24 + ($horaF - $horaNow) + 2;
if ($horasF < 2) {
    $horasF = 2;
}
$advF = $service->avanzarReloj($pF, $horasF);
ok(($advF['ok'] ?? false) === true, 'F avanza reloj');
$visto = null;
foreach ($pF['encuentros'] ?? [] as $enc) {
    if (($enc['id'] ?? '') === ($encF['encuentro']['id'] ?? '')) {
        $visto = $enc;
    }
}
ok(($visto['estado'] ?? '') === 'terminado', 'F encuentro terminado');
ok(!empty($visto['resultado']['_deltas_reales']), 'F deltas reales');
ok(isset($visto['resultado']['delta_social']['a_hacia_b']), 'F hay delta social');
ok(SenalRomantica::yaHuboPrimeraCita($pF, $a, $b), 'F hito primera_cita');
ok(ParejaEngine::estado($pF, $a, $b) !== ParejaEngine::PAREJA, 'F no crea pareja');
$tiposTrasF = PropuestaNivel::tiposPermitidos($pF, $a, $b, $cal);
ok(in_array('cita', $tiposTrasF, true), 'F después aparece Cita');
ok(!in_array('primera_cita', $tiposTrasF, true), 'F ya no Primera cita');

// G) primera cita rechazada
$pG = $service->nuevaPartida('playtest_01', 'rel-v1-g');
RelacionEngine::registrarContacto($pG, $a, $b, 'normal', $cal);
RelacionEngine::setRomanceHacia($pG, $a, $b, 12);
$romG0 = RelacionEngine::romanceHacia($pG, $a, $b);
$rechaza = new class implements VoluntadEvaluator {
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
            'motivo_tecnico' => 'test_rechazo',
            'motivo_tipo' => 'banal',
            'copy_id' => 'hoy_no_me_da_la_vida',
            'score' => 10,
            'p' => 0.0,
        ];
    }
};
$propG = PropuestaEncuentroEngine::proponer($pG, [$a, $b], 1, 11, 'primera_cita', 'lug_cafeteria', null, $rechaza);
ok(!empty($propG['rechazada']) || ($propG['ok'] ?? true) === false || ($propG['propuesta']['estado'] ?? '') === 'rechazada', 'G propuesta rechazada');
ok(ParejaEngine::estado($pG, $a, $b) !== ParejaEngine::PAREJA, 'G no pareja');
ok(SenalRomantica::yaHuboPrimeraCita($pG, $a, $b) === false, 'G no hito primera cita');
ok((RelacionEngine::romanceHacia($pG, $a, $b) ?? 0) <= (int) $romG0 + 1, 'G no fuerza romance');

// H) amigo + romance
$pH = $service->nuevaPartida('playtest_01', 'rel-v1-h');
RelacionEngine::registrarContacto($pH, $a, $b, 'normal', $cal);
RelacionEngine::registrarContacto($pH, $b, $a, 'normal', $cal);
$relH = RelacionEngine::obtenerEntre($pH, $a, $b)['social'];
$relH['a_hacia_b']['valor'] = 40;
$relH['a_hacia_b']['banda'] = RelacionBandas::social(40, true, $cal);
$relH['b_hacia_a']['valor'] = 40;
$relH['b_hacia_a']['banda'] = RelacionBandas::social(40, true, $cal);
RelacionEngine::persistirSocial($pH, $relH);
RelacionEngine::setRomanceHacia($pH, $a, $b, 20);
ok(RelacionBandas::social(RelacionEngine::valorSocialHacia($pH, $a, $b), true, $cal) === 'amigo', 'H banda amigo');
ok((RelacionEngine::romanceHacia($pH, $a, $b) ?? 0) === 20, 'H romance convive');

$relH = RelacionEngine::obtenerEntre($pH, $a, $b)['social'];
$relH['a_hacia_b']['valor'] = 82;
$relH['a_hacia_b']['banda'] = RelacionBandas::social(82, true, $cal);
RelacionEngine::persistirSocial($pH, $relH);
RelacionEngine::setRomanceHacia($pH, $a, $b, 30);
ok(RelacionBandas::social(RelacionEngine::valorSocialHacia($pH, $a, $b), true, $cal) === 'mejor_amigo', 'I mejor amigo');
ok((RelacionEngine::romanceHacia($pH, $a, $b) ?? 0) === 30, 'I romance convive');

// J) pareja conserva social
$socAntes = RelacionEngine::valorSocialHacia($pH, $a, $b);
ParejaEngine::formar($pH, $a, $b, true, true, RelacionBitacora::DECLARACION, $cal);
ok(ParejaEngine::estado($pH, $a, $b) === ParejaEngine::PAREJA, 'J son pareja');
ok(RelacionEngine::valorSocialHacia($pH, $a, $b) === $socAntes, 'J social intacto');
ok(RelacionVistaJugador::de($pH, $a, $b, $cal)['etiqueta_vinculo'] === 'pareja', 'J etiqueta pareja');

// K) parentesco veta
$pK = $service->nuevaPartida('playtest_01', 'rel-v1-k');
RelacionEngine::registrarContacto($pK, $a, $b, 'normal', $cal);
RelacionEngine::setRomanceHacia($pK, $a, $b, 40);
$relK = RelacionEngine::obtenerEntre($pK, $a, $b)['social'];
$relK['es_familiar'] = true;
$relK['veta_romance'] = true;
RelacionEngine::persistirSocial($pK, $relK);
ok(SenalRomantica::desbloqueaPrimeraCita($pK, $a, $b, $cal) === false, 'K parentesco cierra gate');
ok(!in_array('primera_cita', PropuestaNivel::tiposPermitidos($pK, $a, $b, $cal), true), 'K no Primera cita');

// L) edad veta (gap > límite duro; no parentesco)
$pL = $service->nuevaPartida('playtest_01', 'rel-v1-l');
RelacionEngine::registrarContacto($pL, $a, $b, 'normal', $cal);
if (isset($pL['residentes'][$a]['runtime']['perfil_partida']) && is_array($pL['residentes'][$a]['runtime']['perfil_partida'])) {
    $pL['residentes'][$a]['runtime']['perfil_partida']['edad'] = 20;
}
if (isset($pL['residentes'][$b]['runtime']['perfil_partida']) && is_array($pL['residentes'][$b]['runtime']['perfil_partida'])) {
    $pL['residentes'][$b]['runtime']['perfil_partida']['edad'] = 72;
}
RelacionEngine::setRomanceHacia($pL, $a, $b, 40);
ok((RomanceElegibilidad::par($pL, $a, $b, $cal)['motivo'] ?? '') === 'edad_limite_duro', 'L motivo edad');
ok(SenalRomantica::desbloqueaPrimeraCita($pL, $a, $b, $cal) === false, 'L veto edad');
ok(!in_array('primera_cita', PropuestaNivel::tiposPermitidos($pL, $a, $b, $cal), true), 'L no Primera cita');

// M) personalidad influye
$perfM1 = [
    'hobbies' => ['cine'],
    'rasgos' => ['bromista'],
    'indicadores_visuales' => [],
    'preferencias' => [
        'personalidad_pos' => ['bromista'],
        'personalidad_neg' => [],
        'visual_pos' => [],
        'visual_neg' => [],
        'hobbies_pos' => [],
        'hobbies_neg' => [],
    ],
];
$perfM2 = $perfM1;
$perfM2['preferencias']['personalidad_pos'] = ['timido'];
$otroM = ['hobbies' => ['cine'], 'rasgos' => ['bromista'], 'indicadores_visuales' => [], 'preferencias' => []];
ok(CompatibilidadCalculator::aHaciaB($perfM1, $otroM, $cal)['total'] > CompatibilidadCalculator::aHaciaB($perfM2, $otroM, $cal)['total'], 'M personalidad_pos influye');

// N) visual influye
$perfN1 = [
    'hobbies' => [],
    'rasgos' => [],
    'indicadores_visuales' => [],
    'preferencias' => [
        'personalidad_pos' => [],
        'personalidad_neg' => [],
        'visual_pos' => ['gafas'],
        'visual_neg' => [],
        'hobbies_pos' => [],
        'hobbies_neg' => [],
    ],
];
$perfN2 = $perfN1;
$perfN2['preferencias']['visual_pos'] = [];
$otroN = ['hobbies' => [], 'rasgos' => [], 'indicadores_visuales' => ['gafas'], 'preferencias' => []];
ok(CompatibilidadCalculator::aHaciaB($perfN1, $otroN, $cal)['total'] > CompatibilidadCalculator::aHaciaB($perfN2, $otroN, $cal)['total'], 'N visual_pos influye');

// O) hobbies_pos/neg influyen
$perfO1 = [
    'hobbies' => ['bingo'],
    'rasgos' => [],
    'indicadores_visuales' => [],
    'preferencias' => [
        'personalidad_pos' => [],
        'personalidad_neg' => [],
        'visual_pos' => [],
        'visual_neg' => [],
        'hobbies_pos' => ['cine'],
        'hobbies_neg' => [],
    ],
];
$perfO2 = $perfO1;
$perfO2['preferencias']['hobbies_pos'] = [];
$perfO2['preferencias']['hobbies_neg'] = ['cine'];
$otroO = ['hobbies' => ['cine'], 'rasgos' => [], 'indicadores_visuales' => [], 'preferencias' => []];
ok(CompatibilidadCalculator::aHaciaB($perfO1, $otroO, $cal)['total'] > CompatibilidadCalculator::aHaciaB($perfO2, $otroO, $cal)['total'], 'O hobbies_pos/neg influyen');

// P) discovery persistido
$pP = $service->nuevaPartida('playtest_01', 'rel-v1-p');
$campoP = ConocimientoNpc::campoRechazo('personalidad', 'cabezota');
DiscoveryReveal::registrarJugador($pP, $a, $campoP, 'cabezota', 'test');
ok(DiscoveryEngine::estado($pP, $a, $campoP) === DiscoveryEngine::DESCUBIERTO, 'P descubierto en memoria');
$service->guardar($pP);
$loaded = $service->cargar((string) $pP['meta']['partida_id']);
ok(DiscoveryEngine::estado($loaded, $a, $campoP) === DiscoveryEngine::DESCUBIERTO, 'P persistido tras save/load');
$fichaP = $service->fichaResidente($loaded, $a);
$pistas = $fichaP['vista_play']['pistas'] ?? [];
$copy = CopyDescubrimiento::texto('Carmen', $campoP, 'cabezota', $store);
ok(is_string($copy) && strpos($copy, 'no soporta') !== false, 'P copy humano sin ID técnico');
ok($copy !== null && strpos($copy, 'personalidad_neg') === false, 'P no expone clave técnica');
ok($pistas === [] || strpos(implode(' ', $pistas), 'rechazo_personalidad') === false, 'P ficha no muestra campo técnico');

// J extra: flechazo unilateral
$pFl = $service->nuevaPartida('playtest_01', 'rel-v1-flechazo');
RelacionEngine::registrarContacto($pFl, $a, $b, 'normal', $cal);
RelacionEngine::registrarContacto($pFl, $b, $a, 'normal', $cal);
$fl = AccionRomantica::ejecutar($pFl, 'flechazo', $a, $b, $store, $cal, true);
ok(($fl['ok'] ?? false) === true, 'J flechazo ok');
ok(($fl['unilateral'] ?? false) === true, 'J flechazo unilateral flag');
ok((RelacionEngine::romanceHacia($pFl, $a, $b) ?? 0) >= 8, 'J A→B sube');
ok((RelacionEngine::romanceHacia($pFl, $b, $a) ?? 0) < 8, 'J B→A no hereda');
ok(SenalRomantica::desdeHacia($pFl, $a, $b, $cal)['ok'] === true, 'J señal A→B');
ok(SenalRomantica::desdeHacia($pFl, $b, $a, $cal)['ok'] === false, 'J sin señal B→A');

$buzon = $pFl['buzon'] ?? [];
$hayAviso = false;
foreach ($buzon as $msg) {
    if (($msg['tipo'] ?? '') !== 'senal_romantica') {
        continue;
    }
    $txt = (string) ($msg['texto'] ?? '');
    $tieneJose = strpos($txt, 'José') !== false || strpos($txt, 'Jose') !== false;
    if (strpos($txt, 'Carmen') !== false && $tieneJose) {
        $hayAviso = true;
    }
}
ok($hayAviso, 'aviso Celestine con dirección de nombres');

exit($failures > 0 ? 1 : 0);
