<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\labActiva;
use function AquiHayTema\Api\savePartida;
use function AquiHayTema\Api\withLabAudit;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ContentValidationException;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\DebugParejasEngine;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\PartidaValidator;
use AquiHayTema\Engine\RetratoResolver;
use AquiHayTema\Engine\TutorialPrimerosPasos;

final class PartidaHandler
{
    public static function nueva(ApiContext $ctx, array $body): array
    {
        try {
            $horaLocal = isset($body['hora_local']) && is_array($body['hora_local']) ? $body['hora_local'] : null;
            $partida = $ctx->service->nuevaPartida(
                $body['config_id'] ?? 'debug_v0',
                isset($body['seed']) ? (string) $body['seed'] : null,
                $horaLocal
            );
        } catch (ContentValidationException $e) {
            return [
                'ok' => false,
                'error' => 'content_validation_failed',
                'errores' => $e->errores,
                'mensaje_ui' => 'El catálogo o una ficha no es válida.',
            ];
        }
        FeatureConfig::mergeIntoPartida($partida, $ctx->root);
        LabAudit::reset();
        if (labActiva($body)) {
            LabAudit::eventoNuevaPartida($partida, new Catalog($ctx->root));
            LabAudit::eventoTutorial($partida, 'NUEVA_PARTIDA', new Catalog($ctx->root));
        }
        return withLabAudit(['ok' => true, 'partida' => $ctx->service->estadoResumido($partida), 'partida_id' => $partida['meta']['partida_id'], 'historia' => HistoriaPuebloHandler::pendientes($partida)]);
    }

    public static function listar(ApiContext $ctx, array $body): array
    {
        return ['ok' => true, 'partidas' => $ctx->service->listarPartidas()];
    }

    public static function estado(ApiContext $ctx, array $body, array &$partida): array
    {
        if (!$ctx->partidaCargadaSincronizada) {
            self::sincronizarEncuentrosSiToca($ctx, $partida);
        }
        LabAudit::reset();
        if (labActiva($body)) {
            LabAudit::eventoSnapshotCargada($partida, new Catalog($ctx->root));
        }
        return withLabAudit(['ok' => true, 'estado' => $ctx->service->estadoResumido($partida)]);
    }

    public static function guardar(ApiContext $ctx, array $body, array $partida): array
    {
        savePartida($ctx, $partida);
        LabAudit::reset();
        if (labActiva($body)) {
            LabAudit::push('PARTIDA', '[AHT DEBUG PARTIDA]', [
                'evento' => 'GUARDAR',
                'partida_id' => $partida['meta']['partida_id'] ?? null,
                'reloj' => $partida['reloj'] ?? null,
            ]);
        }
        return withLabAudit(['ok' => true, 'guardado' => true]);
    }

    public static function cargar(ApiContext $ctx, array $body): array
    {
        $id = $body['partida_id'] ?? null;
        if (!$id) {
            return ['ok' => false, 'error' => 'partida_id_requerido'];
        }
        $partida = $ctx->service->cargar((string) $id);
        LabAudit::reset();
        if (labActiva($body)) {
            LabAudit::eventoSnapshotCargada($partida, new Catalog($ctx->root));
        }
        return withLabAudit(['ok' => true, 'partida_id' => $id, 'estado' => $ctx->service->estadoResumido($partida)]);
    }

    public static function reiniciar(ApiContext $ctx, array $body, array $partida): array
    {
        $id = $partida['meta']['partida_id'];
        $nueva = $ctx->service->reiniciarPartida($id, $body['config_id'] ?? 'debug_v0', $body['seed'] ?? null);
        LabAudit::reset();
        if (labActiva($body)) {
            LabAudit::eventoNuevaPartida($nueva, new Catalog($ctx->root));
        }
        return withLabAudit([
            'ok' => true,
            'partida_id' => $id,
            'nota' => 'Reiniciar conserva partida_id; partida.nueva crea id nuevo',
            'estado' => $ctx->service->estadoResumido($nueva),
        ]);
    }

    public static function inspeccionar(ApiContext $ctx, array $body, array &$partida): array
    {
        if (!$ctx->partidaCargadaSincronizada) {
            self::sincronizarEncuentrosSiToca($ctx, $partida);
        }
        LabAudit::reset();
        if (labActiva($body)) {
            $catalog = new Catalog($ctx->root);
            LabAudit::push('REL', '[AHT DEBUG REL]', [
                'evento' => 'MATRIZ_COMPLETA',
                'matriz' => LabAudit::matrizRelacionalPublica($partida, $catalog),
            ]);
        }
        return withLabAudit(['ok' => true, 'partida' => self::enriquecerRetratos($partida, $ctx->root)]);
    }

    /**
     * Carga una vez la partida y compone todos los datos del refresh.
     *
     * @param array<string, mixed> $partida
     */
    public static function refrescar(ApiContext $ctx, array $body, array &$partida): array
    {
        if (!$ctx->partidaCargadaSincronizada) {
            self::sincronizarEncuentrosSiToca($ctx, $partida);
        }

        if (($partida['tutorial']['id'] ?? '') === TutorialPrimerosPasos::ID
            && empty($partida['tutorial']['jugable_completado'])) {
            TutorialPrimerosPasos::asegurarMisiones($partida, new Catalog($ctx->root));
        }

        $labOn = labActiva($body);
        LabAudit::reset();
        if ($labOn) {
            LabAudit::eventoSnapshotRefreshBootstrap($partida);
        }

        $mapa = MapaHandler::presencia($ctx, $body, $partida);
        $buzon = BuzonHandler::listar($ctx, $body, $partida);
        $diario = DiarioHandler::listar($ctx, $body, $partida);

        $out = [
            'ok' => true,
            'estado' => $ctx->service->estadoResumido($partida),
            'partida' => self::partidaParaRefreshUi(self::enriquecerRetratos($partida, $ctx->root, $ctx->visualPacks())),
            'mapa' => $mapa['mapa'] ?? [],
            'pueblo' => $mapa['pueblo'] ?? [],
            'buzon' => $buzon,
            'diario' => $diario,
            'historia' => HistoriaPuebloHandler::pendientes($partida),
        ];
        if ($labOn) {
            $eventosLab = LabAudit::flush();
            if ($eventosLab !== []) {
                $out['lab_audit'] = ['eventos' => $eventosLab];
            }
        }
        return $out;
    }

    /**
     * Subconjunto de partida que el cliente usa en cacheInsp tras refresh.
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function partidaParaRefreshUi(array $partida): array
    {
        $out = [];
        foreach (['residentes', 'encuentros', 'propuestas_encuentro', 'misiones_diarias', 'relaciones_romanticas', 'celeste'] as $k) {
            if (array_key_exists($k, $partida)) {
                $out[$k] = $partida[$k];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function enriquecerRetratos(array $partida, string $root, ?\AquiHayTema\Engine\VisualPackStore $packs = null): array
    {
        $vista = $partida;
        $mapa = RetratoResolver::mapaCompletoPartida($partida, $root, $packs);
        foreach ($vista['residentes'] ?? [] as $rid => &$res) {
            if (!is_string($rid) || $rid === '' || !is_array($res)) {
                continue;
            }
            $tok = $mapa[$rid] ?? null;
            if (!is_array($tok)) {
                continue;
            }
            $res['retrato_url'] = $tok['url'];
            $res['retrato_pack_id'] = $tok['pack_id'];
            $res['retrato_sin_pack'] = $tok['sin_pack'];
            $res['retrato_asset_faltante'] = $tok['asset_faltante'];
        }
        unset($res);
        return $vista;
    }

    public static function validar(ApiContext $ctx, array $body, array $partida): array
    {
        $errores = PartidaValidator::validar($partida);
        return ['ok' => empty($errores), 'errores' => $errores];
    }

    public static function tutorialFinale(ApiContext $ctx, array $body, array &$partida): array
    {
        \AquiHayTema\Engine\TutorialPrimerosPasos::marcarFinaleVisto($partida);
        savePartida($ctx, $partida);
        return [
            'ok' => true,
            'tutorial' => \AquiHayTema\Engine\TutorialPrimerosPasos::vistaPublica($partida),
        ];
    }

    public static function debugExport(ApiContext $ctx, array $body, array $partida): array
    {
        if (!labActiva($body)) {
            return ['ok' => false, 'error' => 'debug_no_activo'];
        }
        $historial = is_array($body['historial'] ?? null) ? $body['historial'] : [];
        $export = LabAudit::exportCompleto($partida, new Catalog($ctx->root), $historial);
        return withLabAudit([
            'ok' => true,
            'debug_export' => $export,
        ]);
    }

    public static function debugParejasCrear(ApiContext $ctx, array $body, array &$partida): array
    {
        if (!labActiva($body)) {
            return ['ok' => false, 'error' => 'debug_no_activo', 'mensaje_ui' => 'Activa DEBUG primero.'];
        }
        $cal = CalibracionConfig::load($ctx->root);
        $r = DebugParejasEngine::crear($partida, $cal);
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
            if (is_array($r['debug_parejas'] ?? null)) {
                \AquiHayTema\Engine\LabAudit::eventoDebugParejas('crear', $r['debug_parejas']);
            }
        }
        return withLabAudit($r);
    }

    public static function debugParejasQuitar(ApiContext $ctx, array $body, array &$partida): array
    {
        if (!labActiva($body)) {
            return ['ok' => false, 'error' => 'debug_no_activo', 'mensaje_ui' => 'Activa DEBUG primero.'];
        }
        $r = DebugParejasEngine::quitar($partida);
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
            if (is_array($r['debug_parejas'] ?? null)) {
                \AquiHayTema\Engine\LabAudit::eventoDebugParejas('quitar', $r['debug_parejas']);
            }
        }
        return withLabAudit($r);
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function sincronizarEncuentrosSiToca(ApiContext $ctx, array &$partida): void
    {
        $antes = LabAudit::snapshotEstadosEncuentros($partida);
        EncuentroLifecycle::sincronizarConReloj($partida, $ctx->logger, new Catalog($ctx->root));
        if ($antes !== LabAudit::snapshotEstadosEncuentros($partida)) {
            savePartida($ctx, $partida);
        }
    }
}
