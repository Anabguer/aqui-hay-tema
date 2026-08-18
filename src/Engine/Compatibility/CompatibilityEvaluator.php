<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Compatibility;

/** Contrato evaluador: social, romántico y contextual son canales separados. */
interface CompatibilityEvaluator
{
    /** @return array{tipo?: string, intensidad?: int, se_soportan?: bool, veto?: bool, motivo?: string} */
    public function evaluateSocial(array $partida, string $personaA, string $personaB, array $contexto = []): array;

    /**
     * Capa romántica. V1: no hay filtro de orientación (todos con todos si edad compatible).
     * Parentesco veto: sigue en catálogo, no se aplica aquí todavía.
     * @return array<string, mixed>
     */
    public function evaluateRomantic(array $partida, string $personaA, string $personaB, array $contexto = []): array;

    public function evaluateContextual(array $partida, string $personaA, string $personaB, array $contexto = []): array;
}
