<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Fases de una relación consolidable. Nombres internos, no UI final.
 * Umbrales de paso BLOQUEADO_DECISION: no se auto-aplican.
 */
final class RelacionFase
{
    public const ESTABLE = 'estable';
    public const TENSION = 'tension';
    public const CRISIS = 'crisis';
    public const POSIBLE_RUPTURA = 'posible_ruptura';

    public const FASES = [
        self::ESTABLE,
        self::TENSION,
        self::CRISIS,
        self::POSIBLE_RUPTURA,
    ];

    public static function ensure(array &$rel): void
    {
        if (!array_key_exists('fase', $rel)) {
            $rel['fase'] = null;
        }
        if (!array_key_exists('estabilidad_acumulada', $rel)) {
            $rel['estabilidad_acumulada'] = null;
        }
        $rel['_fase_nombres_no_ui'] = true;
    }

    public static function transicionValida(?string $desde, string $hacia): bool
    {
        if (!in_array($hacia, self::FASES, true)) {
            return false;
        }
        if ($desde === $hacia) {
            return true;
        }
        if ($desde === null) {
            return $hacia === self::ESTABLE;
        }
        if ($desde === self::ESTABLE) {
            return $hacia === self::TENSION;
        }
        if ($desde === self::TENSION) {
            return in_array($hacia, [self::ESTABLE, self::CRISIS], true);
        }
        if ($desde === self::CRISIS) {
            return in_array($hacia, [self::TENSION, self::POSIBLE_RUPTURA], true);
        }
        if ($desde === self::POSIBLE_RUPTURA) {
            return $hacia === self::CRISIS;
        }
        return false;
    }

    /**
     * Aplica fase solo si la transición es válida. No calcula umbrales.
     *
     * @return array{ok: bool, relacion?: array, error?: string, desde?: string|null, hacia?: string}
     */
    public static function aplicar(array &$rel, string $hacia): array
    {
        self::ensure($rel);
        $desde = $rel['fase'] ?? null;
        $desdeStr = is_string($desde) ? $desde : null;
        if (!self::transicionValida($desdeStr, $hacia)) {
            return ['ok' => false, 'error' => 'transicion_fase_invalida', 'desde' => $desdeStr, 'hacia' => $hacia];
        }
        $rel['fase'] = $hacia;
        return ['ok' => true, 'relacion' => $rel];
    }
}
