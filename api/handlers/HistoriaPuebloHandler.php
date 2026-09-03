<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\HistoriaPuebloEngine;
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

    /**
     * Retorna celebraciones pendientes (para partida.refresh y partida.nueva).
     *
     * @return array{ok: bool, celebraciones: list<array>}
     */
    public static function pendientes(array $partida, ?ApiContext $ctx = null): array
    {
        $root = $ctx ? $ctx->root : null;
        $partidaId = $partida['meta']['partida_id'] ?? null;
        return [
            'ok' => true,
            'celebraciones' => HistoriaPuebloEngine::celebracionesPendientes($partida, $root, $partidaId),
        ];
    }

    /**
     * ACK de celebración — marca como consumida (idempotente).
     *
     * @return array{ok: bool, ack_ok: bool}
     */
    public static function ack(ApiContext $ctx, array $body, array &$partida): array
    {
        $hitoId = $body['hito_id'] ?? '';
        if ($hitoId === '') {
            return ['ok' => false, 'error' => 'missing_hito_id'];
        }

        $ackOk = HistoriaPuebloEngine::ack($partida, $hitoId);

        if ($ackOk) {
            savePartida($ctx, $partida);
            // Also persist to separate consumed list (survives cargar() race condition)
            $root = $ctx->root;
            $partidaId = $partida['meta']['partida_id'] ?? '';
            if ($root && $partidaId) {
                $consumed = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
                $consumed[] = $hitoId;
                HistoriaPuebloEngine::saveConsumed($root, $partidaId, $consumed);
            }
        }

        return [
            'ok' => true,
            'ack_ok' => $ackOk,
        ];
    }
}
