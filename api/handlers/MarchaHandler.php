<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\requireDev;
use function AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\MarchaEngine;

final class MarchaHandler
{
    public static function dejar(ApiContext $ctx, array $body, array &$partida): array
    {
        $mid = isset($body['mensaje_id']) ? (string) $body['mensaje_id'] : null;
        $r = MarchaEngine::dejarIr($partida, $ctx->root, $mid, $ctx->logger);
        if ($r['ok'] ?? false) {
            if ($mid !== null && $mid !== '') {
                BuzonEngine::resolverDecision($partida, $mid);
            }
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function quedarse(ApiContext $ctx, array $body, array &$partida): array
    {
        $mid = isset($body['mensaje_id']) ? (string) $body['mensaje_id'] : null;
        $r = MarchaEngine::pedirQuedarse($partida, $ctx->root, $mid, $ctx->logger);
        if ($r['ok'] ?? false) {
            if ($mid !== null && $mid !== '') {
                BuzonEngine::resolverDecision($partida, $mid);
            }
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function forzarDev(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $rid = (string) ($body['residente_id'] ?? '');
        $causa = (string) ($body['causa'] ?? MarchaEngine::CAUSA_AISLAMIENTO);
        if ($rid === '') {
            return ['ok' => false, 'error' => 'residente_id_requerido'];
        }
        $int = MarchaEngine::forzarIntencionDev($partida, $rid, $causa);
        savePartida($ctx, $partida);
        return ['ok' => true, 'intencion' => $int];
    }
}
