<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Interés romántico por un tercero estando en pareja: raro, nunca imposible. */
final class TerceroRomantico
{
    /**
     * @param array<string, mixed> $cal
     */
    public static function multiplicador(array $partida, string $desde, string $hacia, array $cal): float
    {
        $parejaDe = self::parejaDe($partida, $desde);
        if ($parejaDe === null || $parejaDe === $hacia) {
            return 1.0;
        }
        $est = ParejaEngine::estado($partida, $desde, $parejaDe);
        if ($est === ParejaEngine::CRISIS) {
            return (float) CalibracionConfig::get($cal, 'terceros.freno_pareja_crisis', 0.35);
        }
        if ($est === ParejaEngine::PAREJA) {
            return (float) CalibracionConfig::get($cal, 'terceros.freno_pareja_feliz', 0.08);
        }
        return 1.0;
    }

    public static function parejaDe(array $partida, string $id): ?string
    {
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $est = (string) ($rel['estado_pareja'] ?? '');
            if ($est !== ParejaEngine::PAREJA && $est !== ParejaEngine::CRISIS) {
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a === $id) {
                return $b;
            }
            if ($b === $id) {
                return $a;
            }
        }
        return null;
    }
}
