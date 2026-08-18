<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Historial técnico de evolución entre dos residentes. */
final class RelacionHistorial
{
    public static function ensure(array &$partida): void
    {
        $partida['historial_relaciones'] ??= [];
    }

    public static function registrar(
        array &$partida,
        string $personaA,
        string $personaB,
        string $canal,
        string $eventoOrigen,
        array $deltas = [],
        ?array $antes = null,
        ?array $despues = null,
        ?string $correlacionId = null
    ): array {
        self::ensure($partida);
        [$a, $b] = $personaA < $personaB ? [$personaA, $personaB] : [$personaB, $personaA];
        $entry = [
            'id' => 'relh_' . bin2hex(random_bytes(4)),
            'persona_a' => $a,
            'persona_b' => $b,
            'canal' => $canal,
            'evento_origen' => $eventoOrigen,
            'deltas' => $deltas,
            'antes' => $antes,
            'despues' => $despues,
            'correlacion_id' => $correlacionId,
            'ts_juego' => [
                'dia' => $partida['reloj']['dia_pueblo'] ?? null,
                'hora' => $partida['reloj']['hora_actual'] ?? null,
            ],
        ];
        $partida['historial_relaciones'][] = $entry;
        PersistenciaCaps::recortarHistorialRelaciones(
            $partida,
            PersistenciaCaps::cap($partida, 'historial_relaciones_cap', 2000)
        );
        return $entry;
    }

    public static function listarEntre(array $partida, string $personaA, string $personaB): array
    {
        [$a, $b] = $personaA < $personaB ? [$personaA, $personaB] : [$personaB, $personaA];
        return array_values(array_filter(
            $partida['historial_relaciones'] ?? [],
            static fn(array $e) => ($e['persona_a'] ?? '') === $a && ($e['persona_b'] ?? '') === $b
        ));
    }
}
