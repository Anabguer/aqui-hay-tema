<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Engine\AgendaEngine;

final class AgendaHandler
{
    public static function dia(ApiContext $ctx, array $body, array $partida): array
    {
        $rid = $body['residente_id'] ?? ($_GET['residente_id'] ?? null);
        if (!$rid) {
            return ['ok' => false, 'error' => 'residente_id_requerido'];
        }
        $dia = isset($body['dia']) ? (int) $body['dia'] : (int) $partida['reloj']['dia_pueblo'];
        return ['ok' => true, 'agenda' => AgendaEngine::resolverDia($partida, (string) $rid, $dia)];
    }

    public static function disponibilidad(ApiContext $ctx, array $body, array $partida): array
    {
        $rid = $body['residente_id'] ?? null;
        if (!$rid) {
            return ['ok' => false, 'error' => 'residente_id_requerido'];
        }
        return ['ok' => true, 'disponibilidad' => AgendaEngine::estaDisponible(
            $partida,
            (string) $rid,
            (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
            (int) ($body['hora'] ?? 0)
        )];
    }
}
