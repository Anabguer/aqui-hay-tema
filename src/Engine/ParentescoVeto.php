<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Veto romántico duro por parentesco cercano.
 * La química alta NO lo atraviesa. Tipos tio/primo: BLOQUEADO_DECISION.
 */
final class ParentescoVeto
{
    /**
     * @param array<string, mixed> $cal
     * @return list<string>
     */
    public static function tiposVeto(array $cal): array
    {
        $tipos = CalibracionConfig::get($cal, 'parentesco.veto_romance_tipos', []);
        return is_array($tipos) ? array_values($tipos) : [];
    }

    public static function bloqueaRomance(array $partida, string $a, string $b, array $cal): bool
    {
        return self::motivo($partida, $a, $b, $cal) !== null;
    }

    public static function motivo(array $partida, string $a, string $b, array $cal): ?string
    {
        $tipos = self::tiposVeto($cal);
        $tioPrimo = CalibracionConfig::get($cal, 'parentesco.tio_primo_veto', null);
        if ($tioPrimo === true || $tioPrimo === null) {
            $tipos = array_merge($tipos, ['tio', 'tia', 'sobrino', 'sobrina', 'primo_hermano', 'prima_hermana']);
        }
        foreach ($partida['parentesco'] ?? [] as $lazo) {
            if (!is_array($lazo)) {
                continue;
            }
            $pa = (string) ($lazo['persona_a'] ?? $lazo['de'] ?? '');
            $pb = (string) ($lazo['persona_b'] ?? $lazo['a'] ?? $lazo['persona'] ?? '');
            $tipo = (string) ($lazo['tipo'] ?? '');
            if (!self::mismoPar($pa, $pb, $a, $b)) {
                continue;
            }
            if (in_array($tipo, $tipos, true)) {
                return $tipo;
            }
        }
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['social'] ?? null;
        if (is_array($rel) && !empty($rel['veta_romance']) && !empty($rel['es_familiar'])) {
            return 'veta_romance';
        }
        return null;
    }

    private static function mismoPar(string $pa, string $pb, string $a, string $b): bool
    {
        return ($pa === $a && $pb === $b) || ($pa === $b && $pb === $a);
    }
}
