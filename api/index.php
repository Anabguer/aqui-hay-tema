<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once __DIR__ . '/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\AgendaHandler;
use AquiHayTema\Api\Handlers\BuzonHandler;
use AquiHayTema\Api\Handlers\DevHandler;
use AquiHayTema\Api\Handlers\DiarioHandler;
use AquiHayTema\Api\Handlers\EncuentrosHandler;
use AquiHayTema\Api\Handlers\LlegadaHandler;
use AquiHayTema\Api\Handlers\MapaHandler;
use AquiHayTema\Api\Handlers\PartidaHandler;
use AquiHayTema\Api\Handlers\PeticionesHandler;
use AquiHayTema\Api\Handlers\RelacionesHandler;
use AquiHayTema\Api\Handlers\RelojHandler;
use AquiHayTema\Api\Handlers\ResidentesHandler;
use function AquiHayTema\Api\jsonOut;
use function AquiHayTema\Api\readBody;
use function AquiHayTema\Api\requirePartida;

header('Content-Type: application/json; charset=utf-8');

$ctx = new ApiContext(dirname(__DIR__));
$action = $_GET['action'] ?? '';
$body = readBody();
$body = array_merge($_GET, $body);

/** @var array<string, callable> */
$routes = [
    'partida.nueva' => static fn() => PartidaHandler::nueva($ctx, $body),
    'partida.listar' => static fn() => PartidaHandler::listar($ctx, $body),
    'partida.cargar' => static fn() => PartidaHandler::cargar($ctx, $body),
    'partida.estado' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PartidaHandler::estado($ctx, $body, $p);
    },
    'partida.guardar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PartidaHandler::guardar($ctx, $body, $p);
    },
    'partida.reiniciar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PartidaHandler::reiniciar($ctx, $body, $p);
    },
    'partida.inspeccionar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PartidaHandler::inspeccionar($ctx, $body, $p);
    },
    'partida.validar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PartidaHandler::validar($ctx, $body, $p);
    },
    'reloj.avanzar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return RelojHandler::avanzar($ctx, $body, $p);
    },
    'reloj.proximo_encuentro' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return RelojHandler::proximoEncuentro($ctx, $body, $p);
    },
    'reloj.ir_a' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return RelojHandler::irA($ctx, $body, $p);
    },
    'residente.ficha' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return ResidentesHandler::ficha($ctx, $body, $p);
    },
    'residente.placeholder' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return ResidentesHandler::placeholder($ctx, $body, $p);
    },
    'residente.incorporar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return ResidentesHandler::incorporar($ctx, $body, $p);
    },
    'vivienda.liberar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return ResidentesHandler::liberarVivienda($ctx, $body, $p);
    },
    'vivienda.resumen' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return ResidentesHandler::vivienda($ctx, $body, $p);
    },
    'agenda.dia' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return AgendaHandler::dia($ctx, $body, $p);
    },
    'agenda.disponibilidad' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return AgendaHandler::disponibilidad($ctx, $body, $p);
    },
    'agenda.slots_compatibles' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return AgendaHandler::slotsCompatibles($ctx, $body, $p);
    },
    'encuentro.programar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::programar($ctx, $body, $p);
    },
    'encuentro.proponer' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::proponer($ctx, $body, $p);
    },
    'encuentro.tipos_permitidos' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::tiposPermitidos($ctx, $body, $p);
    },
    'encuentro.propuesta.decidir' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::decidirPropuesta($ctx, $body, $p);
    },
    'encuentro.propuestas.listar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::listarPropuestas($ctx, $body, $p);
    },
    'encuentro.estado' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::estado($ctx, $body, $p);
    },
    'encuentro.cancelar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::cancelar($ctx, $body, $p);
    },
    'encuentro.sincronizar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::sincronizar($ctx, $body, $p);
    },
    'encuentro.listar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::listar($ctx, $body, $p);
    },
    'cita.programar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::citaProgramar($ctx, $body, $p);
    },
    'cita.estado' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::estado($ctx, $body, $p);
    },
    'cita.cancelar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return EncuentrosHandler::cancelar($ctx, $body, $p);
    },
    'relacion.social' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return RelacionesHandler::social($ctx, $body, $p);
    },
    'relacion.romance' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return RelacionesHandler::romance($ctx, $body, $p);
    },
    'relacion.listar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return RelacionesHandler::listar($ctx, $body, $p);
    },
    'relacion.fase' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return RelacionesHandler::fase($ctx, $body, $p);
    },
    'peticion.listar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PeticionesHandler::listar($ctx, $body, $p);
    },
    'peticion.crear' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PeticionesHandler::crear($ctx, $body, $p);
    },
    'peticion.atender' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PeticionesHandler::atender($ctx, $body, $p);
    },
    'peticion.ignorar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return PeticionesHandler::ignorar($ctx, $body, $p);
    },
    'mapa.presencia' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return MapaHandler::presencia($ctx, $body, $p);
    },
    'buzon.listar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return BuzonHandler::listar($ctx, $body, $p);
    },
    'buzon.leer' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return BuzonHandler::leer($ctx, $body, $p);
    },
    'buzon.crear_dev' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return BuzonHandler::crearDev($ctx, $body, $p);
    },
    'llegada.estado' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return LlegadaHandler::estado($ctx, $body, $p);
    },
    'llegada.aceptar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return LlegadaHandler::aceptar($ctx, $body, $p);
    },
    'llegada.rechazar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return LlegadaHandler::rechazar($ctx, $body, $p);
    },
    'diario.listar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DiarioHandler::listar($ctx, $body, $p);
    },
    'dev.snapshot.guardar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::snapshotGuardar($ctx, $body, $p);
    },
    'dev.snapshot.restaurar' => static function () use ($ctx, $body) {
        return DevHandler::snapshotRestaurar($ctx, $body);
    },
    'dev.snapshot.listar' => static function () use ($ctx, $body) {
        return DevHandler::snapshotListar($ctx, $body);
    },
    'dev.reset.encuentros' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::resetEncuentros($ctx, $body, $p);
    },
    'dev.reset.relaciones' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::resetRelaciones($ctx, $body, $p);
    },
    'dev.reset.buzon_diario' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::resetBuzonDiario($ctx, $body, $p);
    },
    'dev.partida.eliminar' => static fn() => DevHandler::eliminarPartida($ctx, $body),
    'dev.placeholder.eliminar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::eliminarPlaceholder($ctx, $body, $p);
    },
    'dev.encuentro.forzar_resolver' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::forzarResolver($ctx, $body, $p);
    },
    'dev.rng' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::inspeccionarRng($ctx, $body, $p);
    },
    'dev.audit' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::inspeccionarAudit($ctx, $body, $p);
    },
    'npc.planificar_dev' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::npcPlanificar($ctx, $body, $p);
    },
    'npc.coincidencias.ahora' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::npcCoincidenciasAhora($ctx, $body, $p);
    },
    'npc.coincidencias.historico' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::npcCoincidenciasHistorico($ctx, $body, $p);
    },
    'economia.registrar_dev' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::economiaRegistrar($ctx, $body, $p);
    },
    'dev.stress100' => static fn() => DevHandler::stress100($ctx, $body),
    'dev.calendario' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::calendario($ctx, $body, $p);
    },
    'dev.eventos' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::eventos($ctx, $body, $p);
    },
    'dev.diagnostico.export' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::diagnosticoExport($ctx, $body, $p);
    },
    'dev.simular' => static fn() => DevHandler::simular($ctx, $body),
    'dev.catalogos' => static fn() => DevHandler::catalogos($ctx, $body),
    'dev.diversidad' => static fn() => DevHandler::diversidad($ctx, $body),
    'dev.visual.paquetes' => static fn() => DevHandler::visualPaquetes($ctx, $body),
    'dev.visual.preview' => static fn() => DevHandler::visualPreview($ctx, $body),
    'dev.visual.inventario' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::visualInventario($ctx, $body, $p);
    },
    'dev.estado_emocional.forzar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::estadoEmocionalForzar($ctx, $body, $p);
    },
    'dev.expresion.forzar' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::expresionForzar($ctx, $body, $p);
    },
    'dev.visual.vincular' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::visualVincular($ctx, $body, $p);
    },
    'dev.visual.inventario_lab' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::visualInventario($ctx, $body, $p);
    },
    'dev.discovery.campo' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::discoveryCampo($ctx, $body, $p);
    },
    'dev.eventos.correlacionados' => static function () use ($ctx, $body) {
        $p = requirePartida($ctx, $body);
        return DevHandler::eventosCorrelacionados($ctx, $body, $p);
    },
];

try {
    if (!isset($routes[$action])) {
        jsonOut(['ok' => false, 'error' => 'accion_desconocida', 'action' => $action, 'acciones' => array_keys($routes)], 400);
    }
    jsonOut($routes[$action]());
} catch (Throwable $e) {
    $msg = $e->getMessage();
    $code = str_contains($msg, 'partida_no_encontrada') ? \AquiHayTema\Engine\GameError::PARTIDA_NO_ENCONTRADA
        : (str_contains($msg, 'corrupto') ? \AquiHayTema\Engine\GameError::SAVE_CORRUPTO : 'excepcion');
    if ($code === 'excepcion') {
        jsonOut(['ok' => false, 'error' => 'excepcion', 'mensaje' => $msg], 500);
    }
    jsonOut(\AquiHayTema\Engine\GameError::respuesta($code, ['detalle' => $msg], 500));
}
