<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Servicio compartido de concesión de regalitos (recompensas garantizadas).
 *
 * Cada motor es autoridad de su propio cumplimiento y llama a este servicio
 * con un contexto idempotente:
 *   - misiones_diarias:{dia}  (3/3 misiones completadas)
 *   - reto_semanal:{reto_id}  (futuro)
 *   - historia:{hito_id}      (futuro)
 *
 * Reglas:
 *   - máximo 1 regalito por contexto (nunca duplica);
 *   - la idempotencia vive en partida['regalito_recompensas'] (mapa por contexto);
 *   - si el inventario está lleno, la recompensa queda pendiente
 *     DENTRO del mismo mapa de estado (estado: pendiente);
 *   - reclamarPendientes() itera TODOS los pendientes y entrega los que quepan;
 *   - respeta inventario_cap;
 *   - pool configurable en calibracion_vida.json (regalito_recompensa.objetos);
 *   - selección determinista: crc32('regalito_recomp|' . contexto);
 *   - eco narrativo vía mensajito tipo regalito_recompensa.
 *
 * Estructura persistente en partida['regalito_recompensas']:
 *   {
 *     "misiones_diarias:1": { "objeto_id": "taza", "estado": "entregado" },
 *     "reto_semanal:R7":    { "objeto_id": "libro", "estado": "pendiente" }
 *   }
 *
 * NO existe un slot global 'regalito_recompensa_pendiente'.
 * Cada contexto es independiente y se resuelve por sí mismo.
 */
final class RegalitoRecompensaService
{
    public const TIPO_MENSAJE = 'regalito_recompensa';
    public const ORIGEN_EVENTO = 'regalito_recompensa';

    public const ESTADO_ENTREGADO = 'entregado';
    public const ESTADO_PENDIENTE = 'pendiente';

    /** @return list<string> */
    private static function poolMensaje(): array
    {
        return [
            'Has completado todas las misiones de hoy. Te has ganado {objeto}. Ya está en tu inventario.',
            'Día bien hecho: las tres misiones cumplidas. Te llevas {objeto} — guárdalo para quien quieras.',
            'Has cumplido con creces las misiones del día. {objeto} es tuyo. A ver a quién le toca.',
        ];
    }

    private static function poolMensajePendiente(): array
    {
        return [
            'Has completado todas las misiones de hoy. Te has ganado {objeto}, pero el inventario está lleno. Cuando libres hueco, será tuyo.',
        ];
    }

    /**
     * Otorga 1 regalito garantizado por un contexto dado.
     * Idempotente: segunda llamada con el mismo contexto devuelve null.
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>|null null si ya otorgado o no aplica
     */
    public static function otorgar(array &$partida, string $contexto, ?GameLogger $logger = null): ?array
    {
        if ($contexto === '') {
            return null;
        }

        $root = dirname(__DIR__, 2);
        $cal = CalibracionConfig::load($root);

        if (!(bool) CalibracionConfig::get($cal, 'regalito_recompensa.activa', true)) {
            return null;
        }

        // --- idempotencia: ¿ya otorgado/pendiente para este contexto? ---
        $partida['regalito_recompensas'] ??= [];
        if (isset($partida['regalito_recompensas'][$contexto])) {
            return null;
        }

        // --- elegir objeto del pool ---
        $catalog = new CatalogStore($root);
        $objetoId = self::elegirObjeto($cal, $contexto, $catalog);
        if ($objetoId === null) {
            return null;
        }

        // --- intentar añadir a inventario ---
        $alta = InventarioEngine::anadir($partida, $objetoId, 1, $catalog);
        if (empty($alta['ok'])) {
            // Inventario lleno: guardar como pendiente dentro del mapa por contexto
            $partida['regalito_recompensas'][$contexto] = [
                'objeto_id' => $objetoId,
                'estado' => self::ESTADO_PENDIENTE,
            ];
            $nombreObjeto = self::nombreObjeto($catalog, $objetoId);
            self::mensajito($partida, $objetoId, $nombreObjeto, $logger, true);
            return [
                'ok' => true,
                'objeto_id' => $objetoId,
                'objeto_nombre' => $nombreObjeto,
                'cantidad' => 0,
                'pendiente' => true,
            ];
        }

        // --- marcar como entregado ---
        $partida['regalito_recompensas'][$contexto] = [
            'objeto_id' => $objetoId,
            'estado' => self::ESTADO_ENTREGADO,
        ];

        $nombreObjeto = self::nombreObjeto($catalog, $objetoId);
        self::mensajito($partida, $objetoId, $nombreObjeto, $logger, false);

        return [
            'ok' => true,
            'objeto_id' => $objetoId,
            'objeto_nombre' => $nombreObjeto,
            'cantidad' => (int) ($alta['cantidad'] ?? 1),
            'pendiente' => false,
        ];
    }

    /**
     * Reclama TODAS las recompensas pendientes que quepan en inventario.
     * Llamar al listar inventario (inventario.listar).
     * Operación idempotente: los que ya estaban entregados no se procesan.
     *
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>> recompensas entregadas en esta llamada
     */
    public static function reclamarPendientes(array &$partida, ?GameLogger $logger = null): array
    {
        $partida['regalito_recompensas'] ??= [];
        $entregados = [];

        $root = dirname(__DIR__, 2);
        $catalog = new CatalogStore($root);

        foreach ($partida['regalito_recompensas'] as $contexto => &$entry) {
            if (!is_array($entry) || ($entry['estado'] ?? '') !== self::ESTADO_PENDIENTE) {
                continue;
            }

            $objetoId = (string) ($entry['objeto_id'] ?? '');
            if ($objetoId === '') {
                continue;
            }

            $alta = InventarioEngine::anadir($partida, $objetoId, 1, $catalog);
            if (empty($alta['ok'])) {
                // Inventario sigue lleno, este pendiente queda para la próxima
                continue;
            }

            // Entregado: actualizar estado dentro del mapa
            $entry['estado'] = self::ESTADO_ENTREGADO;

            $nombreObjeto = self::nombreObjeto($catalog, $objetoId);
            self::mensajito($partida, $objetoId, $nombreObjeto, $logger, false);

            $entregados[] = [
                'ok' => true,
                'objeto_id' => $objetoId,
                'objeto_nombre' => $nombreObjeto,
                'cantidad' => (int) ($alta['cantidad'] ?? 1),
                'contexto' => $contexto,
            ];
        }
        unset($entry);

        return $entregados;
    }

    /**
     * ¿Hay recompensas pendientes?
     */
    public static function hayPendientes(array $partida): bool
    {
        foreach ($partida['regalito_recompensas'] ?? [] as $entry) {
            if (is_array($entry) && ($entry['estado'] ?? '') === self::ESTADO_PENDIENTE) {
                return true;
            }
        }
        return false;
    }

    /**
     * Objeto determinista por contexto dentro del pool calibrado.
     */
    public static function elegirObjeto(array $cal, string $contexto, CatalogStore $catalog): ?string
    {
        $lista = CalibracionConfig::get($cal, 'regalito_recompensa.objetos', []);
        $lista = is_array($lista) ? array_values(array_filter($lista, 'is_string')) : [];
        if ($lista === []) {
            return null;
        }
        $validas = [];
        foreach ($lista as $id) {
            if ($catalog->item('regalos', $id) !== null) {
                $validas[] = $id;
            }
        }
        if ($validas === []) {
            return null;
        }
        sort($validas);
        return $validas[crc32('regalito_recomp|' . $contexto) % count($validas)];
    }

    private static function nombreObjeto(CatalogStore $catalog, string $objetoId): string
    {
        $item = $catalog->item('regalos', $objetoId);
        return (string) (is_array($item) ? ($item['nombre'] ?? $objetoId) : $objetoId);
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function mensajito(array &$partida, string $objetoId, string $objetoNombre, ?GameLogger $logger, bool $pendiente): void
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return;
        }
        if (!empty($partida['_lab_peticiones_b4']) || !empty($partida['_lab_misiones_b3'])) {
            return;
        }
        $pool = $pendiente ? self::poolMensajePendiente() : self::poolMensaje();
        $plantilla = $pool[crc32($objetoId) % count($pool)];
        $texto = strtr($plantilla, ['{objeto}' => $objetoNombre]);
        $r = BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::OPORTUNIDAD,
            'tipo' => self::TIPO_MENSAJE,
            'canal' => BuzonEngine::CANAL_BUZON,
            'de_persona' => null,
            'actores' => [],
            'texto' => $texto,
            'origen' => [
                'evento_id' => self::ORIGEN_EVENTO . ':' . $objetoId,
                'tipo_evento' => self::TIPO_MENSAJE,
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje' => $r['mensaje'] ?? null,
            'origen_evento' => self::TIPO_MENSAJE,
        ], $logger, 'RegalitoRecompensaService');
    }
}
