<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\requireDev;
use AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\AutonomousPlanner;
use AquiHayTema\Engine\EconomyLedger;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\StressTestRunner;

final class DevHandler
{
    public static function snapshotGuardar(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        return $ctx->snapshots->guardar($partida, (string) ($body['nombre'] ?? 'snapshot'));
    }

    public static function snapshotRestaurar(ApiContext $ctx, array $body): array
    {
        requireDev();
        $id = (string) ($body['partida_id'] ?? '');
        $r = $ctx->snapshots->restaurar($id, (string) ($body['nombre'] ?? ''));
        if ($r['ok'] ?? false) {
            savePartida($ctx, $r['partida']);
        }
        return $r;
    }

    public static function snapshotListar(ApiContext $ctx, array $body): array
    {
        requireDev();
        return ['ok' => true, 'snapshots' => $ctx->snapshots->listar((string) ($body['partida_id'] ?? ''))];
    }

    public static function resetEncuentros(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = $ctx->dev->resetEncuentros($partida);
        savePartida($ctx, $partida);
        return $r;
    }

    public static function resetRelaciones(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = $ctx->dev->resetRelaciones($partida);
        savePartida($ctx, $partida);
        return $r;
    }

    public static function resetBuzonDiario(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = $ctx->dev->resetBuzonDiario($partida);
        savePartida($ctx, $partida);
        return $r;
    }

    public static function eliminarPartida(ApiContext $ctx, array $body): array
    {
        requireDev();
        $id = (string) ($body['partida_id'] ?? '');
        return ['ok' => $ctx->repo->eliminar($id)];
    }

    public static function eliminarPlaceholder(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = $ctx->dev->eliminarPlaceholder($partida, (string) ($body['residente_id'] ?? ''));
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function forzarResolver(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = $ctx->dev->forzarResolverEncuentro($partida, (string) ($body['encuentro_id'] ?? ''), $ctx->logger);
        savePartida($ctx, $partida);
        return $r;
    }

    public static function inspeccionarRng(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        return ['ok' => true, 'rng' => $partida['rng'] ?? null, 'seed' => $partida['meta']['seed'] ?? null];
    }

    public static function inspeccionarAudit(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        return [
            'ok' => true,
            'event_log' => array_slice($partida['event_log'] ?? [], -50),
            'audit_trail' => array_slice($partida['audit_trail'] ?? [], -50),
        ];
    }

    public static function npcPlanificar(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $rng = RngService::fromPartida($partida);
        $r = AutonomousPlanner::planificarSlot(
            $partida,
            (string) ($body['residente_id'] ?? ''),
            (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
            (int) ($body['hora'] ?? $partida['reloj']['hora_actual']),
            $rng,
            $ctx->logger
        );
        savePartida($ctx, $partida);
        return $r;
    }

    public static function economiaRegistrar(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = EconomyLedger::registrar(
            $partida,
            (string) ($body['recurso'] ?? 'dinero'),
            (float) ($body['delta'] ?? 0),
            (string) ($body['motivo'] ?? 'dev'),
            is_array($body['meta'] ?? null) ? $body['meta'] : []
        );
        savePartida($ctx, $partida);
        return $r;
    }

    public static function stress100(ApiContext $ctx, array $body): array
    {
        requireDev();
        return StressTestRunner::run($ctx->root, (int) ($body['count'] ?? 100));
    }
}
