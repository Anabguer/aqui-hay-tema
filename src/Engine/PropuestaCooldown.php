<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Impide spam de la misma propuesta A→B durante unas horas. */
final class PropuestaCooldown
{
    public static function ensure(array &$partida): void
    {
        $partida['propuestas_cooldown'] ??= [];
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function marcar(array &$partida, string $desde, string $hacia, string $tipo, array $cal = []): void
    {
        if ($desde === '' || $hacia === '') {
            return;
        }
        self::ensure($partida);
        $horas = (int) CalibracionConfig::get($cal, 'rechazos.cooldown_horas', 6);
        $now = self::abs($partida);
        $partida['propuestas_cooldown'][] = [
            'desde' => $desde,
            'hacia' => $hacia,
            'tipo' => $tipo,
            'hasta_abs' => $now + max(1, $horas),
        ];
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function activo(array $partida, string $desde, string $hacia, string $tipo, array $cal = []): bool
    {
        $now = self::abs($partida);
        foreach ($partida['propuestas_cooldown'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['desde'] ?? '') !== $desde || ($row['hacia'] ?? '') !== $hacia) {
                continue;
            }
            if (($row['tipo'] ?? '') !== $tipo) {
                continue;
            }
            if ((int) ($row['hasta_abs'] ?? 0) > $now) {
                return true;
            }
        }
        return false;
    }

    private static function abs(array $partida): int
    {
        return ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
    }
}
