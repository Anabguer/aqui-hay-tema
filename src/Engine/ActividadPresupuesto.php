<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Coordinador centralizado del presupuesto global de actividad autónoma/sistémica.
 *
 * Cada día el pueblo tiene un cupo limitado de acciones autónomas.
 * Las acciones del JUGADOR y el TUTORIAL nunca se bloquean.
 *
 * Canales que consumen presupuesto (por prioridad):
 *   1. Hueco de vida (eventos diarios pre-programados) — ALTA
 *   2. Iniciativa social (NPC→NPC deliberada) — MEDIA
 *   3. Salidas individuales (NPC solo a un lugar) — BAJA
 *
 * Protección anti-monopolio: los huecos tienen un tope configurable
 * (max_hueco_ratio) sobre el total del presupuesto. Si ya consumieron
 * esa fracción, se saltan para dejar cupo a otros canales.
 *
 * Canales que NO consumen presupuesto:
 *   - Tutorial (garantizado, pedagógico)
 *   - Propuestas del jugador/Celestine
 *   - Interacciones casuales (consecuencia de encuentros ya presupuestados)
 *   - Catch-up offline
 */
final class ActividadPresupuesto
{
    public const PRIORIDAD_ALTA = 3;
    public const PRIORIDAD_MEDIA = 2;
    public const PRIORIDAD_BAJA = 1;

    public const CANAL_HUECO_VIDA = 'hueco_vida';
    public const CANAL_INICIATIVA_SOCIAL = 'iniciativa_social';
    public const CANAL_SALIDA_INDIVIDUAL = 'salida_individual';
    public const CANAL_EVENTO_PUEBLO = 'evento_pueblo';

    /**
     * Inicializa el presupuesto del día en la partida.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     */
    public static function alComenzarDia(array &$partida, array $cal): array
    {
        $n = count($partida['residentes'] ?? []);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);

        $budget = self::calcularPresupuesto($n, $dia, $cal);

        $partida['presupuesto_actividad'] = [
            'dia' => $dia,
            'total' => $budget,
            'consumido' => 0,
            'por_canal' => [
                self::CANAL_HUECO_VIDA => 0,
                self::CANAL_INICIATIVA_SOCIAL => 0,
                self::CANAL_SALIDA_INDIVIDUAL => 0,
                self::CANAL_EVENTO_PUEBLO => 0,
            ],
            'bloqueados' => [],
        ];

        return $partida['presupuesto_actividad'];
    }

    /**
     * Calcula el presupuesto diario total.
     *
     * @param array<string, mixed> $cal
     */
    public static function calcularPresupuesto(int $nResidentes, int $dia, array $cal): int
    {
        $base = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.base', 1.5);
        $factor = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.factor_sqrt', 0.8);
        $min = (int) CalibracionConfig::get($cal, 'presupuesto_actividad.min', 1);
        $max = (int) CalibracionConfig::get($cal, 'presupuesto_actividad.max', 15);
        $dia1Factor = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.dia_1_factor', 0.5);

        $budget = (int) round($base + $factor * sqrt(max(1, $nResidentes)));
        $budget = max($min, min($max, $budget));

        // Día 1: reducir presupuesto para dar aire al jugador
        if ($dia === 1) {
            $budget = max($min, (int) round($budget * $dia1Factor));
        }

        return $budget;
    }

    /**
     * Verifica si un canal puede consumir presupuesto ahora mismo.
     * Para huecos de vida aplica anti-monopolio: si ya consumieron
     * demasiado del total, se bloquean.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     */
    public static function puedeCanal(array $partida, string $canal, array $cal = []): bool
    {
        $pres = $partida['presupuesto_actividad'] ?? null;
        if ($pres === null) {
            return true;
        }

        // Siempre verificar agotado primero
        if ($pres['total'] - $pres['consumido'] <= 0) {
            return false;
        }

        // Anti-monopolio huecos: si ya consumieron más del ratio máximo, bloquear
        if ($canal === self::CANAL_HUECO_VIDA) {
            $maxRatio = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.max_hueco_ratio', 0.6);
            $huecoMax = (int) ceil($pres['total'] * $maxRatio);
            $huecoActual = $pres['por_canal'][self::CANAL_HUECO_VIDA] ?? 0;
            if ($huecoActual >= $huecoMax) {
                return false;
            }
        }

        return true;
    }

    /**
     * Intenta consumir una unidad del presupuesto.
     * Retorna true si se concedió, false si se agotó.
     *
     * @param array<string, mixed> &$partida
     */
    public static function consumir(array &$partida, string $canal, int $prioridad): bool
    {
        $pres = &$partida['presupuesto_actividad'] ?? null;
        if ($pres === null) {
            return true; // Sin presupuesto activo → permitir (compatibilidad)
        }

        $restante = $pres['total'] - $pres['consumido'];
        if ($restante <= 0) {
            $pres['bloqueados'][] = [
                'canal' => $canal,
                'prioridad' => $prioridad,
                'dia' => $partida['reloj']['dia_pueblo'] ?? 0,
                'hora' => $partida['reloj']['hora_actual'] ?? 0,
            ];
            return false;
        }

        $pres['consumido']++;
        $pres['por_canal'][$canal] = ($pres['por_canal'][$canal] ?? 0) + 1;

        return true;
    }

    /**
     * Consulta cuánto presupuesto queda.
     *
     * @param array<string, mixed> $partida
     */
    public static function restante(array $partida): int
    {
        $pres = $partida['presupuesto_actividad'] ?? null;
        if ($pres === null) {
            return 999; // Sin presupuesto activo → sin límite
        }
        return max(0, $pres['total'] - $pres['consumido']);
    }

    /**
     * Retorna true si el presupuesto está agotado.
     *
     * @param array<string, mixed> $partida
     */
    public static function agotado(array $partida): bool
    {
        return self::restante($partida) <= 0;
    }

    /**
     * Vista para debug/export.
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function debug(array $partida): array
    {
        $pres = $partida['presupuesto_actividad'] ?? null;
        if ($pres === null) {
            return ['activo' => false];
        }
        return [
            'activo' => true,
            'dia' => $pres['dia'],
            'total' => $pres['total'],
            'consumido' => $pres['consumido'],
            'restante' => max(0, $pres['total'] - $pres['consumido']),
            'por_canal' => $pres['por_canal'],
            'bloqueados_count' => count($pres['bloqueados']),
            'bloqueados' => array_slice($pres['bloqueados'], -10),
        ];
    }
}
