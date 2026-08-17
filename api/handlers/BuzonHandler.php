<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\requireDev;
use AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\BuzonEngine;

final class BuzonHandler
{
    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        return ['ok' => true, 'mensajes' => BuzonEngine::listar($partida, $body['estado'] ?? null)];
    }

    public static function leer(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = BuzonEngine::marcarLeido($partida, (string) ($body['mensaje_id'] ?? ''));
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function crearDev(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = BuzonEngine::crear($partida, is_array($body['mensaje'] ?? null) ? $body['mensaje'] : [
            'texto' => '[DEV PLACEHOLDER] Mensaje de prueba',
            'de_persona' => $body['de_persona'] ?? 'per_i03',
            'tipo' => $body['tipo'] ?? 'peticion',
        ]);
        savePartida($ctx, $partida);
        return $r;
    }
}
