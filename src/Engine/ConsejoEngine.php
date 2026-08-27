<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Consejo de Celestine: inclina conducta futura. No toca barras. El NPC puede no seguirlo. */
final class ConsejoEngine
{
    /**
     * @return array<string, mixed>
     */
    public static function responder(
        array &$partida,
        string $residenteId,
        string $consejoId,
        ?string $objetivoId = null,
        ?string $tema = null
    ): array {
        $partida['inclinaciones_consejo'] ??= [];
        $entry = [
            'residente_id' => $residenteId,
            'consejo_id' => $consejoId,
            'objetivo_id' => $objetivoId,
            'tema' => $tema ?? 'romance',
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'efecto_barra' => false,
            'magnitud' => CalibracionConfig::get([], 'consejo.inclinacion', 10),
            'duracion' => CalibracionConfig::get([], 'consejo.duracion_dias', 5),
            'sigue_consejo' => null,
        ];
        $partida['inclinaciones_consejo'][] = $entry;

        // Registrar seguimiento (F9) si el buzón está activo
        if (FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            SeguimientoConsejoEngine::registrar($partida, $residenteId, $consejoId, $tema ?? 'romance');
        }

        return ['ok' => true, 'inclinacion' => $entry];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function activas(array $partida, string $residenteId, ?string $objetivoId = null): array
    {
        $out = [];
        foreach ($partida['inclinaciones_consejo'] ?? [] as $row) {
            if (($row['residente_id'] ?? '') !== $residenteId) {
                continue;
            }
            if ($objetivoId !== null && ($row['objetivo_id'] ?? null) !== $objetivoId) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }
}
