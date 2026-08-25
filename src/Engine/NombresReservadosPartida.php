<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Regla global de partida: ningún personaje que haya formado parte de ella
 * puede compartir nombre visible normalizado con otro personaje de esa partida.
 *
 * Única implementación compartida de normalización y reserva de nombres.
 * Cubre iniciales, tutorial, cola del tutorial, llegadas posteriores y
 * antiguos residentes (marchados), que siguen reservando su nombre.
 */
final class NombresReservadosPartida
{
    /** Comparación de nombres: trim + lowercase UTF-8 seguro. */
    public static function normalizar(string $nombre): string
    {
        $s = trim(Utf8Text::paraJson($nombre));
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }
        return strtolower($s);
    }

    /**
     * Nombres reservados en esta partida: residentes activos, antiguos
     * residentes (presencia 'antiguo_residente' tras marcha) e historial
     * canónico de llegadas efectivas.
     *
     * @param array<string, mixed> $partida
     * @return array<string, true>
     */
    public static function usados(array $partida, string $root): array
    {
        $out = [];
        foreach ($partida['residentes'] ?? [] as $rid => $r) {
            if (!is_string($rid) || $rid === '' || !is_array($r)) {
                continue;
            }
            $n = $r['identidad_publica']['nombre'] ?? null;
            if (!is_string($n)) {
                continue;
            }
            $k = self::normalizar($n);
            if ($k !== '') {
                $out[$k] = true;
            }
        }
        foreach ($partida['llegadas']['historial'] ?? [] as $row) {
            if (!is_array($row)
                || ($row['resultado'] ?? null) !== CandidatoLlegadaEngine::ESTADO_LLEGADO
                || !isset($row['catalog_id'])) {
                continue;
            }
            $cid = (string) $row['catalog_id'];
            if ($cid === '' || isset($partida['residentes'][$cid])) {
                continue; // nombre ya cubierto vía residentes
            }
            $k = self::normalizar(self::nombreCatalogo($root, $cid));
            if ($k !== '') {
                $out[$k] = true;
            }
        }
        return $out;
    }

    public static function nombreCatalogo(string $root, string $catalogId): string
    {
        try {
            $p = (new Catalog($root))->loadPersonaje($catalogId);
            return (string) ($p['identidad']['nombre'] ?? $p['nombre'] ?? $catalogId);
        } catch (\Throwable $e) {
            return $catalogId;
        }
    }

    /** ¿Este id del catálogo lleva un nombre ya reservado en la partida? */
    public static function idBloqueado(array $usados, string $root, string $catalogId): bool
    {
        if ($usados === []) {
            return false;
        }
        return isset($usados[self::normalizar(self::nombreCatalogo($root, $catalogId))]);
    }

    /**
     * Escoge hasta $cuantos ids sin repetir nombre entre ellos ni con los
     * nombres ya reservados. Selección uniforme sobre los restantes; cada id
     * descartado sale del pool, así que siempre termina (sin bucles infinitos).
     *
     * @param list<string> $disponibles se consume in situ
     * @param array<string, true> $usados se amplía con los nombres escogidos
     * @return list<string>
     */
    public static function escogerSinRepetirNombre(
        RngService $rng,
        array &$disponibles,
        int $cuantos,
        array &$usados,
        string $root
    ): array {
        $out = [];
        while ($cuantos > 0 && $disponibles !== []) {
            $pick = $rng->pickUnique($disponibles, 1);
            $id = (string) ($pick[0] ?? '');
            $disponibles = array_values(array_diff($disponibles, [$id]));
            if ($id === '' || self::idBloqueado($usados, $root, $id)) {
                continue; // descartado por nombre duplicado: prueba otro
            }
            $usados[self::normalizar(self::nombreCatalogo($root, $id))] = true;
            $out[] = $id;
            $cuantos--;
        }
        return $out;
    }
}
