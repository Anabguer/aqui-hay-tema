<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\requireDev;
use function AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\AutonomousPlanner;
use AquiHayTema\Engine\DebugResumenPartida;
use AquiHayTema\Engine\DevCalendarService;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\DiversityAnalyzer;
use AquiHayTema\Engine\EconomyLedger;
use AquiHayTema\Engine\EventInspector;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimulationRunner;
use AquiHayTema\Engine\StressTestRunner;
use AquiHayTema\Engine\CoincidenciasEngine;
use AquiHayTema\Engine\DiscoveryProjection;
use AquiHayTema\Engine\DiscoveryVisibilityPolicy;
use AquiHayTema\Engine\HobbyEmocionDev;
use AquiHayTema\Engine\ResidenteRuntime;
use AquiHayTema\Engine\ContentValidationException;

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

    public static function npcCoincidenciasAhora(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $dia = (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($body['hora'] ?? $partida['reloj']['hora_actual'] ?? 0);
        $new = CoincidenciasEngine::detectarYRegistrar($partida, $ctx->root, $dia, $hora, $ctx->logger);
        savePartida($ctx, $partida);
        return ['ok' => true, 'dia' => $dia, 'hora' => $hora, 'nuevas' => $new];
    }

    public static function npcCoincidenciasHistorico(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        $limit = isset($body['limit']) ? (int) $body['limit'] : 50;
        $list = $partida['historial_coincidencias'] ?? [];
        $list = is_array($list) ? $list : [];
        return ['ok' => true, 'limit' => $limit, 'items' => array_values(array_slice($list, -max(1, $limit)))];
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

    public static function calendario(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        $dia = isset($body['dia']) ? (int) $body['dia'] : (int) $partida['reloj']['dia_pueblo'];
        return DevCalendarService::vistaDia($partida, $dia, $ctx->service->getCatalog());
    }

    public static function eventos(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        return EventInspector::timeline($partida, is_array($body['filtros'] ?? null) ? $body['filtros'] : $body);
    }

    public static function diagnosticoExport(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        return DiagnosticExport::export($partida, $ctx->root);
    }

    public static function simular(ApiContext $ctx, array $body): array
    {
        requireDev();
        return SimulationRunner::run(
            $ctx->root,
            (int) ($body['days'] ?? 30),
            isset($body['seed']) ? (string) $body['seed'] : null,
            (string) ($body['config_id'] ?? 'test_fixtures_v0')
        );
    }

    public static function catalogos(ApiContext $ctx, array $body): array
    {
        requireDev();
        $store = new CatalogStore($ctx->root);
        $tipo = (string) ($body['tipo'] ?? 'hobbies');
        return [
            'ok' => true,
            'tipo' => $tipo,
            'ids' => $store->ids($tipo),
            'items' => $store->items($tipo === 'hobbies' ? 'hobbies' : $tipo),
        ];
    }

    public static function diversidad(ApiContext $ctx, array $body): array
    {
        requireDev();
        $store = new CatalogStore($ctx->root);
        $umbral = isset($body['umbral']) ? (float) $body['umbral'] : 0.55;
        return DiversityAnalyzer::desdeDirectorio($ctx->root . '/data/personajes', $store, $umbral);
    }

    public static function visualPaquetes(ApiContext $ctx, array $body): array
    {
        requireDev();
        $catalog = new CatalogStore($ctx->root);
        return [
            'ok' => true,
            'expression_ids' => \AquiHayTema\Engine\ExpresionVisual::ids($catalog),
            'estados_emocionales' => $catalog->ids('estados_emocionales'),
            'packs' => $ctx->service->visualPacks()->listarResumenes($catalog),
            'nota' => 'El motor consume assets. No los genera. N variable por pack. Fallback = neutral.',
        ];
    }

    public static function visualPreview(ApiContext $ctx, array $body): array
    {
        requireDev();
        $packId = (string) ($body['pack_id'] ?? '');
        $expressionId = (string) ($body['expression_id'] ?? \AquiHayTema\Engine\ExpresionVisual::NEUTRAL);
        $store = $ctx->service->visualPacks();
        $catalog = new CatalogStore($ctx->root);
        $pack = $store->pack($packId);
        if ($pack === null) {
            return ['ok' => false, 'error' => 'pack_no_encontrado'];
        }
        $resolved = \AquiHayTema\Engine\ExpressionResolver::resolver([
            'expresion_solicitada' => $expressionId,
            'pack' => $pack,
            'pack_id' => $packId,
        ], $store, $catalog);
        return [
            'ok' => true,
            'pack' => $store->resumenPack($packId, $catalog),
            'solicitada' => $expressionId,
            'expression_id' => $resolved['expression_id'],
            'fallback' => $resolved['fallback'],
            'motivo' => $resolved['motivo'],
            'asset' => $resolved['asset'],
            'existe' => !empty($resolved['asset']['existe']),
        ];
    }

    public static function visualInventario(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        return $ctx->service->emociones()->inventarioResidente(
            $partida,
            (string) ($body['residente_id'] ?? '')
        );
    }

    public static function estadoEmocionalForzar(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $hasta = null;
        if (isset($body['hasta_dia'], $body['hasta_hora'])) {
            $hasta = ['dia' => (int) $body['hasta_dia'], 'hora' => (int) $body['hasta_hora']];
        }
        $duracion = isset($body['duracion_horas']) ? (int) $body['duracion_horas'] : null;
        $r = $ctx->service->emociones()->aplicar(
            $partida,
            (string) ($body['residente_id'] ?? ''),
            (string) ($body['estado_id'] ?? 'neutro'),
            'dev_manual',
            isset($body['intensidad']) ? (float) $body['intensidad'] : null,
            $hasta,
            is_array($body['contexto'] ?? null) ? $body['contexto'] : [],
            $duracion
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function expresionForzar(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $expr = $body['expression_id'] ?? null;
        $expr = ($expr === '' || $expr === null) ? null : (string) $expr;
        $r = $ctx->service->emociones()->overrideExpresionDev(
            $partida,
            (string) ($body['residente_id'] ?? ''),
            $expr
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function visualVincular(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $rid = (string) ($body['residente_id'] ?? '');
        $packId = (string) ($body['pack_id'] ?? '');
        if (!isset($partida['residentes'][$rid])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }
        if ($packId !== '' && $ctx->service->visualPacks()->pack($packId) === null) {
            return ['ok' => false, 'error' => 'pack_no_encontrado'];
        }
        $partida['residentes'][$rid]['runtime']['visual_pack_id'] = $packId !== '' ? $packId : null;
        $resolved = $ctx->service->emociones()->resolverResidente($partida, $partida['residentes'][$rid]);
        $partida['residentes'][$rid]['runtime']['expresion_visual']['id'] = $resolved['expression_id'];
        $partida['residentes'][$rid]['runtime']['expresion_visual']['motivo'] = $resolved['motivo'];
        savePartida($ctx, $partida);
        return [
            'ok' => true,
            'visual_pack_id' => $partida['residentes'][$rid]['runtime']['visual_pack_id'],
            'expresion' => $resolved,
            'sin_evento_de_juego' => true,
        ];
    }

    /**
     * DEV LAB: inspeccionar visibilidad de un campo de una ficha.
     * No asigna secretos; no toca Rocío ni fichas piloto.
     */
    public static function discoveryCampo(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        $rid = (string) ($body['residente_id'] ?? '');
        $campo = (string) ($body['campo'] ?? '');
        if ($rid === '' || $campo === '') {
            return ['ok' => false, 'error' => 'residente_id y campo requeridos'];
        }
        if (!isset($partida['residentes'][$rid])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }

        $runtime = $partida['residentes'][$rid];
        $catalogo = null;
        try {
            $catalogo = ResidenteRuntime::catalogoParaRuntime($runtime, $ctx->service->getCatalog());
        } catch (ContentValidationException $e) {
        }

        $campos = [];
        if ($catalogo !== null) {
            $campos = DiscoveryProjection::deCatalogo($catalogo, $runtime);
        }
        $valorReal = $campos[$campo] ?? ($body['valor_real'] ?? null);

        $config = DiscoveryVisibilityPolicy::load($ctx->root);
        $proyeccion = DiscoveryProjection::proyectar(
            $partida,
            $rid,
            [$campo => $valorReal],
            $config,
            is_array($body['eventos_alcanzados'] ?? null) ? array_values($body['eventos_alcanzados']) : []
        );

        return [
            'ok' => true,
            'residente_id' => $rid,
            'campo' => $campo,
            'politicas_disponibles' => DiscoveryVisibilityPolicy::politicasDisponibles(),
            'default_config' => $config['default'] ?? 'sin_politica',
            'por_categoria_config' => $config['por_categoria'] ?? [],
            'proyeccion' => $proyeccion[$campo] ?? null,
            '_nota' => 'Sin politicas asignadas a personajes. Cambiar por_categoria en data/configs/discovery_visibility.json.',
        ];
    }

    public static function eventosCorrelacionados(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        $cid = (string) ($body['correlacion_id'] ?? '');
        if ($cid === '') {
            return ['ok' => false, 'error' => 'correlacion_id requerida'];
        }
        return EventInspector::correlacionados($partida, $cid, (int) ($body['limit'] ?? 100));
    }

    public static function hobbyEmocionDiagnostico(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        $rid = (string) ($body['residente_id'] ?? '');
        if ($rid === '') {
            return ['ok' => false, 'error' => 'residente_id requerido'];
        }
        $lugar = isset($body['lugar_id']) ? (string) $body['lugar_id'] : null;
        return HobbyEmocionDev::diagnostico($partida, $rid, $lugar, $ctx->service->getCatalog());
    }

    public static function resumenPartida(ApiContext $ctx, array $body, array $partida): array
    {
        requireDev();
        $limite = isset($body['limite']) ? (int) $body['limite'] : 20;
        return DebugResumenPartida::resumen($partida, $limite);
    }
}
