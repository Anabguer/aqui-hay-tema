<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

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
