<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Desgaste por abandono. No agresivo. Días/deltas null = no aplica nada.
 * No resta romance diario.
 */
final class RelacionDesgaste
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function alCerrarDia(array &$partida, array $cal): array
    {
        $dSocial = CalibracionConfig::get($cal, 'desgaste_social.delta_recien', null);
        $dPareja = CalibracionConfig::get($cal, 'desgaste_pareja.delta_estabilidad_recien', null);
        $nSocial = 0;
        $nPareja = 0;
        if ($dSocial !== null) {
            $nSocial = -1;
        }
        if ($dPareja !== null && CalibracionConfig::get($cal, 'desgaste_pareja.no_restar_romance_diario', true)) {
            $nPareja = -1;
        }
        return [
            'ok' => true,
            'aplicado' => false,
            'motivo' => ($dSocial === null && $dPareja === null) ? 'deltas_no_calibrados' : 'pendiente_cifras',
            'social_tocadas' => $nSocial === -1 ? 0 : 0,
            'parejas_tocadas' => 0,
            '_bloqueado_decision' => ['dias', 'deltas'],
        ];
    }

    public static function horasSinContacto(array $partida, array $rel): ?int
    {
        $uc = $rel['ultimo_contacto_significativo'] ?? null;
        if (!is_array($uc)) {
            return null;
        }
        $now = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        $then = ((int) ($uc['dia'] ?? 0)) * 24 + (int) ($uc['hora'] ?? 0);
        return $now - $then;
    }
}
