<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Engine\DiarioEngine;

final class DiarioHandler
{
    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        return ['ok' => true, 'entradas' => DiarioEngine::listarPorDia($partida, isset($body['dia']) ? (int) $body['dia'] : null)];
    }
}
