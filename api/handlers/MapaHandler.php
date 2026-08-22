<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\PresenciaEngine;use AquiHayTema\Engine\VistaPuebloV3;

final class MapaHandler
{
    public static function presencia(ApiContext $ctx, array $body, array &$partida): array
    {
        if (!$ctx->partidaCargadaSincronizada) {
            $antes = LabAudit::snapshotEstadosEncuentros($partida);
            EncuentroLifecycle::sincronizarConReloj($partida, $ctx->logger, new Catalog($ctx->root));
            if ($antes !== LabAudit::snapshotEstadosEncuentros($partida)) {
                savePartida($ctx, $partida);
            }
        }
        $mapa = PresenciaEngine::resolver($partida, $ctx->root);
        return [
            'ok' => true,
            'mapa' => $mapa,
            'pueblo' => VistaPuebloV3::de($partida, $mapa, $ctx->root),
        ];
    }
}
