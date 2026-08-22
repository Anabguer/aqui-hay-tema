<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Elegibilidad romántica V1: todos-con-todos.
 * No filtra por género ni por orientación (`atraido_por`, `etiqueta_orientacion_visible`).
 * Química, compatibilidad y voluntad deciden probabilidades, no el sí/no de candidato.
 * Vetos duros: parentesco y límite de edad. Nada más.
 */
final class RomanceElegibilidad
{
    /**
     * @param array<string, mixed> $cal
     * @return array{ok: bool, motivo: ?string, edad: array<string, mixed>}
     */
    public static function par(array $partida, string $a, string $b, array $cal, ?Catalog $catalog = null): array
    {
        if (ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal)) {
            return [
                'ok' => false,
                'motivo' => 'parentesco_veto',
                'edad' => self::edad($partida, $a, $b, $cal, $catalog),
            ];
        }
        $edad = self::edad($partida, $a, $b, $cal, $catalog);
        if (!($edad['romance_elegible'] ?? true)) {
            return [
                'ok' => false,
                'motivo' => 'edad_limite_duro',
                'edad' => $edad,
            ];
        }
        return [
            'ok' => true,
            'motivo' => null,
            'edad' => $edad,
        ];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function edad(array $partida, string $a, string $b, array $cal, ?Catalog $catalog = null): array
    {
        if ($catalog !== null) {
            $pa = PerfilPartida::deOLegacy($partida, $a, $catalog);
            $pb = PerfilPartida::deOLegacy($partida, $b, $catalog);
        } else {
            $pa = PerfilPartida::de($partida, $a) ?? [];
            $pb = PerfilPartida::de($partida, $b) ?? [];
        }
        return EdadPolitica::clasificar(
            isset($pa['edad']) ? (int) $pa['edad'] : null,
            isset($pb['edad']) ? (int) $pb['edad'] : null,
            $cal
        );
    }
}
