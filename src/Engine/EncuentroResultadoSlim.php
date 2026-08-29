<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Limpieza segura e idempotente de campos pesados obsoletos en encuentros terminados.
 *
 * Fase 1: elimina únicamente `resultado._cal` de encuentros `terminado`.
 * `_cal` es un snapshot de calibración generado durante la resolución (EncuentroResolver::resolver)
 * y consumido por `aplicarResultado`. Tras el cierre completo del encuentro (resolución + dominio),
 * `_cal` no es necesario para ningún subsistema del juego.
 *
 * Idempotente: ejecutar múltiples veces sobre el mismo encuentro produce el mismo resultado.
 */
final class EncuentroResultadoSlim
{
    /**
     * Claves de `resultado` que se conservan en todo encuentro terminado.
     * Si un encuentro tiene otras claves (ej. `_dev_traza`), se eliminan igualmente.
     *
     * NOTA: Esta whitelist NO se aplica aquí; la limpieza solo quita `_cal`.
     * Se documenta como referencia para futuras fases de compactación.
     */
    public const CLAVES_CONSERVAR = [
        '_placeholder',
        '_deltas_reales',
        'delta_social',
        'delta_romance',
        'conflicto',
        'descubrimientos',
        'eventos_derivados',
        'por_participante',
        'experiencia',
        'experiencia_narrativa',
        'texto_resumen',
        'historial_par',
        'emociones',
    ];

    /**
     * Elimina `resultado._cal` de un único encuentro terminado.
     *
     * - Si el encuentro no es `terminado`, no hace nada (seguro para activos/en_curso).
     * - Si no tiene `resultado`, no hace nada.
     * - Si `_cal` no existe, no hace nada (idempotente).
     * - Modifica el array in-place por referencia.
     *
     * @param array<string, mixed> &$enc Referencia al encuentro dentro de `$partida['encuentros']`
     * @return bool true si se realizó algún cambio, false si no
     */
    public static function limpiarEncuentro(array &$enc): bool
    {
        if (($enc['estado'] ?? '') !== 'terminado') {
            return false;
        }
        if (!is_array($enc['resultado'] ?? null)) {
            return false;
        }
        if (!isset($enc['resultado']['_cal'])) {
            return false;
        }
        unset($enc['resultado']['_cal']);
        return true;
    }

    /**
     * Recorre todos los encuentros de una partida y limpia `_cal` de los terminados.
     *
     * @param array<string, mixed> &$partida Referencia a la partida completa
     * @return array{limpiados: int, bytes_antes: int, bytes_despues: int}
     */
    public static function limpiarPartida(array &$partida): array
    {
        $encuentros = &$partida['encuentros'];
        if (!is_array($encuentros) || $encuentros === []) {
            return ['limpiados' => 0, 'bytes_antes' => 0, 'bytes_despues' => 0];
        }

        $jsonAntes = strlen((string) json_encode($encuentros, JSON_UNESCAPED_UNICODE));
        $limpiados = 0;

        foreach ($encuentros as &$enc) {
            if (self::limpiarEncuentro($enc)) {
                $limpiados++;
            }
        }
        unset($enc);

        $jsonDespues = strlen((string) json_encode($encuentros, JSON_UNESCAPED_UNICODE));

        return [
            'limpiados' => $limpiados,
            'bytes_antes' => $jsonAntes,
            'bytes_despues' => $jsonDespues,
        ];
    }

    /**
     * Versión read-only para dry-run: calcula el ahorro sin modificar nada.
     *
     * @param array<string, mixed> $partida
     * @return array{inspectados: int, terminados_con_cal: int, bytes_antes: int, bytes_despues: int, bytes_ahorrados: int}
     */
    public static function inspeccionar(array $partida): array
    {
        $encuentros = $partida['encuentros'] ?? [];
        if (!is_array($encuentros) || $encuentros === []) {
            return ['inspectados' => 0, 'terminados_con_cal' => 0, 'bytes_antes' => 0, 'bytes_despues' => 0, 'bytes_ahorrados' => 0];
        }

        $jsonAntes = strlen((string) json_encode($encuentros, JSON_UNESCAPED_UNICODE));
        $inspectados = 0;
        $terminadosConCal = 0;

        foreach ($encuentros as $enc) {
            $inspectados++;
            if (($enc['estado'] ?? '') === 'terminado'
                && is_array($enc['resultado'] ?? null)
                && isset($enc['resultado']['_cal'])
            ) {
                $terminadosConCal++;
            }
        }

        $jsonDespues = $jsonAntes;
        if ($terminadosConCal > 0) {
            $copia = $encuentros;
            foreach ($copia as &$enc) {
                if (($enc['estado'] ?? '') === 'terminado' && is_array($enc['resultado'] ?? null)) {
                    unset($enc['resultado']['_cal']);
                }
            }
            unset($enc);
            $jsonDespues = strlen((string) json_encode($copia, JSON_UNESCAPED_UNICODE));
        }

        return [
            'inspectados' => $inspectados,
            'terminados_con_cal' => $terminadosConCal,
            'bytes_antes' => $jsonAntes,
            'bytes_despues' => $jsonDespues,
            'bytes_ahorrados' => $jsonAntes - $jsonDespues,
        ];
    }
}
