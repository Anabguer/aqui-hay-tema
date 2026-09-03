<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
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
    public static function pendientes(array $partida): array
    {
        return [
            'ok' => true,
            'celebraciones' => HistoriaPuebloEngine::celebracionesPendientes($partida),
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

        return [
            'ok' => true,
            'ack_ok' => $ackOk,
        ];
    }
}
