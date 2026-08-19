<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Compatibilidad real oculta al jugador. No hay porcentajes en play.
 * Escáner futuro: nombre y coste BLOQUEADO_DECISION.
 */
final class CompatibilidadOculta
{
    public static function parId(string $a, string $b): string
    {
        return $a < $b ? "cmp_{$a}_{$b}" : "cmp_{$b}_{$a}";
    }

    public static function ensure(array &$partida): void
    {
        $partida['compatibilidad_oculta'] ??= [
            'pares' => [],
            'escaner' => [
                'desbloqueado' => false,
                'desbloqueable' => false,
                'deprecated' => true,
                'nombre' => null,
                'coste' => null,
            ],
        ];
        $partida['compatibilidad_oculta']['pares'] ??= [];
        $partida['compatibilidad_oculta']['escaner'] ??= [
            'desbloqueado' => false,
            'desbloqueable' => false,
            'deprecated' => true,
            'nombre' => null,
            'coste' => null,
        ];
        $partida['compatibilidad_oculta']['escaner']['desbloqueado'] = false;
        $partida['compatibilidad_oculta']['escaner']['desbloqueable'] = false;
        $partida['compatibilidad_oculta']['escaner']['deprecated'] = true;
    }

    public static function ensurePar(array &$partida, string $a, string $b): array
    {
        self::ensure($partida);
        $id = self::parId($a, $b);
        if (!isset($partida['compatibilidad_oculta']['pares'][$id])) {
            $partida['compatibilidad_oculta']['pares'][$id] = [
                'id' => $id,
                'persona_a' => $a < $b ? $a : $b,
                'persona_b' => $a < $b ? $b : $a,
                'visible_jugador' => false,
                'canales' => [
                    'social' => null,
                    'romantico' => null,
                    'contextual' => null,
                ],
                '_placeholder_valores' => true,
                '_bloqueado_decision' => ['formula', 'escala'],
            ];
        }
        return $partida['compatibilidad_oculta']['pares'][$id];
    }

    public static function esVisibleJugador(array $partida, string $a, string $b): bool
    {
        self::ensure($partida);
        $id = self::parId($a, $b);
        $par = $partida['compatibilidad_oculta']['pares'][$id] ?? null;
        if (!is_array($par)) {
            return false;
        }
        return false;
    }

    /**
     * Persiste A→B y B→A si faltan. No recalcula si ya hay totales.
     *
     * @return array<string, mixed>
     */
    public static function asegurarDireccional(array &$partida, string $a, string $b, Catalog $catalog): array
    {
        $row = self::ensurePar($partida, $a, $b);
        if (isset($row['direccional']['a_hacia_b']['total'], $row['direccional']['b_hacia_a']['total'])) {
            return $row;
        }
        $cal = CalibracionConfig::load($catalog->getRoot());
        $pa = PerfilPartida::deOLegacy($partida, $a, $catalog);
        $pb = PerfilPartida::deOLegacy($partida, $b, $catalog);
        $ab = CompatibilidadCalculator::aHaciaB($pa, $pb, $cal);
        $ba = CompatibilidadCalculator::aHaciaB($pb, $pa, $cal);
        $id = self::parId($a, $b);
        $lo = $a < $b ? $a : $b;
        $hi = $a < $b ? $b : $a;
        $dirAb = $a === $lo ? $ab : $ba;
        $dirBa = $a === $lo ? $ba : $ab;
        $partida['compatibilidad_oculta']['pares'][$id]['direccional'] = [
            'a_hacia_b' => $dirAb,
            'b_hacia_a' => $dirBa,
        ];
        $partida['compatibilidad_oculta']['pares'][$id]['_placeholder_valores'] = false;
        $partida['compatibilidad_oculta']['pares'][$id]['visible_jugador'] = false;
        return $partida['compatibilidad_oculta']['pares'][$id];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function hacia(array $partida, string $desde, string $hacia): ?array
    {
        self::ensure($partida);
        $id = self::parId($desde, $hacia);
        $row = $partida['compatibilidad_oculta']['pares'][$id] ?? null;
        if (!is_array($row) || !isset($row['direccional'])) {
            return null;
        }
        $lo = $row['persona_a'] ?? '';
        if ($desde === $lo) {
            return $row['direccional']['a_hacia_b'] ?? null;
        }
        return $row['direccional']['b_hacia_a'] ?? null;
    }
}
