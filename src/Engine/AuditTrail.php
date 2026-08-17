<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Historial técnico consultable para depuración. */
final class AuditTrail
{
    public static function record(
        array &$partida,
        string $tipo,
        array $actores = [],
        ?string $origen = null,
        ?string $regla = null,
        mixed $antes = null,
        mixed $despues = null,
        ?int $rngRoll = null,
        ?string $correlacionId = null
    ): void {
        if (!isset($partida['audit_trail']) || !is_array($partida['audit_trail'])) {
            $partida['audit_trail'] = [];
        }

        $entry = [
            'ts_real' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'ts_juego' => [
                'dia' => $partida['reloj']['dia_pueblo'] ?? null,
                'hora' => $partida['reloj']['hora_actual'] ?? null,
            ],
            'tipo' => $tipo,
            'actores' => $actores,
            'origen' => $origen,
            'regla' => $regla,
            'antes' => $antes,
            'despues' => $despues,
            'rng_roll' => $rngRoll,
            'correlacion_id' => $correlacionId,
        ];

        $partida['audit_trail'][] = $entry;
        if (count($partida['audit_trail']) > 500) {
            $partida['audit_trail'] = array_slice($partida['audit_trail'], -500);
        }
    }
}
