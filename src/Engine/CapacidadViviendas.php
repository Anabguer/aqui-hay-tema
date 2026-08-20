<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Capacidad de vivienda por bloques abiertos.
 * A = 16 canónico. B/C = 16 cada uno cuando el bloque está abierto
 * (lab/gate puede abrir B/C sin flujo de compra).
 */
final class CapacidadViviendas
{
    public const CAP_POR_BLOQUE = 16;

    /** @return list<string> */
    public static function bloquesAbiertos(array $partida): array
    {
        $raw = $partida['celeste']['bloques_abiertos'] ?? ['a'];
        if (!is_array($raw) || $raw === []) {
            return ['a'];
        }
        $out = [];
        foreach ($raw as $b) {
            $b = strtolower((string) $b);
            if (in_array($b, ['a', 'b', 'c'], true) && !in_array($b, $out, true)) {
                $out[] = $b;
            }
        }
        return $out !== [] ? $out : ['a'];
    }

    public static function capacidadTotal(array $partida): int
    {
        return count(self::bloquesAbiertos($partida)) * self::CAP_POR_BLOQUE;
    }

    /**
     * Materializa viviendas B/C si el bloque está abierto y faltan slots.
     *
     * @param array<string, mixed> $partida
     */
    public static function ensure(array &$partida): void
    {
        $partida['bloque_a'] ??= ['capacidad' => self::CAP_POR_BLOQUE, 'viviendas' => []];
        if (!is_array($partida['bloque_a']['viviendas'] ?? null) || $partida['bloque_a']['viviendas'] === []) {
            $partida['bloque_a']['viviendas'] = BloqueA::viviendasVacias();
        }
        $partida['bloque_a']['capacidad'] = self::CAP_POR_BLOQUE;

        foreach (['b' => 'bloque_b', 'c' => 'bloque_c'] as $letra => $key) {
            if (!in_array($letra, self::bloquesAbiertos($partida), true)) {
                continue;
            }
            $partida[$key] ??= ['capacidad' => self::CAP_POR_BLOQUE, 'viviendas' => []];
            if (!is_array($partida[$key]['viviendas']) || count($partida[$key]['viviendas']) < self::CAP_POR_BLOQUE) {
                $partida[$key]['viviendas'] = self::viviendasVacias($letra);
            }
            $partida[$key]['capacidad'] = self::CAP_POR_BLOQUE;
        }
    }

    /** @return list<array{id:string,ocupante_id:?string,estado:string}> */
    public static function viviendasVacias(string $bloque): array
    {
        $bloque = strtoupper($bloque);
        $out = [];
        for ($i = 1; $i <= self::CAP_POR_BLOQUE; $i++) {
            $out[] = [
                'id' => $bloque . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'ocupante_id' => null,
                'estado' => 'libre',
            ];
        }
        return $out;
    }

    public static function huecos(array $partida): int
    {
        self::ensure($partida);
        return max(0, self::capacidadTotal($partida) - self::ocupadas($partida));
    }

    public static function ocupadas(array $partida): int
    {
        $n = 0;
        foreach (self::viviendasTodas($partida) as $v) {
            if (($v['ocupante_id'] ?? null) !== null) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @return list<array{id:string,ocupante_id:?string,estado:string,bloque:string}>
     */
    public static function viviendasTodas(array $partida): array
    {
        self::ensure($partida);
        $out = [];
        $map = ['a' => 'bloque_a', 'b' => 'bloque_b', 'c' => 'bloque_c'];
        foreach (self::bloquesAbiertos($partida) as $letra) {
            $key = $map[$letra];
            foreach ($partida[$key]['viviendas'] ?? [] as $v) {
                if (!is_array($v)) {
                    continue;
                }
                $v['bloque'] = $letra;
                $out[] = $v;
            }
        }
        return $out;
    }

    /** @return array{vivienda_id: string|null, error: string|null} */
    public static function asignarAutomatico(array &$partida, string $residenteId): array
    {
        self::ensure($partida);
        $map = ['a' => 'bloque_a', 'b' => 'bloque_b', 'c' => 'bloque_c'];
        foreach (self::bloquesAbiertos($partida) as $letra) {
            $key = $map[$letra];
            foreach ($partida[$key]['viviendas'] as &$vivienda) {
                if ($vivienda['ocupante_id'] === null && ($vivienda['estado'] ?? '') === 'libre') {
                    $vivienda['ocupante_id'] = $residenteId;
                    $vivienda['estado'] = 'ocupado';
                    if (isset($partida['residentes'][$residenteId])) {
                        $partida['residentes'][$residenteId]['vivienda_id'] = $vivienda['id'];
                    }
                    return ['vivienda_id' => $vivienda['id'], 'error' => null];
                }
            }
            unset($vivienda);
        }
        return ['vivienda_id' => null, 'error' => 'viviendas_llenas'];
    }

    /** Abre bloque para lab/gate sin compra. */
    public static function abrirBloque(array &$partida, string $bloque): void
    {
        $bloque = strtolower($bloque);
        if (!in_array($bloque, ['a', 'b', 'c'], true)) {
            return;
        }
        $abiertos = self::bloquesAbiertos($partida);
        if (!in_array($bloque, $abiertos, true)) {
            $abiertos[] = $bloque;
            $partida['celeste']['bloques_abiertos'] = $abiertos;
        }
        self::ensure($partida);
    }
}
