<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\labActiva;
use function AquiHayTema\Api\savePartida;
use function AquiHayTema\Api\withLabAudit;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\PlayLab;

final class DebugLabHandler
{
    private static function requireLab(array $body): ?array
    {
        if (!labActiva($body)) {
            return ['ok' => false, 'error' => 'debug_no_activo', 'mensaje_ui' => 'Activa DEBUG primero.'];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function resumen(ApiContext $ctx, array $body, array &$partida): array
    {
        if ($deny = self::requireLab($body)) {
            return $deny;
        }
        $catalog = new Catalog($ctx->root);
        return withLabAudit([
            'ok' => true,
            'lab' => PlayLab::resumenPueblo($partida, $catalog, $ctx->root),
        ]);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function vecino(ApiContext $ctx, array $body, array &$partida): array
    {
        if ($deny = self::requireLab($body)) {
            return $deny;
        }
        $id = (string) ($body['residente_id'] ?? $body['id'] ?? '');
        $catalog = new Catalog($ctx->root);
        $r = PlayLab::inspectorVecino($partida, $id, $catalog, $ctx->root);
        return withLabAudit($r);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function par(ApiContext $ctx, array $body, array &$partida): array
    {
        if ($deny = self::requireLab($body)) {
            return $deny;
        }
        $a = (string) ($body['a'] ?? $body['persona_a'] ?? '');
        $b = (string) ($body['b'] ?? $body['persona_b'] ?? '');
        $catalog = new Catalog($ctx->root);
        $r = PlayLab::inspectorPar($partida, $a, $b, $catalog);
        return withLabAudit($r);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function simular(ApiContext $ctx, array $body, array &$partida): array
    {
        if ($deny = self::requireLab($body)) {
            return $deny;
        }
        $dias = (int) ($body['dias'] ?? $body['days'] ?? 0);
        if ($dias <= 0 && isset($body['horas'])) {
            $dias = max(1, (int) ceil(((int) $body['horas']) / 24));
        }
        $catalog = new Catalog($ctx->root);
        $r = PlayLab::simularPeriodo($ctx->service, $partida, $dias, $catalog, $ctx->root);
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return withLabAudit($r);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function exportPeriodo(ApiContext $ctx, array $body, array $partida): array
    {
        if ($deny = self::requireLab($body)) {
            return $deny;
        }
        $export = $body['export'] ?? null;
        if (!is_array($export)) {
            return ['ok' => false, 'error' => 'export_requerido'];
        }
        return withLabAudit(['ok' => true, 'export' => $export]);
    }
}
