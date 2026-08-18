<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Plan de catch-up offline. No genera eventos ni cantidades.
 * Prioridades confirmadas; probabilidades BLOQUEADO_DECISION.
 */
final class CatchUpPlanner
{
    public const PRIORIDADES = [
        'pequenas_novedades',
        'cambios_progresivos',
        'peticiones',
        'relaciones',
        'acontecimiento_importante',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function planificar(int $segundos): array
    {
        $diasCalendario = intdiv(max(0, $segundos), 86400);
        return [
            'segundos' => max(0, $segundos),
            'dias_calendario_aprox' => $diasCalendario,
            'ejecutado' => false,
            'prioridades' => self::PRIORIDADES,
            'cantidades' => null,
            'eventos_generados' => [],
            '_bloqueado_decision' => [
                'cantidades',
                'probabilidades',
                'umbral_acontecimiento_importante',
                'ritmo_3_dias_16_residentes',
            ],
            'nota' => 'Pueblo vivo offline: plan de prioridades. No simula telenovela ni deja el pueblo congelado.',
        ];
    }
}
