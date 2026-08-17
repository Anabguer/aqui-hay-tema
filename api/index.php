<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\AutonomousPlanner;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CitaEngine;
use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\EconomyLedger;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$service = new PartidaService($root);

function jsonOut(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function readBody(): array
{
    $raw = file_get_contents('php://input') ?: '{}';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requirePartida(PartidaService $service, array $body): array
{
    $id = $body['partida_id'] ?? ($_GET['partida_id'] ?? null);
    if (!$id) {
        jsonOut(['ok' => false, 'error' => 'partida_id_requerido'], 400);
    }
    try {
        return $service->cargar((string) $id);
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => 'partida_no_encontrada', 'detalle' => $e->getMessage()], 404);
    }
}

$action = $_GET['action'] ?? '';
$body = readBody();

try {
    switch ($action) {
        case 'partida.nueva':
            $config = $body['config_id'] ?? 'debug_v0';
            $seed = $body['seed'] ?? null;
            $partida = $service->nuevaPartida($config, is_string($seed) ? $seed : null);
            jsonOut(['ok' => true, 'partida' => $service->estadoResumido($partida), 'partida_id' => $partida['meta']['partida_id']]);

        case 'partida.listar':
            jsonOut(['ok' => true, 'partidas' => $service->listarPartidas()]);

        case 'partida.estado':
            $partida = requirePartida($service, array_merge($body, $_GET));
            jsonOut(['ok' => true, 'estado' => $service->estadoResumido($partida)]);

        case 'partida.guardar':
            $partida = requirePartida($service, $body);
            $service->guardar($partida);
            jsonOut(['ok' => true, 'guardado' => true]);

        case 'partida.cargar':
            $id = $body['partida_id'] ?? null;
            if (!$id) {
                jsonOut(['ok' => false, 'error' => 'partida_id_requerido'], 400);
            }
            $partida = $service->cargar((string) $id);
            jsonOut(['ok' => true, 'partida_id' => $id, 'estado' => $service->estadoResumido($partida)]);

        case 'partida.reiniciar':
            $partida = requirePartida($service, $body);
            $id = $partida['meta']['partida_id'];
            $nueva = $service->reiniciarPartida($id, $body['config_id'] ?? 'debug_v0', $body['seed'] ?? null);
            jsonOut([
                'ok' => true,
                'partida_id' => $id,
                'nota' => 'Reiniciar conserva partida_id; nueva partida usa partida.nueva',
                'estado' => $service->estadoResumido($nueva),
            ]);

        case 'partida.inspeccionar':
            $partida = requirePartida($service, array_merge($body, $_GET));
            jsonOut(['ok' => true, 'partida' => $partida]);

        case 'reloj.avanzar':
            $partida = requirePartida($service, $body);
            $result = $service->avanzarReloj($partida, (int) ($body['horas'] ?? 1));
            $service->guardar($partida);
            jsonOut(['ok' => true, 'reloj' => $result]);

        case 'residente.ficha':
            $partida = requirePartida($service, array_merge($body, $_GET));
            $rid = $body['residente_id'] ?? ($_GET['residente_id'] ?? null);
            if (!$rid) {
                jsonOut(['ok' => false, 'error' => 'residente_id_requerido'], 400);
            }
            jsonOut(['ok' => true, 'ficha' => $service->fichaResidente($partida, (string) $rid)]);

        case 'residente.placeholder':
            $partida = requirePartida($service, $body);
            $r = $service->crearResidentePlaceholderDev($partida);
            $service->guardar($partida);
            jsonOut(['ok' => true, 'resultado' => $r]);

        case 'residente.incorporar':
            $partida = requirePartida($service, $body);
            $r = $service->incorporarResidenteCatalogo($partida, (string) ($body['catalog_id'] ?? ''));
            $service->guardar($partida);
            jsonOut(['ok' => $r['ok'], 'resultado' => $r]);

        case 'vivienda.liberar':
            $partida = requirePartida($service, $body);
            $r = $service->liberarVivienda($partida, (string) ($body['vivienda_id'] ?? ''));
            $service->guardar($partida);
            jsonOut(['ok' => true, 'resultado' => $r]);

        case 'agenda.dia':
            $partida = requirePartida($service, array_merge($body, $_GET));
            $rid = $body['residente_id'] ?? ($_GET['residente_id'] ?? null);
            $dia = isset($body['dia']) ? (int) $body['dia'] : (int) $partida['reloj']['dia_pueblo'];
            if (!$rid) {
                jsonOut(['ok' => false, 'error' => 'residente_id_requerido'], 400);
            }
            jsonOut(['ok' => true, 'agenda' => AgendaEngine::resolverDia($partida, (string) $rid, $dia)]);

        case 'agenda.disponibilidad':
            $partida = requirePartida($service, array_merge($body, $_GET));
            $rid = $body['residente_id'] ?? null;
            if (!$rid) {
                jsonOut(['ok' => false, 'error' => 'residente_id_requerido'], 400);
            }
            jsonOut(['ok' => true, 'disponibilidad' => AgendaEngine::estaDisponible(
                $partida,
                (string) $rid,
                (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
                (int) ($body['hora'] ?? 0)
            )]);

        case 'encuentro.programar':
            $partida = requirePartida($service, $body);
            $r = $service->programarEncuentro(
                $partida,
                is_array($body['participantes'] ?? null) ? $body['participantes'] : [
                    (string) ($body['residente_a'] ?? ''),
                    (string) ($body['residente_b'] ?? ''),
                ],
                (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
                (int) ($body['hora'] ?? 17),
                (string) ($body['tipo'] ?? 'conocerse'),
                isset($body['lugar']) ? (string) $body['lugar'] : null
            );
            if ($r['ok'] ?? false) {
                $service->guardar($partida);
            }
            jsonOut($r);

        case 'encuentro.estado':
            $partida = requirePartida($service, $body);
            $r = EncuentroEngine::cambiarEstado($partida, (string) ($body['encuentro_id'] ?? ''), (string) ($body['estado'] ?? ''));
            if ($r['ok'] ?? false) {
                $service->guardar($partida);
            }
            jsonOut($r);

        case 'encuentro.cancelar':
            $partida = requirePartida($service, $body);
            $r = EncuentroEngine::cancelar($partida, (string) ($body['encuentro_id'] ?? ''));
            if ($r['ok'] ?? false) {
                $service->guardar($partida);
            }
            jsonOut($r);

        case 'encuentro.sincronizar':
            $partida = requirePartida($service, $body);
            $r = EncuentroLifecycle::sincronizarConReloj($partida, $service->getLogger());
            $service->guardar($partida);
            jsonOut(['ok' => true, 'resultado' => $r]);

        case 'cita.programar':
            $partida = requirePartida($service, $body);
            $r = CitaEngine::programar(
                $partida,
                (string) ($body['residente_a'] ?? ''),
                (string) ($body['residente_b'] ?? ''),
                (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
                (int) ($body['hora'] ?? 17),
                isset($body['lugar']) ? (string) $body['lugar'] : null,
                isset($body['actividad']) ? (string) $body['actividad'] : null
            );
            if ($r['ok'] ?? false) {
                $service->guardar($partida);
            }
            jsonOut($r);

        case 'cita.estado':
        case 'cita.cancelar':
            $partida = requirePartida($service, $body);
            $id = (string) ($body['cita_id'] ?? $body['encuentro_id'] ?? '');
            $r = $action === 'cita.cancelar'
                ? CitaEngine::cancelar($partida, $id)
                : CitaEngine::cambiarEstado($partida, $id, (string) ($body['estado'] ?? ''));
            if ($r['ok'] ?? false) {
                $service->guardar($partida);
            }
            jsonOut($r);

        case 'relacion.social':
            $partida = requirePartida($service, $body);
            $r = RelacionEngine::upsertSocial(
                $partida,
                (string) ($body['persona_a'] ?? ''),
                (string) ($body['persona_b'] ?? ''),
                (string) ($body['tipo'] ?? 'conocidos'),
                isset($body['intensidad']) ? (int) $body['intensidad'] : null,
                isset($body['se_soportan']) ? (bool) $body['se_soportan'] : null
            );
            $service->guardar($partida);
            jsonOut($r);

        case 'relacion.romance':
            $partida = requirePartida($service, $body);
            $r = RelacionEngine::upsertRomance(
                $partida,
                (string) ($body['persona_a'] ?? ''),
                (string) ($body['persona_b'] ?? ''),
                is_array($body['valores'] ?? null) ? $body['valores'] : []
            );
            $service->guardar($partida);
            jsonOut($r);

        case 'mapa.presencia':
            $partida = requirePartida($service, array_merge($body, $_GET));
            jsonOut(['ok' => true, 'mapa' => PresenciaEngine::resolver($partida, $root)]);

        case 'buzon.listar':
            $partida = requirePartida($service, array_merge($body, $_GET));
            jsonOut(['ok' => true, 'mensajes' => BuzonEngine::listar($partida, $body['estado'] ?? null)]);

        case 'buzon.leer':
            $partida = requirePartida($service, $body);
            $r = BuzonEngine::marcarLeido($partida, (string) ($body['mensaje_id'] ?? ''));
            if ($r['ok'] ?? false) {
                $service->guardar($partida);
            }
            jsonOut($r);

        case 'buzon.crear_dev':
            require_once $root . '/src/dev_gate.php';
            if (!aht_dev_enabled()) {
                jsonOut(['ok' => false, 'error' => 'dev_deshabilitado'], 403);
            }
            $partida = requirePartida($service, $body);
            $r = BuzonEngine::crear($partida, is_array($body['mensaje'] ?? null) ? $body['mensaje'] : [
                'texto' => '[DEV PLACEHOLDER] Mensaje de prueba',
                'de_persona' => $body['de_persona'] ?? 'per_i03',
                'tipo' => $body['tipo'] ?? 'peticion',
            ]);
            $service->guardar($partida);
            jsonOut($r);

        case 'diario.listar':
            $partida = requirePartida($service, array_merge($body, $_GET));
            jsonOut(['ok' => true, 'entradas' => DiarioEngine::listarPorDia($partida, isset($body['dia']) ? (int) $body['dia'] : null)]);

        case 'npc.planificar_dev':
            require_once $root . '/src/dev_gate.php';
            if (!aht_dev_enabled()) {
                jsonOut(['ok' => false, 'error' => 'dev_deshabilitado'], 403);
            }
            $partida = requirePartida($service, $body);
            $rng = RngService::fromPartida($partida);
            $r = AutonomousPlanner::planificarSlot(
                $partida,
                (string) ($body['residente_id'] ?? ''),
                (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
                (int) ($body['hora'] ?? $partida['reloj']['hora_actual']),
                $rng,
                $service->getLogger()
            );
            $service->guardar($partida);
            jsonOut($r);

        case 'economia.registrar_dev':
            require_once $root . '/src/dev_gate.php';
            if (!aht_dev_enabled()) {
                jsonOut(['ok' => false, 'error' => 'dev_deshabilitado'], 403);
            }
            $partida = requirePartida($service, $body);
            $r = EconomyLedger::registrar(
                $partida,
                (string) ($body['recurso'] ?? 'dinero'),
                (float) ($body['delta'] ?? 0),
                (string) ($body['motivo'] ?? 'dev'),
                is_array($body['meta'] ?? null) ? $body['meta'] : []
            );
            $service->guardar($partida);
            jsonOut($r);

        default:
            jsonOut(['ok' => false, 'error' => 'accion_desconocida', 'action' => $action], 400);
    }
} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => 'excepcion', 'mensaje' => $e->getMessage()], 500);
}
