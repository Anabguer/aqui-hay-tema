<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionPuebloEngine;

final class PeticionesHandler
{
    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        $estado = isset($body['estado']) ? (string) $body['estado'] : null;
        $items = [];
        foreach (PeticionEngine::listar($partida, $estado) as $p) {
            if (!empty($p['schema_b4'])) {
                $items[] = PeticionPuebloEngine::vistaItem($partida, $p);
            } else {
                $items[] = $p;
            }
        }
        return ['ok' => true, 'peticiones' => $items];
    }

    public static function crear(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = PeticionEngine::crear(
            $partida,
            (string) ($body['residente_id'] ?? ''),
            (string) ($body['tipo'] ?? 'otro'),
            is_array($body['datos'] ?? null) ? $body['datos'] : [],
            $ctx->logger
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function atender(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = PeticionEngine::atender($partida, (string) ($body['peticion_id'] ?? ''), $ctx->logger);
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function ignorar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = PeticionEngine::ignorar($partida, (string) ($body['peticion_id'] ?? ''), $ctx->logger);
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }
}
