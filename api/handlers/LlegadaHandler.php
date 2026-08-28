<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\labActiva;
use function AquiHayTema\Api\savePartida;
use function AquiHayTema\Api\withLabAudit;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\LlegadaPresentacionEngine;

final class LlegadaHandler
{
    public static function perfil(ApiContext $ctx, array $body, array $partida): array
    {
        $catalogId = (string) ($body['catalog_id'] ?? '');
        if ($catalogId === '') {
            $cand = $partida['llegadas']['candidato_activo'] ?? null;
            if (is_array($cand)) {
                $catalogId = (string) ($cand['catalog_id'] ?? '');
            }
        }
        if ($catalogId === '') {
            return ['ok' => false, 'error' => 'catalog_id_requerido'];
        }
        return LlegadaPresentacionEngine::perfilCandidato($ctx->root, $catalogId);
    }

    public static function acompanantes(ApiContext $ctx, array $body, array $partida): array
    {
        $abs = isset($body['llega_minutos_abs']) ? (int) $body['llega_minutos_abs'] : null;
        return [
            'ok' => true,
            'acompanantes' => LlegadaPresentacionEngine::acompanantesDisponibles(
                $partida,
                $ctx->root,
                $abs
            ),
        ];
    }

    public static function estado(ApiContext $ctx, array $body, array $partida): array
    {
        CandidatoLlegadaEngine::ensure($partida);
        return [
            'ok' => true,
            'llegadas' => $partida['llegadas'],
            'huecos' => \AquiHayTema\Engine\CapacidadViviendas::huecos($partida),
            'capacidad' => \AquiHayTema\Engine\CapacidadViviendas::capacidadTotal($partida),
            'modo_normal' => CandidatoLlegadaEngine::modoNormalActivo($partida),
        ];
    }

    public static function aceptar(ApiContext $ctx, array $body, array &$partida): array
    {
        $acompanante = isset($body['acompanante_id']) ? (string) $body['acompanante_id'] : null;
        if ($acompanante === null && isset($body['personaje_id'])) {
            $acompanante = (string) $body['personaje_id'];
        }
        $r = CandidatoLlegadaEngine::aceptar(
            $partida,
            $ctx->root,
            isset($body['mensaje_id']) ? (string) $body['mensaje_id'] : null,
            $ctx->logger,
            $acompanante
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
            if (labActiva($body) && is_array($r['en_camino'] ?? null)) {
                LabAudit::eventoLlegadaEnCamino($partida, $r['en_camino']);
            }
        }
        return withLabAudit($r);
    }

    public static function rechazar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = CandidatoLlegadaEngine::rechazar(
            $partida,
            $ctx->root,
            isset($body['mensaje_id']) ? (string) $body['mensaje_id'] : null
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }
}
