<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Pareja, crisis, ruptura y reconciliación SOLO por hito explícito.
 * romance > X o estabilidad < X NUNCA cambian el estado.
 */
final class ParejaEngine
{
    public const NINGUNA = 'ninguna';
    public const PAREJA = 'pareja';
    public const CRISIS = 'crisis';
    public const EX = 'ex';

    /**
     * @return array<string, mixed>
     */
    public static function formar(
        array &$partida,
        string $a,
        string $b,
        bool $aceptaA,
        bool $aceptaB,
        string $hito = RelacionBitacora::DECLARACION,
        array $cal = []
    ): array {
        if (ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal)) {
            return GameError::respuesta(GameError::VALIDACION_FALLIDA, [
                'motivo' => 'parentesco_veto',
                'tipo' => ParentescoVeto::motivo($partida, $a, $b, $cal),
            ]);
        }
        if (!$aceptaA || !$aceptaB) {
            RelacionBitacora::registrar($partida, RelacionBitacora::DECLARACION, [$a, $b], null, [
                'acepta_a' => $aceptaA,
                'acepta_b' => $aceptaB,
            ]);
            return ['ok' => false, 'error' => 'hito_sin_ambos_si', 'estado_pareja' => self::estado($partida, $a, $b)];
        }
        $rel = self::ensureRomance($partida, $a, $b);
        $antes = (string) ($rel['estado_pareja'] ?? self::NINGUNA);
        $esVuelta = $antes === self::EX || RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::RUPTURA);
        $rel['estado_pareja'] = self::PAREJA;
        $rel['estabilidad_pareja']['activa'] = true;
        $rel['estabilidad_pareja']['valor'] = $esVuelta ? null : CalibracionConfig::get($cal, 'pareja.estabilidad_inicial', null);
        $rel['estabilidad_pareja']['base_reconciliacion'] = $esVuelta
            ? CalibracionConfig::get($cal, 'pareja.base_reconciliacion', null)
            : null;
        $rel['fecha_inicio'] = [
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
        ];
        $rel['historial_parejas'][] = [
            'inicio' => $rel['fecha_inicio'],
            'fin' => null,
            'como_acabo' => null,
            'vuelta' => $esVuelta,
        ];
        self::persistRomance($partida, $rel);
        RelacionBitacora::registrar(
            $partida,
            $esVuelta ? RelacionBitacora::VUELTA : RelacionBitacora::INICIO_PAREJA,
            [$a, $b],
            null,
            ['hito' => $hito]
        );
        return ['ok' => true, 'relacion' => $rel, 'vuelta' => $esVuelta];
    }

    /**
     * @return array<string, mixed>
     */
    public static function crisis(array &$partida, string $a, string $b): array
    {
        $est = self::estado($partida, $a, $b);
        if ($est !== self::PAREJA && $est !== self::CRISIS) {
            return ['ok' => false, 'error' => 'no_son_pareja'];
        }
        $rel = self::ensureRomance($partida, $a, $b);
        $rel['estado_pareja'] = self::CRISIS;
        self::persistRomance($partida, $rel);
        RelacionBitacora::registrar($partida, RelacionBitacora::CRISIS, [$a, $b]);
        return ['ok' => true, 'relacion' => $rel];
    }

    /**
     * @return array<string, mixed>
     */
    public static function romper(array &$partida, string $a, string $b, ?string $comoAcabo = null): array
    {
        $est = self::estado($partida, $a, $b);
        if ($est !== self::PAREJA && $est !== self::CRISIS) {
            return ['ok' => false, 'error' => 'no_son_pareja'];
        }
        $rel = self::ensureRomance($partida, $a, $b);
        $mem = $rel['estabilidad_pareja']['valor'] ?? null;
        $rel['estado_pareja'] = self::EX;
        $rel['estabilidad_pareja']['activa'] = false;
        $rel['estabilidad_pareja']['memoria'] = $mem;
        $rel['estabilidad_pareja']['valor'] = null;
        // Tras la ruptura el vínculo es EX: el romance direccional debe
        // resetearse a 0 o el mapa funcional mostraría "ex" y "romance vivo"
        // a la vez (contradicción). Se resetea en ambas direcciones.
        $rel['romance_a_hacia_b'] = 0;
        $rel['romance_b_hacia_a'] = 0;
        $rel['atraccion_a_hacia_b'] = 0;
        $rel['atraccion_b_hacia_a'] = 0;
        $n = count($rel['historial_parejas']);
        if ($n > 0) {
            $rel['historial_parejas'][$n - 1]['fin'] = [
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            ];
            $rel['historial_parejas'][$n - 1]['como_acabo'] = $comoAcabo;
        }
        self::persistRomance($partida, $rel);
        RelacionBitacora::registrar($partida, RelacionBitacora::RUPTURA, [$a, $b], null, $comoAcabo);
        return ['ok' => true, 'relacion' => $rel];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reconciliar(
        array &$partida,
        string $a,
        string $b,
        bool $aceptaA,
        bool $aceptaB,
        array $cal = []
    ): array {
        if (!RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::RUPTURA)
            && self::estado($partida, $a, $b) !== self::EX) {
            return ['ok' => false, 'error' => 'no_fueron_pareja'];
        }
        if (self::estado($partida, $a, $b) === self::PAREJA || self::estado($partida, $a, $b) === self::CRISIS) {
            return ['ok' => false, 'error' => 'ya_son_pareja'];
        }
        RelacionBitacora::registrar($partida, RelacionBitacora::RECONCILIACION, [$a, $b]);
        return self::formar($partida, $a, $b, $aceptaA, $aceptaB, RelacionBitacora::RECONCILIACION, $cal);
    }

    public static function estado(array $partida, string $a, string $b): string
    {
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'] ?? null;
        if (!is_array($rel)) {
            return self::NINGUNA;
        }
        $est = (string) ($rel['estado_pareja'] ?? self::NINGUNA);
        return $est !== '' ? $est : self::NINGUNA;
    }

    /**
     * @return array<string, mixed>
     */
    public static function ensureRomance(array &$partida, string $a, string $b): array
    {
        RelacionEngine::upsertRomance($partida, $a, $b, []);
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
        RelacionEngine::ensureRomanceCampos($rel);
        RelacionEngine::persistirRomance($partida, $rel);
        return $rel;
    }

    private static function persistRomance(array &$partida, array $rel): void
    {
        RelacionEngine::persistirRomance($partida, $rel);
    }
}
