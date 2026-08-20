<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\VistaPuebloV3;

final class MapaHandler
{
    public static function presencia(ApiContext $ctx, array $body, array $partida): array
    {
        $mapa = PresenciaEngine::resolver($partida, $ctx->root);
        return [
            'ok' => true,
            'mapa' => $mapa,
            'pueblo' => VistaPuebloV3::de($partida, $mapa, $ctx->root),
        ];
    }
}
