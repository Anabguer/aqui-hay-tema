<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\RegaloEngine;
use AquiHayTema\Engine\RegaloHints;
use AquiHayTema\Engine\RegalitoRecompensaService;
use function AquiHayTema\Api\jsonOut;
use function AquiHayTema\Api\savePartida;

/** Inventario de Celestine + accion REGALAR (Fase 1). */
final class RegalosHandler
{
    /**
     * Inventario.listar (GET, carga ligera).
     * Con residente_id opcional anade hints SOLO con conocimiento descubierto
     * (RegaloHints). Sin descubrimientos no hay pistas: cero conocimiento magico.
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function inventario(ApiContext $ctx, array $body, array &$partida): array
    {
        $catalog = new CatalogStore($ctx->root);
        InventarioEngine::ensure($partida);

        $pendientesEntregados = RegalitoRecompensaService::reclamarPendientes($partida, $ctx->logger);
        if (!empty($pendientesEntregados)) {
            savePartida($ctx, $partida);
        }
        $residenteId = trim((string) ($body['residente_id'] ?? ''));
        $vecinoValido = $residenteId !== '' && isset($partida['residentes'][$residenteId]);
        if ($residenteId !== '' && !$vecinoValido) {
            jsonOut(GameError::respuesta(GameError::RESIDENTE_NO_ACTIVO, ['residente_id' => $residenteId], 404));
        }
        $items = [];
        $total = 0;
        foreach (InventarioEngine::listar($partida) as $id => $n) {
            $regalo = $catalog->item('regalos', (string) $id);
            if ($regalo === null) {
                continue; // objeto huerfano: no se muestra
            }
            $n = (int) $n;
            $total += $n;
            $row = [
                'id' => (string) $id,
                'nombre' => (string) ($regalo['nombre'] ?? $id),
                'asset' => (string) ($regalo['asset'] ?? ''),
                'url' => 'assets/play-v3/' . (string) ($regalo['asset'] ?? ''),
                'cantidad' => $n,
                'hint' => null,
                'hint_texto' => null,
            ];
            if ($vecinoValido) {
                $hint = RegaloHints::paraObjeto($partida, $residenteId, is_array($regalo['hobby_ids'] ?? null) ? $regalo['hobby_ids'] : []);
                if ($hint !== null) {
                    $row['hint'] = $hint['nivel'];
                    $row['hint_texto'] = RegaloHints::textoDe(
                        $hint,
                        IdentidadPublica::nombre($partida, $residenteId),
                        $catalog
                    );
                }
            }
            $items[] = $row;
        }
        $out = ['ok' => true, 'inventario' => $items, 'total' => $total];
        if ($vecinoValido) {
            $out['residente_id'] = $residenteId;
            $out['residente_nombre'] = IdentidadPublica::nombre($partida, $residenteId);
        }
        return $out;
    }

    /**
     * Regalo.entregar: valida y resuelve todo en una sola mutacion.
     * Si algo falla, no se consume ni se guarda (atomico).
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function entregar(ApiContext $ctx, array $body, array &$partida): array
    {
        $objectId = trim((string) ($body['objeto_id'] ?? ''));
        $residenteId = trim((string) ($body['residente_id'] ?? ''));
        if ($objectId === '' || $residenteId === '') {
            jsonOut(GameError::respuesta(GameError::VALIDACION_FALLIDA, ['campo' => 'objeto_id, residente_id'], 400));
        }
        $cal = CalibracionConfig::load($ctx->root);
        $res = RegaloEngine::entregar($partida, $residenteId, $objectId, $cal, new CatalogStore($ctx->root), $ctx->logger);
        if (!$res['ok']) {
            $mapa = [
                'regalo_objeto_desconocido' => [GameError::REGALO_OBJETO_DESCONOCIDO, 400],
                'regalo_sin_unidades' => [GameError::REGALO_SIN_UNIDADES, 409],
                'regalo_cooldown' => [GameError::REGALO_COOLDOWN, 409],
                'residente_no_encontrado' => [GameError::RESIDENTE_NO_ACTIVO, 404],
            ];
            $entrada = $mapa[$res['error']] ?? [GameError::VALIDACION_FALLIDA, 400];
            jsonOut(GameError::respuesta($entrada[0], $res, $entrada[1]));
        }
        savePartida($ctx, $partida);
        return $res;
    }

    /**
     * dev.regalo.otorgar: herramienta de prueba (requireDev en la ruta). No es economia.
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function otorgarDev(ApiContext $ctx, array $body, array &$partida): array
    {
        $objectId = trim((string) ($body['objeto_id'] ?? ''));
        $cantidad = (int) ($body['cantidad'] ?? 1);
        if ($objectId === '' || $cantidad <= 0) {
            jsonOut(GameError::respuesta(GameError::VALIDACION_FALLIDA, ['campo' => 'objeto_id, cantidad>0'], 400));
        }
        $res = InventarioEngine::anadir($partida, $objectId, $cantidad, new CatalogStore($ctx->root));
        if (!$res['ok']) {
            $codigo = $res['error'] === 'inventario_lleno' ? GameError::VALIDACION_FALLIDA : GameError::REGALO_OBJETO_DESCONOCIDO;
            jsonOut(GameError::respuesta($codigo, $res, 409));
        }
        savePartida($ctx, $partida);
        return ['ok' => true, 'objeto_id' => $objectId] + $res;
    }
}
