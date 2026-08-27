<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\RelacionEngine;

final class RelacionesHandler
{
    public static function social(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = RelacionEngine::upsertSocial(
            $partida,
            (string) ($body['persona_a'] ?? ''),
            (string) ($body['persona_b'] ?? ''),
            (string) ($body['tipo'] ?? 'conocidos'),
            isset($body['intensidad']) ? (int) $body['intensidad'] : null,
            isset($body['se_soportan']) ? (bool) $body['se_soportan'] : null
        );
        savePartida($ctx, $partida);
        return $r;
    }

    public static function romance(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = RelacionEngine::upsertRomance(
            $partida,
            (string) ($body['persona_a'] ?? ''),
            (string) ($body['persona_b'] ?? ''),
            is_array($body['valores'] ?? null) ? $body['valores'] : []
        );
        savePartida($ctx, $partida);
        return $r;
    }

    /**
     * Vista global de relaciones para la pestaña «Relaciones» de Vecinos del pueblo.
     * Solo lectura; reutiliza el DTO canónico de ficha (RelacionVistaJugador).
     */
    public static function vistaPueblo(ApiContext $ctx, array $body, array $partida): array
    {
        return $ctx->service->vistaRelacionesPueblo($partida);
    }

    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        return [
            'ok' => true,
            'sociales' => $partida['relaciones_sociales'] ?? [],
            'romanticas' => $partida['relaciones_romanticas'] ?? [],
            'conflicto' => $partida['relaciones_conflicto'] ?? [],
        ];
    }

    public static function fase(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = RelacionEngine::aplicarFase(
            $partida,
            (string) ($body['persona_a'] ?? ''),
            (string) ($body['persona_b'] ?? ''),
            (string) ($body['canal'] ?? 'social'),
            (string) ($body['fase'] ?? '')
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }
}
