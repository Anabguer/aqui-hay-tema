<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * §23.6 Detallitos sorpresa: fuente orgánica de objetos al cumplir misiones
 * diarias. No todas las misiones dan objeto (la mayoría sigue dando Latido).
 *
 * Reglas (calibración detallitos):
 *   - probabilidad por misión cumplida (default 0.15).
 *   - determinista: el "sorteo" es crc32(mision.id), no RNG de partida.
 *   - máximo 1 detallito/día (persistente en misiones_diarias.detallitos_dia).
 *   - cooldown mínimo entre detallitos (default 2 días).
 *   - respeta inventario_cap; sin hueco no hay detallito.
 *
 * El eco narrativo vive en un Mensajito (tipo detallito_sorpresa,
 * clasificacion oportunidad), reutilizando el patrón de
 * RegaloRecompensaEngine.
 */
final class DetallitoEngine
{
    public const TIPO_MENSAJE = 'detallito_sorpresa';
    public const ORIGEN_EVENTO = 'detallito_sorpresa';

    /** @return list<string> */
    private static function poolMensaje(): array
    {
        return [
            'Hoy, al completar una misión, te has ganado un detallito. Abres la cajita y encuentras {objeto}.',
            'Pequeña sorpresa del día: {objeto}. Guardado en tu inventario. Te lo has ganado al cumplir una misión.',
            'Detallito merecido: {objeto}. Regalo al cumplir una misión. Ya está en el cajón.',
        ];
    }

    /**
     * Punto de enganche: llamado desde MisionDiariaEngine::cumplirIndice().
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>|null null si no toca detallito
     */
    public static function alCumplirMision(array &$partida, array $mision, ?GameLogger $logger = null): ?array
    {
        if (!FeatureConfig::isEnabled($partida, 'misiones_diarias_enabled')) {
            return null;
        }

        $root = dirname(__DIR__, 2);
        $cal = CalibracionConfig::load($root);

        if (!(bool) CalibracionConfig::get($cal, 'detallitos.probabilidad', 0)) {
            return null;
        }

        $mid = (string) ($mision['id'] ?? '');
        if ($mid === '') {
            return null;
        }

        // --- cooldown mínimo entre detallitos ---
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['misiones_diarias'] ??= [];
        $ultimoDetallito = (int) ($partida['misiones_diarias']['ultimo_detallito_dia'] ?? 0);
        $cooldown = (int) CalibracionConfig::get($cal, 'detallitos.cooldown_minimo_dias', 2);
        if ($ultimoDetallito > 0 && ($dia - $ultimoDetallito) < $cooldown) {
            return null;
        }

        // --- tope diario ---
        $contador = is_array($partida['misiones_diarias']['detallitos_dia'] ?? null)
            ? $partida['misiones_diarias']['detallitos_dia']
            : ['n' => 0, 'otorgados' => 0];
        if ((int) ($contador['n'] ?? 0) !== $dia) {
            $contador = ['n' => $dia, 'otorgados' => 0];
        }
        $maxDia = (int) CalibracionConfig::get($cal, 'detallitos.max_por_dia', 1);
        if ((int) $contador['otorgados'] >= max(1, $maxDia)) {
            return null;
        }

        // --- probabilidad determinista ---
        $prob = (float) CalibracionConfig::get($cal, 'detallitos.probabilidad', 0.15);
        $toca = ((crc32('detallito|' . $mid) % 10000) / 10000) < $prob;
        if (!$toca) {
            return null;
        }

        // --- elegir objeto del catálogo ---
        $catalog = new CatalogStore($root);
        $objetoId = self::elegirObjeto($cal, $mid, $catalog);
        if ($objetoId === null) {
            return null;
        }

        // --- inventario ---
        $alta = InventarioEngine::anadir($partida, $objetoId, 1, $catalog);
        if (empty($alta['ok'])) {
            return null;
        }

        // --- persistir contadores ---
        $contador['otorgados'] = (int) $contador['otorgados'] + 1;
        $partida['misiones_diarias']['detallitos_dia'] = $contador;
        $partida['misiones_diarias']['ultimo_detallito_dia'] = $dia;

        $nombreObjeto = self::nombreObjeto($catalog, $objetoId);

        // --- eco narrativo ---
        self::mensajito($partida, $objetoId, $nombreObjeto, $logger);

        return [
            'ok' => true,
            'objeto_id' => $objetoId,
            'objeto_nombre' => $nombreObjeto,
            'cantidad' => (int) ($alta['cantidad'] ?? 1),
        ];
    }

    /**
     * Objeto determinista por misión dentro de la lista calibrada.
     */
    public static function elegirObjeto(array $cal, string $misionId, CatalogStore $catalog): ?string
    {
        $lista = CalibracionConfig::get($cal, 'detallitos.objetos', []);
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
        return $validas[crc32('detallito_obj|' . $misionId) % count($validas)];
    }

    private static function nombreObjeto(CatalogStore $catalog, string $objetoId): string
    {
        $item = $catalog->item('regalos', $objetoId);
        return (string) (is_array($item) ? ($item['nombre'] ?? $objetoId) : $objetoId);
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function mensajito(array &$partida, string $objetoId, string $objetoNombre, ?GameLogger $logger): void
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return;
        }
        if (!empty($partida['_lab_peticiones_b4']) || !empty($partida['_lab_misiones_b3'])) {
            return;
        }
        $pool = self::poolMensaje();
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
        ], $logger, 'DetallitoEngine');
    }
}
