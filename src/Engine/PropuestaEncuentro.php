<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Contrato de propuesta de encuentro. El jugador propone; el residente decide.
 * No contiene fórmula de voluntad.
 */
final class PropuestaEncuentro
{
    public const ESTADOS = ['propuesta', 'aceptada', 'rechazada', 'programada', 'caducada'];
    public const CLASE_INDISPONIBILIDAD = 'indisponibilidad';
    public const CLASE_VOLUNTAD = 'voluntad';
    public const DECISION_PENDIENTE = 'pendiente';
    public const DECISION_ACEPTA = 'acepta';
    public const DECISION_RECHAZA = 'rechaza';

    public static function transicionValida(string $desde, string $hacia): bool
    {
        if ($desde === $hacia) {
            return true;
        }
        if ($desde === 'propuesta') {
            return in_array($hacia, ['aceptada', 'rechazada', 'caducada'], true);
        }
        if ($desde === 'aceptada') {
            return in_array($hacia, ['programada', 'caducada', 'rechazada'], true);
        }
        return false;
    }
}
