<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Compatibility;

/** Placeholder: deltas fijos dev. Veto romántico NO bloquea amistad/conocerse. */
final class PlaceholderEvaluator implements CompatibilityEvaluator
{
    public function evaluateSocial(array $partida, string $personaA, string $personaB, array $contexto = []): array
    {
        $tipoEnc = $contexto['tipo_encuentro'] ?? 'conocerse';
        return [
            '_placeholder' => true,
            'tipo' => match ($tipoEnc) {
                'amistad' => 'amistad',
                'conflicto' => 'roce',
                default => 'conocidos',
            },
            'intensidad' => 1,
            'se_soportan' => $tipoEnc !== 'conflicto',
        ];
    }

    public function evaluateRomantic(array $partida, string $personaA, string $personaB, array $contexto = []): array
    {
        if (($contexto['tipo_encuentro'] ?? '') !== 'romantico') {
            return ['_placeholder' => true, 'aplicado' => false];
        }
        return [
            '_placeholder' => true,
            'atraccion_a_hacia_b' => 1,
            'atraccion_b_hacia_a' => 1,
            'vinculo' => 0,
        ];
    }

    public function evaluateContextual(array $partida, string $personaA, string $personaB, array $contexto = []): array
    {
        return ['_placeholder' => true, 'sesgos' => []];
    }
}
