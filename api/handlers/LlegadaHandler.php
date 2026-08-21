<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\labActiva;
use function AquiHayTema\Api\savePartida;
use function AquiHayTema\Api\withLabAudit;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\LabAudit;

final class LlegadaHandler
{
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
        $r = CandidatoLlegadaEngine::aceptar(
            $partida,
            $ctx->root,
            isset($body['mensaje_id']) ? (string) $body['mensaje_id'] : null,
            $ctx->logger
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
