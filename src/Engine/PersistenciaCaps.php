<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PersistenciaCaps
{
    public static function defaults(string $projectRoot): array
    {
        $path = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/configs/persistencia.json';
        if (!is_file($path)) {
            return [
                'audit_trail_cap' => 200,
                'domain_events_cap' => 200,
                'descubrimientos_cap' => 400,
                'historial_relaciones_cap' => 2000,
                'event_log_cap' => 100,
                'historial_coincidencias_cap' => 500,
                'memoria_eventos_cap' => 500,
                'archivar_al_recortar' => true,
            ];
        }
        return JsonFile::read($path);
    }

    public static function mergeIntoPartida(array &$partida, string $projectRoot): void
    {
        $partida['persistencia'] = array_merge(
            self::defaults($projectRoot),
            is_array($partida['persistencia'] ?? null) ? $partida['persistencia'] : []
        );
    }

    public static function cap(array $partida, string $key, int $fallback): int
    {
        return (int) ($partida['persistencia'][$key] ?? $fallback);
    }

    public static function recortarLista(array &$partida, string $campo, int $cap, string $archivoCampo): void
    {
        if (!isset($partida[$campo]) || !is_array($partida[$campo])) {
            return;
        }
        $n = count($partida[$campo]);
        if ($n <= $cap) {
            return;
        }
        $recortar = $n - $cap;
        $caidos = array_slice($partida[$campo], 0, $recortar);

        if ($partida['persistencia']['archivar_al_recortar'] ?? true) {
            $partida[$archivoCampo] ??= [];
            $porTipo = [];
            foreach ($caidos as $e) {
                $t = (string) ($e['tipo'] ?? $e['evento'] ?? $e['evento_origen'] ?? 'otro');
                $porTipo[$t] = ($porTipo[$t] ?? 0) + 1;
            }
            $partida[$archivoCampo][] = [
                'count' => count($caidos),
                'por_tipo' => $porTipo,
                'ts_juego_desde' => $caidos[0]['ts_juego'] ?? null,
                'ts_juego_hasta' => $caidos[array_key_last($caidos)]['ts_juego'] ?? null,
            ];
            if (count($partida[$archivoCampo]) > 50) {
                $partida[$archivoCampo] = array_slice($partida[$archivoCampo], -50);
            }
        }

        $partida[$campo] = array_values(array_slice($partida[$campo], -$cap));
    }

    /**
     * Recorta historial de relaciones conservando al menos la última entrada de cada par.
     */
    public static function recortarHistorialRelaciones(array &$partida, int $cap): void
    {
        $lista = $partida['historial_relaciones'] ?? [];
        if (count($lista) <= $cap) {
            return;
        }

        $ultimaPorPar = [];
        foreach ($lista as $i => $e) {
            $k = ($e['persona_a'] ?? '') . '|' . ($e['persona_b'] ?? '');
            $ultimaPorPar[$k] = $i;
        }
        $proteger = array_flip(array_values($ultimaPorPar));

        $mantener = [];
        $caidos = 0;
        $start = count($lista) - $cap;
        foreach ($lista as $i => $e) {
            if ($i >= $start || isset($proteger[$i])) {
                $mantener[] = $e;
            } else {
                $caidos++;
            }
        }
        if ($caidos > 0 && ($partida['persistencia']['archivar_al_recortar'] ?? true)) {
            $partida['historial_relaciones_archivo'] ??= [];
            $partida['historial_relaciones_archivo'][] = ['count' => $caidos];
        }
        $partida['historial_relaciones'] = $mantener;
        if (count($partida['historial_relaciones']) > $cap + count($ultimaPorPar)) {
            $partida['historial_relaciones'] = array_slice($partida['historial_relaciones'], -($cap));
        }
    }

    public static function aplicar(array &$partida): void
    {
        self::recortarLista($partida, 'audit_trail', self::cap($partida, 'audit_trail_cap', 200), 'audit_trail_archivo');
        self::recortarLista($partida, 'domain_events', self::cap($partida, 'domain_events_cap', 200), 'domain_events_archivo');
        self::recortarLista($partida, 'descubrimientos', self::cap($partida, 'descubrimientos_cap', 400), 'descubrimientos_archivo');
        self::recortarLista($partida, 'event_log', self::cap($partida, 'event_log_cap', 100), 'event_log_archivo');
        self::recortarHistorialRelaciones($partida, self::cap($partida, 'historial_relaciones_cap', 2000));
        self::recortarLista($partida, 'historial_coincidencias', self::cap($partida, 'historial_coincidencias_cap', 500), 'historial_coincidencias_archivo');
        MemoriaEventos::compactar($partida, self::cap($partida, 'memoria_eventos_cap', 500));
    }
}
