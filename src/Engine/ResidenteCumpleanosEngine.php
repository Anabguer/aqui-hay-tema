<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Cumpleaños mínimo de residente (§19.6 / F10).
 *
 * Contrato persistente: identidad_publica.cumpleanos = { dia: 1-28, mes: 1-12 }.
 * Sin año de nacimiento. Asignación lazy y determinista para saves antiguos.
 */
final class ResidenteCumpleanosEngine
{
    /**
     * @return array{dia: int, mes: int}|null
     */
    public static function obtener(array &$partida, string $residenteId, ?Catalog $catalog = null): ?array
    {
        if (!isset($partida['residentes'][$residenteId]) || !is_array($partida['residentes'][$residenteId])) {
            return null;
        }
        $res = &$partida['residentes'][$residenteId];
        if (!isset($res['identidad_publica']) || !is_array($res['identidad_publica'])) {
            $res['identidad_publica'] = [];
        }
        $actual = $res['identidad_publica']['cumpleanos'] ?? null;
        if (is_array($actual) && self::esValido($actual)) {
            return ['dia' => (int) $actual['dia'], 'mes' => (int) $actual['mes']];
        }
        $desdeCatalogo = self::desdeCatalogo($res, $catalog);
        if ($desdeCatalogo !== null) {
            $res['identidad_publica']['cumpleanos'] = $desdeCatalogo;
            return $desdeCatalogo;
        }
        $generado = self::generarDeterminista($partida, $residenteId);
        $res['identidad_publica']['cumpleanos'] = $generado;
        return $generado;
    }

    public static function esCumpleanosHoy(array &$partida, string $residenteId, ?Catalog $catalog = null): bool
    {
        $c = self::obtener($partida, $residenteId, $catalog);
        if ($c === null) {
            return false;
        }
        $diaPueblo = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $fecha = Reloj::fechaDeDia($partida['reloj'] ?? [], $diaPueblo);
        return (int) $fecha->format('n') === $c['mes'] && (int) $fecha->format('j') === $c['dia'];
    }

    /**
     * Clave anual de dedup: cumpleanero + año calendario del pueblo.
     */
    public static function claveAnual(array $partida, string $cumpleaneroId): string
    {
        $diaPueblo = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $fecha = Reloj::fechaDeDia($partida['reloj'] ?? [], $diaPueblo);
        return 'cumple_' . $cumpleaneroId . '_' . $fecha->format('Y');
    }

    /**
     * @param array<string, mixed> $residente
     * @return array{dia: int, mes: int}|null
     */
    private static function desdeCatalogo(array $residente, ?Catalog $catalog): ?array
    {
        if ($catalog === null) {
            return null;
        }
        $cat = ResidenteRuntime::catalogoParaRuntime($residente, $catalog);
        if ($cat === null) {
            return null;
        }
        $raw = $cat['identidad']['cumpleanos'] ?? $cat['vida']['cumpleanos'] ?? null;
        if (!is_array($raw) || !self::esValido($raw)) {
            return null;
        }
        return ['dia' => (int) $raw['dia'], 'mes' => (int) $raw['mes']];
    }

    /**
     * @return array{dia: int, mes: int}
     */
    private static function generarDeterminista(array $partida, string $residenteId): array
    {
        $seed = (string) ($partida['meta']['seed'] ?? $partida['rng']['seed'] ?? 'aht');
        $h = (int) sprintf('%u', crc32($seed . '|cumpleanos|' . $residenteId));
        $mes = ($h % 12) + 1;
        $dia = (($h >> 8) % 28) + 1;
        return ['dia' => $dia, 'mes' => $mes];
    }

    /**
     * @param array<string, mixed> $c
     */
    private static function esValido(array $c): bool
    {
        $dia = (int) ($c['dia'] ?? 0);
        $mes = (int) ($c['mes'] ?? 0);
        return $dia >= 1 && $dia <= 28 && $mes >= 1 && $mes <= 12;
    }
}
