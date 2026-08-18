<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Voluntad;

/**
 * Evalúa si un residente QUIERE el encuentro. Distinto de agenda (puede/no puede).
 * La fórmula de pesos está BLOQUEADO_DECISION.
 */
interface VoluntadEvaluator
{
    /**
     * @return array{
     *   decision: string,
     *   clase: string|null,
     *   motivo_tecnico: string,
     *   copy_id: string|null,
     *   _bloqueado_decision: bool
     * }
     */
    public function evaluar(array $partida, array $propuesta, string $residenteId): array;
}
