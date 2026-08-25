<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Selección determinista de variantes narrativas con protección mínima
 * anti-repetición: la misma clave (familia/persona/par) nunca repite dos
 * veces seguidas la misma variante si hay más de una disponible.
 *
 * Estado mínimo por partida: partida['copy_ultimo'][clave] = último índice.
 * Solo para copy que se genera una vez y se guarda (evento), nunca para
 * copy recalculado en render.
 */
final class CopyVariante
{
    /**
     * @param list<string> $pool
     */
    public static function elegir(array &$partida, string $clave, array $pool, string $seed): string
    {
        $n = count($pool);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $pool[0];
        }
        $idx = abs(crc32($seed)) % $n;
        $ultimo = $partida['copy_ultimo'][$clave] ?? null;
        if (is_int($ultimo) && $ultimo === $idx) {
            $idx = ($idx + 1) % $n;
        }
        $partida['copy_ultimo'][$clave] = $idx;
        return $pool[$idx];
    }
}
