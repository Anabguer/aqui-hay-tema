<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Bitácora de HITOS relacionales consultable por reglas.
 * No registra cada saludo. historial_relaciones sigue siendo el log técnico.
 */
final class RelacionBitacora
{
    public const SE_CONOCIERON = 'se_conocieron';
    public const PRIMERA_CITA = 'primera_cita';
    public const RECHAZO_IMPORTANTE = 'rechazo_importante';
    public const PLAN_SIGNIFICATIVO = 'plan_significativo';
    public const REGALO = 'regalo';
    public const DECLARACION = 'declaracion';
    public const INICIO_PAREJA = 'inicio_pareja';
    public const CRISIS = 'crisis';
    public const RECONCILIACION = 'reconciliacion';
    public const RUPTURA = 'ruptura';
    public const VUELTA = 'vuelta';
    public const DISCUSION_FUERTE = 'discusion_fuerte';
    public const APOYO_IMPORTANTE = 'apoyo_importante';
    public const HITO_ROMANTICO = 'hito_romantico';
    public const FLECHAZO = 'flechazo';

    public static function ensure(array &$partida): void
    {
        $partida['bitacora_relaciones'] ??= [];
    }

    /**
     * @param list<string> $participantes
     * @return array<string, mixed>
     */
    public static function registrar(
        array &$partida,
        string $tipo,
        array $participantes,
        ?string $direccion = null,
        $resultado = null,
        ?int $intensidad = null,
        array $meta = []
    ): array {
        self::ensure($partida);
        $reloj = $partida['reloj'] ?? [];
        $ids = array_values($participantes);
        sort($ids);
        $entry = [
            'id' => 'hito_' . count($partida['bitacora_relaciones']) . '_' . ($reloj['dia_pueblo'] ?? 0),
            'tipo' => $tipo,
            'fecha' => [
                'dia' => (int) ($reloj['dia_pueblo'] ?? 1),
                'hora' => (int) ($reloj['hora_actual'] ?? 0),
            ],
            'participantes' => array_values($participantes),
            'par' => $ids,
            'direccion' => $direccion,
            'resultado' => $resultado,
            'intensidad' => $intensidad,
            'meta' => $meta,
        ];
        $partida['bitacora_relaciones'][] = $entry;
        return $entry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function entre(array $partida, string $a, string $b, ?string $tipo = null): array
    {
        $ids = [$a, $b];
        sort($ids);
        $out = [];
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            if (($h['par'] ?? []) !== $ids) {
                continue;
            }
            if ($tipo !== null && ($h['tipo'] ?? '') !== $tipo) {
                continue;
            }
            $out[] = $h;
        }
        return $out;
    }

    public static function tienenHito(array $partida, string $a, string $b, string $tipo): bool
    {
        return self::entre($partida, $a, $b, $tipo) !== [];
    }

    public static function vecesPareja(array $partida, string $a, string $b): int
    {
        return count(self::entre($partida, $a, $b, self::INICIO_PAREJA))
            + count(self::entre($partida, $a, $b, self::VUELTA));
    }

    /** Familia de copy/evento: no es texto final. */
    public static function familiaCopy(array $partida, string $a, string $b): string
    {
        if (self::tienenHito($partida, $a, $b, self::RUPTURA) || self::tienenHito($partida, $a, $b, self::RECONCILIACION)) {
            return 'ex_reconexion';
        }
        if (self::tienenHito($partida, $a, $b, self::INICIO_PAREJA)) {
            return 'pareja';
        }
        if (self::tienenHito($partida, $a, $b, self::SE_CONOCIERON)) {
            return 'conocidos';
        }
        return 'desconocidos';
    }
}
