<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CatchUpPlanner;
use AquiHayTema\Engine\CompatibilidadOculta;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroResultadoVista;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionFase;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\SchemaFields;
use AquiHayTema\Engine\SchemaMigrator;
use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;
use AquiHayTema\Engine\Voluntad\VoluntadPendienteEvaluator;

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

function vidaSetup(): array
{
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('test_fixtures_v0', 'vida-rel');
    $ph = $service->crearResidentePlaceholderDev($partida);
    return [$service, $partida, 'per_qa_valid', $ph['residente']['catalog_id']];
}

$aceptaSiempre = new class implements VoluntadEvaluator {
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'test_acepta',
            'copy_id' => null,
            '_bloqueado_decision' => false,
        ];
    }
};

$rechazaB = new class implements VoluntadEvaluator {
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        $ids = $propuesta['participantes'] ?? [];
        $b = $ids[1] ?? '';
        if ($residenteId === $b) {
            return [
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
                'motivo_tecnico' => 'test_rechaza_voluntad',
                'copy_id' => null,
                '_bloqueado_decision' => false,
            ];
        }
        return [
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'test_acepta',
            'copy_id' => null,
            '_bloqueado_decision' => false,
        ];
    }
};

// --- Schema aditivo sin bump ---
$v2 = ['meta' => ['schema_version' => 2, 'seed' => 's'], 'residentes' => [], 'relaciones_sociales' => []];
$ensured = SchemaMigrator::migrate($v2);
ok((int) $ensured['meta']['schema_version'] === 2, 'schema sigue v2 (sin bump)');
ok(isset($ensured['propuestas_encuentro']), 'ensure propuestas_encuentro');
ok(isset($ensured['peticiones']), 'ensure peticiones');
ok(isset($ensured['relaciones_conflicto']), 'ensure relaciones_conflicto');
ok(isset($ensured['compatibilidad_oculta']['escaner']), 'ensure compatibilidad_oculta');

$old = ['meta' => ['schema_version' => 2], 'relaciones_sociales' => [['id' => 'soc_a_b', 'persona_a' => 'a', 'persona_b' => 'b', 'tipo' => 'conocidos']]];
SchemaFields::ensure($old);
ok(array_key_exists('fase', $old['relaciones_sociales'][0]), 'fase aditiva en save antiguo');
ok($old['relaciones_sociales'][0]['fase'] === null, 'fase inicial null (sin umbral inventado)');

// --- Propuesta: indisponibilidad (trabajo lunes 11h, autónomo 10-18) ---
[$service, $partida, $ida, $idb] = vidaSetup();
$rInd = PropuestaEncuentroEngine::proponer($partida, [$ida, $idb], 1, 11, 'conocerse', null, null, new VoluntadPendienteEvaluator());
ok($rInd['ok'] ?? false, 'propuesta registrada a las 11h (trabajo)');
ok(($rInd['rechazada'] ?? false) === false, '11h ocupada no aborta: busca siguiente franja');
ok((int) ($rInd['propuesta']['hora'] ?? 11) !== 11, 'hora resultante distinta de 11');
ok(($rInd['programado'] ?? true) === false, 'pendiente no programa todavía');

// --- Propuesta: voluntad pendiente (hora libre 19) no programa ---
[$service, $partida, $ida, $idb] = vidaSetup();
$rPend = PropuestaEncuentroEngine::proponer($partida, [$ida, $idb], 1, 19, 'conocerse', null, null, new VoluntadPendienteEvaluator());
ok($rPend['ok'] ?? false, 'propuesta ok con voluntad pendiente');
ok(($rPend['programado'] ?? true) === false, 'pendiente no programa');
ok(($rPend['propuesta']['estado'] ?? '') === 'propuesta', 'estado propuesta');
ok(($rPend['propuesta']['reacciones'][0]['decision'] ?? '') === PropuestaEncuentro::DECISION_PENDIENTE, 'A pendiente');
ok(($rPend['propuesta']['reacciones'][1]['decision'] ?? '') === PropuestaEncuentro::DECISION_PENDIENTE, 'B pendiente');
ok(!empty($rPend['propuesta']['reacciones'][0]['_bloqueado_decision']), 'fórmula voluntad bloqueada');
ok(count($partida['encuentros'] ?? []) === 0, 'legacy programar no se llamó');

$confPend = PropuestaEncuentroEngine::confirmarSiProcede($partida, (string) $rPend['propuesta']['id']);
ok(!($confPend['ok'] ?? true), 'confirmar pendiente = no ok');
ok(($confPend['error'] ?? '') === GameError::PROPUESTA_PENDIENTE, 'código PROPUESTA_PENDIENTE');

// --- Aceptación explícita A y B → programa ---
$dA = PropuestaEncuentroEngine::registrarDecision($partida, (string) $rPend['propuesta']['id'], $ida, true);
ok($dA['ok'] ?? false, 'A acepta');
ok(($dA['programado'] ?? true) === false, 'un solo sí no basta');
$dB = PropuestaEncuentroEngine::registrarDecision($partida, (string) $rPend['propuesta']['id'], $idb, true);
ok($dB['ok'] ?? false, 'B acepta y programa');
ok(($dB['programado'] ?? false) === true, 'ambos sí → programado');
ok(isset($dB['encuentro']['id']), 'encuentro creado tras aceptación');
ok(($dB['propuesta']['estado'] ?? '') === 'programada', 'estado programada');

// playtest legacy programar sigue existiendo
[$service, $partida, $ida, $idb] = vidaSetup();
$legacy = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse');
ok($legacy['ok'] ?? false, 'encuentro.programar legacy sigue funcionando');

// --- Rechazo por voluntad ---
[$service, $partida, $ida, $idb] = vidaSetup();
$rVol = PropuestaEncuentroEngine::proponer($partida, [$ida, $idb], 1, 19, 'conocerse', null, null, $rechazaB);
ok(($rVol['rechazada'] ?? false) === true, 'rechazo voluntad');
ok(($rVol['rechazo_clase'] ?? '') === PropuestaEncuentro::CLASE_VOLUNTAD, 'clase voluntad');
ok(($rVol['programado'] ?? true) === false, 'voluntad no programa');
ok(array_key_exists('copy_id', $rVol['propuesta']['reacciones'][1]) && $rVol['propuesta']['reacciones'][1]['copy_id'] === null, 'copy_id preparado y vacío');

// --- Vertical slice dominio: proponer → aceptar → encuentro → relación ---
[$service, $partida, $ida, $idb] = vidaSetup();
$slice = PropuestaEncuentroEngine::proponer($partida, [$ida, $idb], 1, 19, 'conocerse', null, null, $aceptaSiempre);
ok(($slice['programado'] ?? false) === true, 'evaluator test acepta ambos → programa');
$encId = $slice['encuentro']['id'] ?? '';
$adv = $service->avanzarReloj($partida, 16);
ok(($adv['ok'] ?? false) === true, 'avanzar reloj resuelve encuentro');
$rel = RelacionEngine::obtenerEntre($partida, $ida, $idb);
ok(RelacionEngine::seConocen($partida, $ida, $idb), 'tras encuentro son conocidos');
ok(is_int(RelacionEngine::valorSocialHacia($partida, $ida, $idb)), 'canal social numérico real');
ok(($rel['romance'] ?? null) === null || ($rel['romance']['vinculo'] ?? null) === null, 'romance independiente no forzado');
ok(array_key_exists('fase', $rel['social']), 'fase presente en relación');
ok($rel['social']['fase'] === null, 'fase no auto-asignada');

$raw = null;
foreach ($partida['encuentros'] as $enc) {
    if (($enc['id'] ?? '') === $encId) {
        $raw = $enc;
        break;
    }
}
ok(is_array($raw) && ($raw['estado'] ?? '') === 'terminado', 'encuentro terminado');
$vista = EncuentroResultadoVista::de($partida, $raw, $service->getCatalog(), $root);
$jsVista = json_encode($vista);
ok($jsVista !== false && strpos($jsVista, 'compatibilidad') === false, 'DTO play sin compatibilidad');
ok(is_int($vista['resultado']['social']['delta'] ?? null), 'consecuencia visible social en DTO');
$ficha = $service->fichaResidente($partida, $ida);
$jsFicha = json_encode($ficha);
ok($jsFicha !== false && strpos($jsFicha, 'compatibilidad_oculta') === false, 'ficha sin compatibilidad_oculta');
$est = $service->estadoResumido($partida);
ok(!isset($est['compatibilidad_oculta']), 'estado resumido no expone compatibilidad');
ok(CompatibilidadOculta::esVisibleJugador($partida, $ida, $idb) === false, 'compatibilidad oculta al jugador');
ok(($partida['compatibilidad_oculta']['escaner']['desbloqueado'] ?? true) === false, 'escáner no desbloqueado');

// --- Fases ---
ok(RelacionFase::transicionValida(null, RelacionFase::ESTABLE), 'null → estable');
ok(RelacionFase::transicionValida(RelacionFase::ESTABLE, RelacionFase::TENSION), 'estable → tensión');
ok(RelacionFase::transicionValida(RelacionFase::TENSION, RelacionFase::CRISIS), 'tensión → crisis');
ok(RelacionFase::transicionValida(RelacionFase::CRISIS, RelacionFase::POSIBLE_RUPTURA), 'crisis → posible ruptura');
ok(!RelacionFase::transicionValida(RelacionFase::ESTABLE, RelacionFase::POSIBLE_RUPTURA), 'no salto estable → ruptura');
$faseOk = RelacionEngine::aplicarFase($partida, $ida, $idb, 'social', RelacionFase::ESTABLE);
ok($faseOk['ok'] ?? false, 'aplicar fase estable explícita');
$faseBad = RelacionEngine::aplicarFase($partida, $ida, $idb, 'social', RelacionFase::CRISIS);
ok(!($faseBad['ok'] ?? true), 'no salta a crisis sin tensión');
ok(($faseBad['error'] ?? '') === GameError::FASE_TRANSICION_INVALIDA, 'FASE_TRANSICION_INVALIDA');

// --- Conflicto canal independiente ---
[$service, $partida, $ida, $idb] = vidaSetup();
$c1 = RelacionEngine::upsertConflicto($partida, $ida, $idb, null, 'roce');
ok($c1['ok'] ?? false, 'canal conflicto independiente');
$relC = RelacionEngine::obtenerEntre($partida, $ida, $idb);
ok($relC['conflicto'] !== null && $relC['social'] !== null, 'conflicto ≠ social');
ok($relC['conflicto']['intensidad'] === null, 'intensidad conflicto no inventada');

// --- Peticiones ---
[$service, $partida, $ida, $idb] = vidaSetup();
$pet = PeticionEngine::crear($partida, $ida, 'lugar', [
    'objetivo' => 'lug_cafeteria',
    'plazo_dia' => 1,
    'plazo_hora' => 10,
]);
ok($pet['ok'] ?? false, 'petición creada');
ok(($pet['peticion']['estado'] ?? '') === 'abierta', 'petición abierta');
ok($pet['peticion']['evolucion_si_ignora'] === null, 'evolución ignorar no inventada');
ok(count(PeticionEngine::listar($partida, 'abierta')) === 1, 'listar abiertas');
ok(count($partida['buzon'] ?? []) === 1, 'buzón recibe petición');
$ign = PeticionEngine::ignorar($partida, (string) $pet['peticion']['id']);
ok(($ign['peticion']['estado'] ?? '') === 'ignorada', 'ignorar cambia estado');
ok($ign['peticion']['evolucion_si_ignora'] === null, 'ignorar no inventa consecuencia');

$pet2 = PeticionEngine::crear($partida, $idb, 'tiempo', ['plazo_dia' => 1, 'plazo_hora' => 9]);
ok(($pet2['peticion']['estado'] ?? '') === 'abierta', 'petición con plazo futuro abierta');
ok(PeticionEngine::caducarVencidas($partida) === 0, 'no caduca antes de plazo');
$advPet = $service->avanzarReloj($partida, 2);
ok(((int) ($advPet['peticiones_caducadas'] ?? 0)) === 1, 'reloj caduca petición vencida');
ok(count(PeticionEngine::listar($partida, 'caducada')) === 1, 'queda una caducada');
ok(count(PeticionEngine::listar($partida, 'abierta')) === 0, 'no quedan abiertas tras caducar');

// --- Catch-up planifica, no ejecuta ---
$plan = CatchUpPlanner::planificar(3 * 86400);
ok($plan['ejecutado'] === false, 'catch-up no ejecuta');
ok($plan['eventos_generados'] === [], 'sin eventos inventados');
ok($plan['cantidades'] === null, 'sin cantidades');
ok($plan['dias_calendario_aprox'] === 3, 'días = aritmética de reloj, no diseño');
ok($plan['prioridades'][0] === 'pequenas_novedades', 'prioridad novedades pequeñas');
ok(in_array('acontecimiento_importante', $plan['prioridades'], true), 'prioridad acontecimiento ocasional');

[$service, $partida, $ida, $idb] = vidaSetup();
$hace = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->sub(new DateInterval('P3D'));
$partida['reloj']['ultima_sesion_iso'] = $hace->format(DATE_ATOM);
$cu = Reloj::calcularCatchUpPendiente($partida);
ok(($cu['plan']['ejecutado'] ?? true) === false, 'catch-up al cargar no simula pueblo');
ok(($partida['reloj']['catch_up_pendiente']['eventos_pendientes'] ?? ['x']) === [], 'eventos_pendientes vacío');
ok(isset($partida['reloj']['catch_up_pendiente']['plan']['prioridades']), 'plan enganchado al reloj');

exit($failures > 0 ? 1 : 0);
