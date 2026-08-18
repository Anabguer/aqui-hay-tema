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
                'nombre' => null,
                'coste' => null,
                '_bloqueado_decision' => ['nombre_ui', 'coste'],
            ],
        ];
        $partida['compatibilidad_oculta']['pares'] ??= [];
        $partida['compatibilidad_oculta']['escaner'] ??= [
            'desbloqueado' => false,
            'nombre' => null,
            'coste' => null,
            '_bloqueado_decision' => ['nombre_ui', 'coste'],
        ];
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
        if (!empty($par['visible_jugador'])) {
            return true;
        }
        return !empty($partida['compatibilidad_oculta']['escaner']['desbloqueado']);
    }
}
