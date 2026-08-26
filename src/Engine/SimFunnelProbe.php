<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * PROBE de diagnostico (solo laboratorio): registra observaciones del embudo
 * romantico sin alterar probabilidades, orden de evaluacion ni consumo de RNG.
 * Inactivo salvo que partida['meta']['sim_funnel'] === true.
 */
final class SimFunnelProbe
{
    private const CAP_FILAS = 40000;
    public const CANALES = [
        'hueco',
        'elegir_evento',
        'salida_individual',
        'casual',
        'quedada_autonoma',
        'flechazo',
        'declaracion',
        'cooldown_gate',
        'senal',
        'encuentro_resuelto',
    ];

    public static function activo(array $partida): bool
    {
        return ($partida['meta']['sim_funnel'] ?? null) === true;
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $data
     */
    public static function on(array &$partida, string $canal, array $data): void
    {
        if (!self::activo($partida)) {
            return;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $row = array_merge(['d' => $dia, 'h' => $hora], $data);

        $partida['sim_funnel_counts'] ??= [];
        $partida['sim_funnel_counts'][$canal] ??= [];
        $clave = (string) ($data['_k'] ?? $data['ev'] ?? 'na');
        $partida['sim_funnel_counts'][$canal][$clave] = (int) ($partida['sim_funnel_counts'][$canal][$clave] ?? 0) + 1;

        if (!isset($data['_solo_conteo']) || !$data['_solo_conteo']) {
            $partida['sim_funnel_probe'] ??= [];
            $partida['sim_funnel_probe'][$canal] ??= [];
            if (count($partida['sim_funnel_probe'][$canal]) < self::CAP_FILAS) {
                unset($row['_k'], $row['_solo_conteo']);
                $partida['sim_funnel_probe'][$canal][] = $row;
            }
        }
    }
}
