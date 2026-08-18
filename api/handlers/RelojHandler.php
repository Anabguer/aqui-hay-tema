<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\RelojDev;

final class RelojHandler
{
    public static function avanzar(ApiContext $ctx, array $body, array &$partida): array
    {
        $result = $ctx->service->avanzarReloj($partida, (int) ($body['horas'] ?? 1));
        savePartida($ctx, $partida);
        return ['ok' => true, 'reloj' => $result];
    }

    public static function irA(ApiContext $ctx, array $body, array &$partida): array
    {
        \AquiHayTema\Api\requireDev();
        $r = RelojDev::irA(
            $partida,
            (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
            (int) ($body['hora'] ?? $partida['reloj']['hora_actual']),
            (bool) ($body['permitir_rewind'] ?? false),
            $ctx->logger,
            $ctx->service->emociones(),
            $ctx->root
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }
}
