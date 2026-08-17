<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Engine\PresenciaEngine;

final class MapaHandler
{
    public static function presencia(ApiContext $ctx, array $body, array $partida): array
    {
        return ['ok' => true, 'mapa' => PresenciaEngine::resolver($partida, $ctx->root)];
    }
}
