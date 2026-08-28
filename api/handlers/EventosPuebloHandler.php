<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\EventosPuebloEngine;
use AquiHayTema\Engine\VidaPuebloEngine;

final class EventosPuebloHandler
{
    public static function elegibles(ApiContext $ctx, array $body, array $partida): array
    {
        $cal = CalibracionConfig::load($ctx->root);
        if (!EventosPuebloEngine::activa($partida, $cal)) {
            return ['ok' => false, 'error' => 'eventos_pueblo_inactivo'];
        }
        $eventoId = (string) ($body['evento_pueblo_id'] ?? $body['evento_id'] ?? '');

        return EventosPuebloEngine::vecinosElegibles($partida, $eventoId, $cal, $ctx->service->getCatalog());
    }

    public static function confirmarAsistentes(ApiContext $ctx, array $body, array &$partida): array
    {
        return self::apuntar($ctx, $body, $partida);
    }

    /** @alias confirmarAsistentes — contrato canónico de selección por Celestine */
    public static function apuntar(ApiContext $ctx, array $body, array &$partida): array
    {
        $perdida = VidaPuebloEngine::rechazoSiPerdida($partida, CalibracionConfig::load($ctx->root));
        if ($perdida !== null) {
            return $perdida;
        }
        $cal = CalibracionConfig::load($ctx->root);
        if (!EventosPuebloEngine::activa($partida, $cal)) {
            return ['ok' => false, 'error' => 'eventos_pueblo_inactivo'];
        }
        $eventoId = (string) ($body['evento_pueblo_id'] ?? $body['evento_id'] ?? '');
        $participantes = is_array($body['participantes'] ?? null) ? $body['participantes'] : [];
        $r = EventosPuebloEngine::confirmarAsistentes(
            $partida,
            $eventoId,
            $participantes,
            $cal,
            $ctx->service->getCatalog(),
            $ctx->logger
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
            $ev = EventosPuebloEngine::buscarProgramadoPorId($partida, $eventoId);
            if ($ev !== null) {
                $r['evento_pueblo'] = EventosPuebloEngine::vistaApuntar($partida, $ev, $ctx->service->getCatalog());
            }
            $prox = EventosPuebloEngine::vistaProximoEvento($partida, $ctx->service->getCatalog());
            if ($prox !== null) {
                $r['proximo_evento_pueblo'] = $prox;
            }
            if (!isset($r['mensaje_ui'])) {
                $n = count($participantes);
                $r['mensaje_ui'] = $n === 1
                    ? '1 vecino apuntado al evento del pueblo.'
                    : ($n . ' vecinos apuntados al evento del pueblo.');
            }
        }

        return $r;
    }
}
