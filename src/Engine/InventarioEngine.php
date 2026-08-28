<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Inventario de Celestine (Fase 1). Mapa object_id => cantidad.
 * Sin RNG, sin economia, sin metadata. Determinista.
 * Tolerante con saves antiguos: ensure() crea la seccion si falta.
 */
final class InventarioEngine
{
    public static function ensure(array &$partida): void
    {
        if (!isset($partida['inventario']) || !is_array($partida['inventario'])) {
            $partida['inventario'] = [];
        }
        foreach ($partida['inventario'] as $id => $n) {
            if (!is_string($id) || $id === '' || !is_numeric($n) || (int) $n <= 0) {
                unset($partida['inventario'][$id]);
            } elseif ((int) $n !== $n) {
                $partida['inventario'][$id] = (int) $n;
            }
        }
        self::ordenar($partida);
    }

    /** @return array<string, int> mapa id => cantidad ordenado por id */
    public static function listar(array $partida): array
    {
        self::ensure($partida);
        return $partida['inventario'];
    }

    public static function cantidad(array $partida, string $objectId): int
    {
        self::ensure($partida);
        return (int) ($partida['inventario'][$objectId] ?? 0);
    }

    public static function totalUnidades(array $partida): int
    {
        $total = 0;
        foreach (self::listar($partida) as $n) {
            $total += (int) $n;
        }
        return $total;
    }

    /**
     * Anade unidades. Si se pasa catalogo, valida que el objeto exista.
     *
     * @return array<string, mixed>
     */
    public static function anadir(array &$partida, string $objectId, int $cantidad, ?CatalogStore $catalog = null): array
    {
        if ($objectId === '') {
            return ['ok' => false, 'error' => 'regalo_objeto_desconocido'];
        }
        if ($cantidad <= 0) {
            return ['ok' => false, 'error' => 'validacion_fallida'];
        }
        if ($catalog !== null && $catalog->item('regalos', $objectId) === null) {
            return ['ok' => false, 'error' => 'regalo_objeto_desconocido'];
        }
        self::ensure($partida);
        $cap = PersistenciaCaps::cap($partida, 'inventario_cap', 200);
        $total = self::totalUnidades($partida);
        $hueco = $cap - $total;
        if ($hueco <= 0) {
            return ['ok' => false, 'error' => 'inventario_lleno', 'cap' => $cap];
        }
        $anadido = min($cantidad, $hueco);
        $partida['inventario'][$objectId] = self::cantidad($partida, $objectId) + $anadido;
        self::ordenar($partida);
        return [
            'ok' => true,
            'anadido' => $anadido,
            'cantidad' => self::cantidad($partida, $objectId),
            'total' => self::totalUnidades($partida),
        ];
    }

    /**
     * Consume unidades. Nunca deja cantidades negativas.
     *
     * @return array<string, mixed>
     */
    public static function consumir(array &$partida, string $objectId, int $cantidad = 1): array
    {
        if ($cantidad <= 0) {
            return ['ok' => false, 'error' => 'validacion_fallida'];
        }
        self::ensure($partida);
        $tengo = self::cantidad($partida, $objectId);
        if ($tengo < $cantidad) {
            return ['ok' => false, 'error' => 'regalo_sin_unidades', 'cantidad' => $tengo];
        }
        $restante = $tengo - $cantidad;
        if ($restante <= 0) {
            unset($partida['inventario'][$objectId]);
        } else {
            $partida['inventario'][$objectId] = $restante;
        }
        return ['ok' => true, 'restante' => max(0, $restante)];
    }

    private static function ordenar(array &$partida): void
    {
        $inv = $partida['inventario'];
        ksort($inv);
        $partida['inventario'] = $inv;
    }
}
