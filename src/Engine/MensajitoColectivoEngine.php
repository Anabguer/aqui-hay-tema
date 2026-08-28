<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * F4 — Mensajito colectivo → EventosPuebloEngine (B1).
 *
 * Un vecino propone organizar algo con otros; Celestine acepta o declina.
 * Si acepta, se programa un evento REAL vía EventosPuebloEngine (sin segundo sistema).
 */
final class MensajitoColectivoEngine
{
    /**
     * @param array<string, mixed> $cal
     * @return array{familia: string, peso: int, datos: array<string, mixed>}|null
     */
    public static function candidato(array $partida, string $residenteId, array $cal, Catalog $catalog): ?array
    {
        if (!self::eventosDisponibles($partida, $cal, $catalog)) {
            return null;
        }
        $items = EventosPuebloEngine::catalogItems($catalog);
        if ($items === []) {
            return null;
        }
        $prob = (float) CalibracionConfig::get($cal, 'mensajitos.f4_prob_base', 0.06);
        $rng = RngService::fromPartida($partida);
        if ($rng->nextInt(1, 10000) > $prob * 10000) {
            return null;
        }
        $def = EventosPuebloEngine::elegirItemCatalogo($items, $rng);
        $rng->persistToPartida($partida);
        if ($def === null) {
            return null;
        }
        $eventoId = (string) ($def['id'] ?? '');
        if ($eventoId === '') {
            return null;
        }
        $clave = 'f_colectivo|' . $eventoId . '|' . $residenteId;
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $residenteId, 'f_colectivo', $clave)) {
            return null;
        }
        $nombreEvt = (string) ($def['nombre'] ?? $eventoId);
        return [
            'familia' => 'f_colectivo',
            'peso' => 1,
            'datos' => [
                'evento_catalogo_id' => $eventoId,
                'evento_nombre' => $nombreEvt,
                'clave' => $clave,
            ],
        ];
    }

    /**
     * Celestine acepta: programa evento colectivo real.
     *
     * @return array<string, mixed>
     */
    public static function aceptar(
        array &$partida,
        string $mensajeId,
        string $root,
        ?GameLogger $logger = null
    ): array {
        $mensaje = self::buscarRaw($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $datos = is_array($mensaje['datos_familia'] ?? null) ? $mensaje['datos_familia'] : [];
        $eventoId = (string) ($datos['evento_catalogo_id'] ?? '');
        $cal = CalibracionConfig::load($root);
        $catalog = new Catalog($root);
        if ($eventoId === '' || EventosPuebloEngine::catalogItem($catalog, $eventoId) === null) {
            return ['ok' => false, 'error' => 'evento_catalogo_invalido'];
        }
        if (!self::eventosDisponibles($partida, $cal, $catalog)) {
            return ['ok' => false, 'error' => 'eventos_no_disponibles'];
        }
        $rng = RngService::fromPartida($partida);
        $r = EventosPuebloEngine::planificar($partida, $eventoId, $cal, $rng, $catalog, $logger);
        $rng->persistToPartida($partida);
        if (!($r['ok'] ?? false) && !str_starts_with((string) ($r['resultado'] ?? ''), 'evento_programado')) {
            return [
                'ok' => false,
                'error' => 'no_se_pudo_programar',
                'detalle' => (string) ($r['resultado'] ?? ''),
            ];
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'aceptar_evento', 'evento_id' => $eventoId]);
        $evt = is_array($r['evento'] ?? null) ? $r['evento'] : [];
        return [
            'ok' => true,
            'mensaje_ui' => 'Perfecto, lo organizamos.',
            'evento_pueblo' => $evt,
            'detallito_hook' => ['pendiente' => false, 'motivo' => 'evento_colectivo_programado'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function declinar(array &$partida, string $mensajeId): array
    {
        $mensaje = self::buscarRaw($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'declinar_evento']);
        return ['ok' => true, 'mensaje_ui' => 'Vale, esta vez paso.'];
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function eventosDisponibles(array $partida, array $cal, Catalog $catalog): bool
    {
        if (EventosPuebloEngine::catalogItems($catalog) === []) {
            return false;
        }
        if (FeatureConfig::isEnabled($partida, 'eventos_pueblo_enabled')) {
            return true;
        }
        return (bool) CalibracionConfig::get($cal, 'eventos_pueblo.activo', false);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function cerrarHilo(array &$partida, string $mensajeId, array $meta): void
    {
        foreach ($partida['buzon'] as &$m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $mensajeId) {
                continue;
            }
            $m['respuesta_celestine'] = $meta;
            $m['hilo_estado'] = 'respondido';
            $m['seguimiento_pendiente'] = false;
            break;
        }
        unset($m);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buscarRaw(array $partida, string $mensajeId): ?array
    {
        foreach ($partida['buzon'] ?? [] as $m) {
            if (is_array($m) && (string) ($m['id'] ?? '') === $mensajeId) {
                return $m;
            }
        }
        return null;
    }
}
