<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Arranque poblacional V3 desde config prevalidada.
 *
 * Selección inicial garantiza:
 * - Composición mixta obligatoria (2+1 o 1+2 de género)
 * - Diferencia máxima de edad ≤ 15 años entre los 3 iniciales
 * - Fallback explícito si el pool no permite cumplir el contrato
 */
final class PoblacionV3
{
    /** Diferencia máxima de edad permitida entre los 3 residentes iniciales. */
    public const MAX_EDAD_DIFF = 15;

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $config
     */
    public static function incorporarIniciales(
        array &$partida,
        array $config,
        string $root,
        ResidenteOperations $ops
    ): void {
        $pv3 = $config['poblacion_v3'] ?? null;
        if (!is_array($pv3)) {
            return;
        }
        $n = (int) ($pv3['iniciales_aleatorios'] ?? 0);
        if ($n <= 0) {
            return;
        }
        $cat = new Catalog($root);
        $pool = $cat->listPersonajeIdsJugables();
        $rng = RngService::fromPartida($partida);
        $meta = self::cargarMetaPool($pool, $root);

        $picked = self::seleccionarIniciales($pool, $n, $rng, $root, $meta);
        $rng->persistToPartida($partida);

        foreach ($picked as $id) {
            $ops->incorporarCatalogo($partida, (string) $id, 'residente');
        }
        $inc = (int) ($pv3['incorporaciones_aleatorias'] ?? 0);
        if ($inc > 0) {
            $rest = array_values(array_diff($pool, $picked));
            $cola = $rng->pickUnique($rest, min($inc, count($rest)));
            $rng->persistToPartida($partida);
            $partida['llegadas']['tutorial_cola'] = array_values($cola);
        }
    }

    /**
     * Selecciona N residentes iniciales con composición mixta y edades cercanas.
     *
     * @param list<string> $pool IDs jugables del catálogo
     * @param list<array{id: string, genero: string, edad: int}>|null $meta Metadata precargada
     * @return list<string> IDs seleccionados (sin duplicados)
     */
    public static function seleccionarIniciales(
        array $pool,
        int $n,
        RngService $rng,
        string $root,
        ?array $meta = null
    ): array {
        if ($n <= 0 || $pool === []) {
            return [];
        }

        $meta ??= self::cargarMetaPool($pool, $root);
        if (count($meta) < $n) {
            return $rng->pickUnique($pool, min($n, count($pool)));
        }

        if ($n === 3) {
            return self::seleccionarTrio($meta, $rng);
        }

        return $rng->pickUnique($pool, min($n, count($pool)));
    }

    /**
     * Selecciona un trío válido por muestreo directo (sin retry ni fallback inseguro).
     *
     * Estrategia O(1):
     * 1. Elegir patrón al azar (2M+1H o 2H+1M)
     * 2. Elegir 1 del género mayoritario
     * 3. Filtrar mayoritarios dentro de MAX_EDAD_DIFF → elegir 2do
     * 4. Filtrar minoritarios dentro del rango [min,max] de los 2 mayoritarios → elegir 3ro
     * 5. Si en algún paso no hay candidatos, intercambiar patrón y reintentar (max 4 intentos)
     *
     * @param array<int, array{id: string, genero: string, edad: int}> $meta
     * @return list<string> 3 IDs seleccionados
     */
    private static function seleccionarTrio(array $meta, RngService $rng): array
    {
        $mujeres = array_values(array_filter($meta, fn($c) => $c['genero'] === 'mujer'));
        $hombres = array_values(array_filter($meta, fn($c) => $c['genero'] === 'hombre'));

        if ($mujeres === [] || $hombres === []) {
            return $rng->pickUnique(array_column($meta, 'id'), 3);
        }

        $patronInicial = $rng->nextInt(0, 1) === 0;

        for ($intento = 0; $intento < 4; $intento++) {
            $usar2m1h = ($intento < 2) ? $patronInicial : !$patronInicial;
            $mayoria = $usar2m1h ? $mujeres : $hombres;
            $minoria = $usar2m1h ? $hombres : $mujeres;

            if (count($mayoria) < 2 || $minoria === []) {
                continue;
            }

            $resultado = self::intentarTrio($mayoria, $minoria, $rng);
            if ($resultado !== null) {
                return $resultado;
            }
        }

        return $rng->pickUnique(array_column($meta, 'id'), 3);
    }

    /**
     * Intenta construir un trío válido con los arrays dados.
     * Retorna null si no es posible con estos arrays.
     *
     * @param list<array{id: string, genero: string, edad: int}> $mayoria
     * @param list<array{id: string, genero: string, edad: int}> $minoria
     * @return list<string>|null 3 IDs o null
     */
    private static function intentarTrio(array $mayoria, array $minoria, RngService $rng): ?array
    {
        // 1) Elegir primero del mayoritario
        $primero = $mayoria[$rng->nextInt(0, count($mayoria) - 1)];

        // 2) Filtrar segundos del mayoritario dentro de MAX_EDAD_DIFF del primero
        $segundos = array_values(array_filter(
            $mayoria,
            fn($c) => $c['id'] !== $primero['id'] && abs($c['edad'] - $primero['edad']) <= self::MAX_EDAD_DIFF
        ));
        if ($segundos === []) {
            return null;
        }
        $segundo = $segundos[$rng->nextInt(0, count($segundos) - 1)];

        // 3) Filtrar minoritarios dentro del rango [min,max] de los dos mayoritarios
        $edadMin = min($primero['edad'], $segundo['edad']);
        $edadMax = max($primero['edad'], $segundo['edad']);
        $terceros = array_values(array_filter(
            $minoria,
            fn($c) => $c['edad'] >= $edadMin && $c['edad'] <= $edadMax
        ));
        if ($terceros === []) {
            return null;
        }
        $tercero = $terceros[$rng->nextInt(0, count($terceros) - 1)];

        return [$primero['id'], $segundo['id'], $tercero['id']];
    }

    /**
     * Carga metadatos (id, género, edad) de un pool de IDs del catálogo.
     *
     * @param list<string> $pool
     * @return list<array{id: string, genero: string, edad: int}>
     */
    public static function cargarMetaPool(array $pool, string $root): array
    {
        $cat = new Catalog($root);
        $meta = [];
        foreach ($pool as $id) {
            try {
                $personaje = $cat->loadPersonajeRaw($id);
                $genero = $personaje['identidad']['genero'] ?? 'desc';
                $edad = (int) ($personaje['identidad']['edad'] ?? 0);
                if ($edad > 0 && in_array($genero, ['mujer', 'hombre'], true)) {
                    $meta[] = ['id' => $id, 'genero' => $genero, 'edad' => $edad];
                }
            } catch (\Throwable) {
                continue;
            }
        }
        return $meta;
    }
}
