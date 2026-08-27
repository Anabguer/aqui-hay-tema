<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\labActiva;
use function AquiHayTema\Api\requireDev;
use function AquiHayTema\Api\savePartida;
use function AquiHayTema\Api\withLabAudit;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CitaEngine;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EncuentroResultadoVista;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\ResumenDia;
use AquiHayTema\Engine\VidaPuebloEngine;

final class EncuentrosHandler
{
    public static function programar(ApiContext $ctx, array $body, array &$partida): array
    {
        $perdida = VidaPuebloEngine::rechazoSiPerdida($partida, CalibracionConfig::load($ctx->root));
        if ($perdida !== null) {
            return withLabAudit($perdida);
        }
        $r = $ctx->service->programarEncuentro(
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
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function proponer(ApiContext $ctx, array $body, array &$partida): array
    {
        $perdida = VidaPuebloEngine::rechazoSiPerdida($partida, CalibracionConfig::load($ctx->root));
        if ($perdida !== null) {
            return withLabAudit($perdida);
        }
        $lab = labActiva($body);
        $antesRes = $lab ? LabAudit::residentesActivos($partida) : [];
        $r = $ctx->service->proponerEncuentro(
            $partida,
            is_array($body['participantes'] ?? null) ? $body['participantes'] : [
                (string) ($body['residente_a'] ?? ''),
                (string) ($body['residente_b'] ?? ''),
            ],
            (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
            (int) ($body['hora'] ?? 17),
            (string) ($body['tipo'] ?? 'conocerse'),
            isset($body['lugar']) ? (string) $body['lugar'] : null,
            isset($body['peticion_id']) ? (string) $body['peticion_id'] : null
        );
        savePartida($ctx, $partida);
        if ($lab) {
            $catalog = new Catalog($ctx->root);
            LabAudit::eventosNuevosResidentes($antesRes, $partida, $catalog);
            LabAudit::eventoPlan($partida, $r, $catalog);
            $prop = is_array($r['propuesta'] ?? null) ? $r['propuesta'] : [];
            if ((string) ($prop['tipo'] ?? '') === 'individual') {
                LabAudit::eventoPlanSolo($partida, $r, $catalog);
            }
        }
        if ($r['ok'] ?? false) {
            $r['estado_delta'] = self::estadoDeltaOrganizar($ctx, $partida);
        }
        return withLabAudit($r);
    }

    /**
     * Campos de estado que la UI necesita tras organizar un plan (sin partida.refresh).
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function estadoDeltaOrganizar(ApiContext $ctx, array $partida): array
    {
        $estado = $ctx->service->estadoResumido($partida);
        return [
            'proximo_encuentro' => $estado['proximo_encuentro'] ?? null,
            'encuentro_en_curso' => $estado['encuentro_en_curso'] ?? null,
            'encuentros_en_curso' => $estado['encuentros_en_curso'] ?? [],
            'encuentros_hoy' => $estado['encuentros_hoy'] ?? [],
            'encuentros_activos' => $estado['encuentros_activos'] ?? 0,
            'encuentros_activos_label' => $estado['encuentros_activos_label'] ?? '',
            'buzon_pendientes' => $estado['buzon_pendientes'] ?? 0,
            'propuestas_pendientes' => $estado['propuestas_pendientes'] ?? 0,
        ];
    }

    public static function decidirPropuesta(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = $ctx->service->decidirPropuestaEncuentro(
            $partida,
            (string) ($body['propuesta_id'] ?? ''),
            (string) ($body['residente_id'] ?? ''),
            (bool) ($body['acepta'] ?? false)
        );
        savePartida($ctx, $partida);
        return $r;
    }

    public static function listarPropuestas(ApiContext $ctx, array $body, array $partida): array
    {
        $estado = isset($body['estado']) ? (string) $body['estado'] : null;
        $items = \AquiHayTema\Engine\PropuestaEncuentroEngine::listar($partida);
        if ($estado !== null) {
            $items = array_values(array_filter($items, static function ($p) use ($estado) {
                return ($p['estado'] ?? '') === $estado;
            }));
        }
        return ['ok' => true, 'propuestas' => $items];
    }

    public static function tiposPermitidos(ApiContext $ctx, array $body, array $partida): array
    {
        $parts = is_array($body['participantes'] ?? null) ? $body['participantes'] : [
            (string) ($body['residente_a'] ?? ''),
            (string) ($body['residente_b'] ?? ''),
        ];
        $a = (string) ($parts[0] ?? '');
        $b = (string) ($parts[1] ?? '');
        $modo = (string) ($body['modo'] ?? 'pareja');
        if ($modo === 'solo' || ($b === '' && $a !== '')) {
            return [
                'ok' => true,
                'conocidos' => false,
                'tipos' => ['individual'],
                'opciones' => [[
                    'id' => 'individual',
                    'label' => 'Por su cuenta',
                    'cupo' => '1 persona',
                ]],
                'tipo_sugerido' => 'individual',
                'causa' => '',
                'mensaje_ui' => '',
                'candidatos_a' => \AquiHayTema\Engine\OrganizarMotivo::candidatos($partida, ''),
                'candidatos_b' => [],
                'planes_organizar' => \AquiHayTema\Engine\PropuestaNivel::contratoOrganizar(),
                'hint' => 'Plan en solitario: un vecino, un lugar, una hora.',
            ];
        }
        $cal = \AquiHayTema\Engine\CalibracionConfig::load($ctx->root);
        $tipos = \AquiHayTema\Engine\PropuestaNivel::tiposPermitidos($partida, $a, $b, $cal);
        $motivo = \AquiHayTema\Engine\OrganizarMotivo::de($partida, $a, $b, '', $cal);
        $opciones = [];
        foreach ($tipos as $t) {
            $opciones[] = [
                'id' => $t,
                'label' => \AquiHayTema\Engine\PropuestaNivel::etiquetaPlay($t),
                'cupo' => \AquiHayTema\Engine\PropuestaNivel::cupoUi($t),
            ];
        }
        return [
            'ok' => true,
            'conocidos' => $a !== '' && $b !== '' && $a !== $b && \AquiHayTema\Engine\RelacionEngine::seConocen($partida, $a, $b),
            'tipos' => $tipos,
            'opciones' => $opciones,
            'tipo_sugerido' => $motivo['tipo_sugerido'],
            'causa' => $motivo['codigo'],
            'mensaje_ui' => \AquiHayTema\Engine\OrganizarMotivo::mensajeUi($motivo['codigo']),
            'candidatos_a' => \AquiHayTema\Engine\OrganizarMotivo::candidatos($partida, $b),
            'candidatos_b' => \AquiHayTema\Engine\OrganizarMotivo::candidatos($partida, $a),
            'planes_organizar' => \AquiHayTema\Engine\PropuestaNivel::contratoOrganizar(),
            'hint' => \AquiHayTema\Engine\PropuestaNivel::hintPlay($partida, $a, $b, $cal),
        ];
    }

    public static function estado(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = EncuentroEngine::cambiarEstado($partida, (string) ($body['encuentro_id'] ?? ''), (string) ($body['estado'] ?? ''));
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function cancelar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = EncuentroEngine::cancelar($partida, (string) ($body['encuentro_id'] ?? ''), $ctx->logger);
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function sincronizar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = EncuentroLifecycle::sincronizarConReloj($partida, $ctx->logger, $ctx->service->getCatalog());
        savePartida($ctx, $partida);
        return ['ok' => true, 'resultado' => $r];
    }

    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        $catalog = $ctx->service->getCatalog();
        $out = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $row = $enc;
            $row['vista'] = ($enc['estado'] ?? '') === 'terminado'
                ? EncuentroResultadoVista::de($partida, $enc, $catalog, $ctx->root)
                : ResumenDia::vistaEncuentro($partida, $enc, $catalog);
            $out[] = $row;
        }
        return ['ok' => true, 'encuentros' => $out];
    }

    public static function intervencionAcciones(ApiContext $ctx, array $body, array $partida): array
    {
        $encId = (string) ($body['encuentro_id'] ?? '');
        $enc = EncuentroIntervencion::buscar($partida, $encId);
        if ($enc === null) {
            return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['detalle' => 'encuentro_no_encontrado']);
        }
        return [
            'ok' => true,
            'vista' => EncuentroIntervencion::vistaParaPlay($partida, $enc, $ctx->service->getCatalog()),
        ];
    }

    public static function intervencionEjecutar(ApiContext $ctx, array $body, array &$partida): array
    {
        $params = [];
        if (isset($body['hobby_id'])) {
            $params['hobby_id'] = (string) $body['hobby_id'];
        }
        if (isset($body['residente_id'])) {
            $params['residente_id'] = (string) $body['residente_id'];
        }
        if (isset($body['objetivo'])) {
            $params['objetivo'] = (string) $body['objetivo'];
        }
        $r = EncuentroIntervencion::ejecutar(
            $partida,
            (string) ($body['encuentro_id'] ?? ''),
            (string) ($body['accion'] ?? ''),
            $params,
            $ctx->service->getCatalog(),
            $ctx->logger
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
            $estado = $ctx->service->estadoResumido($partida);
            $r['estado_delta'] = [
                'encuentro_en_curso' => $estado['encuentro_en_curso'] ?? null,
                'encuentros_en_curso' => $estado['encuentros_en_curso'] ?? [],
                'buzon_pendientes' => $estado['buzon_pendientes'] ?? 0,
            ];
        }
        return $r;
    }

    /** Retrocompat cita.* */
    public static function citaProgramar(ApiContext $ctx, array $body, array &$partida): array
    {
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
            savePartida($ctx, $partida);
        }
        return $r;
    }
}
