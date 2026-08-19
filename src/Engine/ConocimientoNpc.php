<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Qué sabe un NPC de otro. Sin rumores ni información falsa.
 * Celestine (jugador) usa descubrimientos; esto es NPC→NPC.
 */
final class ConocimientoNpc
{
    public static function ensure(array &$partida): void
    {
        $partida['conocimiento_npc'] ??= [];
    }

    public static function campoHobby(string $id): string
    {
        return 'hobby:' . $id;
    }

    public static function campoRasgo(string $id): string
    {
        return 'rasgo:' . $id;
    }

    public static function campoRechazo(string $tipo, string $id): string
    {
        return 'rechazo_' . $tipo . ':' . $id;
    }

    public static function campoGusto(string $tipo, string $id): string
    {
        return 'gusto_' . $tipo . ':' . $id;
    }

    public static function sabe(array $partida, string $quien, string $de, string $campo): bool
    {
        if ($quien === $de) {
            return true;
        }
        $bag = $partida['conocimiento_npc'][$quien][$de]['campos'] ?? [];
        return !empty($bag[$campo]);
    }

    /**
     * @param list<string> $campos
     */
    public static function revelar(array &$partida, string $quien, string $de, array $campos, string $origen = 'evento'): int
    {
        if ($quien === $de || $campos === []) {
            return 0;
        }
        self::ensure($partida);
        $partida['conocimiento_npc'][$quien] ??= [];
        $partida['conocimiento_npc'][$quien][$de] ??= ['campos' => [], 'origenes' => []];
        $n = 0;
        foreach ($campos as $campo) {
            $campo = (string) $campo;
            if ($campo === '') {
                continue;
            }
            if (!empty($partida['conocimiento_npc'][$quien][$de]['campos'][$campo])) {
                continue;
            }
            $partida['conocimiento_npc'][$quien][$de]['campos'][$campo] = true;
            $partida['conocimiento_npc'][$quien][$de]['origenes'][$campo] = $origen;
            $n++;
        }
        return $n;
    }

    /**
     * Hobbies del objetivo que el observador ya ha descubierto.
     *
     * @return list<string>
     */
    public static function hobbiesConocidos(array $partida, string $quien, string $de): array
    {
        $perfil = PerfilPartida::de($partida, $de);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        $out = [];
        foreach ($hobbies as $h) {
            if (is_string($h) && $h !== '' && self::sabe($partida, $quien, $de, self::campoHobby($h))) {
                $out[] = $h;
            }
        }
        return $out;
    }

    /**
     * Rechazos de hobby/lugar conocidos.
     *
     * @return list<string>
     */
    public static function hobbiesRechazadosConocidos(array $partida, string $quien, string $de): array
    {
        $perfil = PerfilPartida::de($partida, $de);
        $neg = is_array($perfil['preferencias']['hobbies_neg'] ?? null) ? $perfil['preferencias']['hobbies_neg'] : [];
        $out = [];
        foreach ($neg as $h) {
            if (is_string($h) && $h !== '' && self::sabe($partida, $quien, $de, self::campoRechazo('hobby', $h))) {
                $out[] = $h;
            }
        }
        return $out;
    }
}
