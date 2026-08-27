<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\VistaCotilleoV3;
use function AquiHayTema\Api\savePartidaRapida;

final class DiarioHandler
{
    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        return [
            'ok' => true,
            'entradas' => DiarioEngine::listarPorDia($partida, isset($body['dia']) ? (int) $body['dia'] : null),
            'cotilleo' => VistaCotilleoV3::de($partida),
        ];
    }

    public static function cotilleoVisto(ApiContext $ctx, array $body, array &$partida): array
    {
        $ids = [];
        foreach ((array) ($body['ids'] ?? []) as $id) {
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
        $r = VistaCotilleoV3::marcarVistas($partida, $ids);
        savePartidaRapida($ctx, $partida);
        return $r;
    }
}
