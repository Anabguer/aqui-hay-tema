<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\CitaEngine;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EncuentroResultadoVista;
use AquiHayTema\Engine\ResumenDia;

final class EncuentrosHandler
{
    public static function programar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = $ctx->service->programarEncuentro(
            $partida,
            is_array($body['participantes'] ?? null) ? $body['participantes'] : [
                (string) ($body['residente_a'] ?? ''),
                (string) ($body['residente_b'] ?? ''),
            ],
            (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
            (int) ($body['hora'] ?? 17),
            (string) ($body['tipo'] ?? 'conocerse'),
            isset($body['lugar']) ? (string) $body['lugar'] : null
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function estado(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = EncuentroEngine::cambiarEstado($partida, (string) ($body['encuentro_id'] ?? ''), (string) ($body['estado'] ?? ''));
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function cancelar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = EncuentroEngine::cancelar($partida, (string) ($body['encuentro_id'] ?? ''), $ctx->logger);
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function sincronizar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = EncuentroLifecycle::sincronizarConReloj($partida, $ctx->logger);
        savePartida($ctx, $partida);
        return ['ok' => true, 'resultado' => $r];
    }

    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        $catalog = $ctx->service->getCatalog();
        $out = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $row = $enc;
            $row['vista'] = ($enc['estado'] ?? '') === 'terminado'
                ? EncuentroResultadoVista::de($partida, $enc, $catalog, $ctx->root)
                : ResumenDia::vistaEncuentro($partida, $enc, $catalog);
            $out[] = $row;
        }
        return ['ok' => true, 'encuentros' => $out];
    }

    /** Retrocompat cita.* */
    public static function citaProgramar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = CitaEngine::programar(
            $partida,
            (string) ($body['residente_a'] ?? ''),
            (string) ($body['residente_b'] ?? ''),
            (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
            (int) ($body['hora'] ?? 17),
            isset($body['lugar']) ? (string) $body['lugar'] : null,
            isset($body['actividad']) ? (string) $body['actividad'] : null
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }
}
