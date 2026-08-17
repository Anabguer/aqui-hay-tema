<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CitaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;

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
            $nueva = $service->reiniciar($partida['meta']['partida_id']);
            jsonOut(['ok' => true, 'partida_id' => $nueva['meta']['partida_id'], 'estado' => $service->estadoResumido($nueva)]);

        case 'partida.inspeccionar':
            $partida = requirePartida($service, array_merge($body, $_GET));
            jsonOut(['ok' => true, 'partida' => $partida]);

        case 'reloj.avanzar':
            $partida = requirePartida($service, $body);
            $horas = (int) ($body['horas'] ?? 1);
            $result = $service->avanzarReloj($partida, $horas);
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
            $catalogId = $body['catalog_id'] ?? null;
            if (!$catalogId) {
                jsonOut(['ok' => false, 'error' => 'catalog_id_requerido'], 400);
            }
            $r = $service->incorporarResidenteCatalogo($partida, (string) $catalogId);
            $service->guardar($partida);
            jsonOut(['ok' => $r['ok'], 'resultado' => $r]);

        case 'vivienda.liberar':
            $partida = requirePartida($service, $body);
            $vid = $body['vivienda_id'] ?? null;
            if (!$vid) {
                jsonOut(['ok' => false, 'error' => 'vivienda_id_requerido'], 400);
            }
            $r = $service->liberarVivienda($partida, (string) $vid);
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
            $dia = (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']);
            $hora = (int) ($body['hora'] ?? 0);
            if (!$rid) {
                jsonOut(['ok' => false, 'error' => 'residente_id_requerido'], 400);
            }
            jsonOut(['ok' => true, 'disponibilidad' => AgendaEngine::estaDisponible($partida, (string) $rid, $dia, $hora)]);

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
            $partida = requirePartida($service, $body);
            $r = CitaEngine::cambiarEstado($partida, (string) ($body['cita_id'] ?? ''), (string) ($body['estado'] ?? ''));
            if ($r['ok'] ?? false) {
                $service->guardar($partida);
            }
            jsonOut($r);

        case 'cita.cancelar':
            $partida = requirePartida($service, $body);
            $r = CitaEngine::cancelar($partida, (string) ($body['cita_id'] ?? ''));
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

        default:
            jsonOut([
                'ok' => false,
                'error' => 'accion_desconocida',
                'acciones' => [
                    'partida.nueva', 'partida.listar', 'partida.estado', 'partida.guardar',
                    'partida.cargar', 'partida.reiniciar', 'partida.inspeccionar',
                    'reloj.avanzar', 'residente.ficha', 'residente.placeholder',
                    'residente.incorporar', 'vivienda.liberar', 'agenda.dia',
                    'agenda.disponibilidad', 'cita.programar', 'cita.estado',
                    'cita.cancelar', 'relacion.social', 'relacion.romance',
                ],
            ], 400);
    }
} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => 'excepcion', 'mensaje' => $e->getMessage()], 500);
}
