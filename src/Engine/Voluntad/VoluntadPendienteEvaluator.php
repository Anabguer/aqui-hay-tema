<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Voluntad;

use AquiHayTema\Engine\PropuestaEncuentro;

/**
 * No inventa aceptación. Deja la voluntad pendiente hasta decisión explícita o fórmula futura.
 */
final class VoluntadPendienteEvaluator implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_PENDIENTE,
            'clase' => null,
            'motivo_tecnico' => 'voluntad_sin_formula',
            'copy_id' => null,
            '_bloqueado_decision' => true,
        ];
    }
}
