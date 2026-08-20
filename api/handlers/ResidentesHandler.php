<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\savePartida;

final class ResidentesHandler
{
    public static function ficha(ApiContext $ctx, array $body, array &$partida): array
    {
        $rid = $body['residente_id'] ?? ($_GET['residente_id'] ?? null);
        if (!$rid) {
            return ['ok' => false, 'error' => 'residente_id_requerido'];
        }
        $ficha = $ctx->service->fichaResidente($partida, (string) $rid);
        $antes = json_encode($partida['tutorial'] ?? null);
        $tut = \AquiHayTema\Engine\TutorialBucle::registrar($partida, \AquiHayTema\Engine\TutorialBucle::HECHO_VECINO);
        if ($antes !== json_encode($partida['tutorial'] ?? null)) {
            savePartida($ctx, $partida);
        }
        return ['ok' => true, 'ficha' => $ficha, 'tutorial' => $tut];
    }

    public static function placeholder(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = $ctx->service->crearResidentePlaceholderDev($partida);
        savePartida($ctx, $partida);
        return ['ok' => true, 'resultado' => $r];
    }

    public static function incorporar(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = $ctx->service->incorporarResidenteCatalogo($partida, (string) ($body['catalog_id'] ?? ''));
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return ['ok' => $r['ok'], 'resultado' => $r];
    }

    public static function liberarVivienda(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = $ctx->service->liberarVivienda($partida, (string) ($body['vivienda_id'] ?? ''));
        savePartida($ctx, $partida);
        return ['ok' => true, 'resultado' => $r];
    }

    public static function vivienda(ApiContext $ctx, array $body, array $partida): array
    {
        return ['ok' => true, 'bloque_a' => \AquiHayTema\Engine\BloqueA::resumen($partida)];
    }
}
