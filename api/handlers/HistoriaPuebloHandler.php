<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\savePartidaRapida;
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
     * @return array{ok: bool, ack_ok: bool, recompensa?: array, animacion_pendiente?: bool}
     */
    public static function ack(ApiContext $ctx, array $body, array &$partida): array
    {
        $hitoId = $body['hito_id'] ?? '';
        if ($hitoId === '') {
            return ['ok' => false, 'error' => 'missing_hito_id'];
        }

        $animPendienteAntes = HistoriaPuebloEngine::recompensaAnimacionPendiente($partida, $hitoId);
        $entradaAntes = HistoriaPuebloEngine::entradaPorHito($partida, $hitoId);

        HistoriaPuebloEngine::ack($partida, $hitoId);
        $consumida = HistoriaPuebloEngine::estaConsumida($partida, $hitoId);

        if ($consumida) {
            $root = $ctx->root;
            $partidaId = $partida['meta']['partida_id'] ?? '';
            if ($root && $partidaId) {
                $consumed = HistoriaPuebloEngine::loadConsumed($root, $partidaId);
                if (!in_array($hitoId, $consumed, true)) {
                    $consumed[] = $hitoId;
                    HistoriaPuebloEngine::saveConsumed($root, $partidaId, $consumed);
                }
            }
            savePartidaRapida($ctx, $partida);
        }

        $recompensa = null;
        if (is_array($entradaAntes)) {
            $recompensa = \AquiHayTema\Engine\RegalitoRecompensaService::recompensaDeEntradaHistoria($partida, $entradaAntes);
        }

        return [
            'ok' => true,
            'ack_ok' => $consumida,
            'recompensa' => $recompensa,
            'animacion_pendiente' => $consumida && $animPendienteAntes,
        ];
    }

    public static function recompensaAnimAck(ApiContext $ctx, array $body, array &$partida): array
    {
        $hitoId = $body['hito_id'] ?? '';
        if ($hitoId === '') {
            return ['ok' => false, 'error' => 'missing_hito_id'];
        }
        $mutado = HistoriaPuebloEngine::marcarRecompensaAnimada($partida, $hitoId);
        if ($mutado) {
            savePartidaRapida($ctx, $partida);
        }
        return ['ok' => true, 'marcado' => $mutado];
    }
}
