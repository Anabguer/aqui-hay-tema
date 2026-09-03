<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Engine\HistoriaPuebloVista;

final class HistoriaPuebloHandler
{
    public static function snapshot(ApiContext $ctx, array $body, array $partida): array
    {
        return [
            'ok' => true,
            'historia' => HistoriaPuebloVista::snapshot($partida),
        ];
    }
}
