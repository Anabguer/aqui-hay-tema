<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Desgaste natural hacia 0. Nunca cruza 0. Vínculo fuerte se desgasta más lento.
 * Negativo no sana solo: hace falta algo bueno.
 */
final class RelacionDesgaste
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function alCerrarDia(array &$partida, array $cal): array
    {
        $activo = (bool) CalibracionConfig::get($cal, 'desgaste_social.activo', false);
        $nSocial = 0;
        $nPareja = 0;
        if ($activo) {
            $nSocial = self::aplicarSocial($partida, $cal);
        }
        if ((bool) CalibracionConfig::get($cal, 'desgaste_pareja.activo', false)) {
            $nPareja = self::aplicarPareja($partida, $cal);
        }
        return [
            'ok' => true,
            'aplicado' => $nSocial > 0 || $nPareja > 0,
            'motivo' => $activo ? 'formula_central' : 'inactivo',
            'social_tocadas' => $nSocial,
            'parejas_tocadas' => $nPareja,
        ];
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function aplicarSocial(array &$partida, array $cal): int
    {
        $horasMin = (int) CalibracionConfig::get($cal, 'desgaste_social.horas_sin_contacto', 24);
        $exp = (float) CalibracionConfig::get($cal, 'desgaste_social.exponente', 1.4);
        $basePorNivel = CalibracionConfig::get($cal, 'desgaste_social.base_por_nivel', [0.8, 0.4, 0.2, 0.1]);
        if (!is_array($basePorNivel) || count($basePorNivel) < 4) {
            $basePorNivel = [0.8, 0.4, 0.2, 0.1];
        }
        $n = 0;
        foreach ($partida['relaciones_sociales'] ?? [] as $i => $rel) {
            if (!is_array($rel) || empty($rel['conocidos'])) {
                continue;
            }
            RelacionEngine::ensureSocialCampos($rel);
            $horas = self::horasSinContacto($partida, $rel);
            if ($horas === null || $horas < $horasMin) {
                $partida['relaciones_sociales'][$i] = $rel;
                continue;
            }
            $cons = $rel['consolidacion'] ?? ['nivel' => 0, 'activa' => false];
            $nivel = $cons['activa'] === true ? (int) ($cons['nivel'] ?? 0) : 0;
            $base = (float) ($basePorNivel[$nivel] ?? $basePorNivel[0]);
            $cambio = false;
            foreach (['a_hacia_b', 'b_hacia_a'] as $key) {
                $v = (int) ($rel[$key]['valor'] ?? 0);
                if ($v <= 0) {
                    continue;
                }
                $rate = $base * pow(1.0 - ($v / 100.0), $exp);
                $resto = (float) ($rel[$key]['desgaste_resto'] ?? 0) + $rate;
                $enteros = (int) floor($resto);
                $rel[$key]['desgaste_resto'] = $resto - $enteros;
                if ($enteros <= 0) {
                    continue;
                }
                $nuevo = $v - $enteros;
                if ($nuevo < 0) {
                    $nuevo = 0;
                }
                if ($nuevo !== $v) {
                    $rel[$key]['valor'] = $nuevo;
                    $rel[$key]['banda'] = RelacionBandas::social($nuevo, true, $cal);
                    $cambio = true;
                    $n++;
                }
            }
            if ($cambio) {
                $rel['intensidad'] = (int) round((($rel['a_hacia_b']['valor'] ?? 0) + ($rel['b_hacia_a']['valor'] ?? 0)) / 2);
            }
            $partida['relaciones_sociales'][$i] = $rel;
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function aplicarPareja(array &$partida, array $cal): int
    {
        if (!(bool) CalibracionConfig::get($cal, 'desgaste_pareja.no_restar_romance_diario', true)) {
            return 0;
        }
        $n = 0;
        $delta = (float) CalibracionConfig::get($cal, 'desgaste_pareja.delta_estabilidad_recien', 2.0);
        $diasMin = (int) CalibracionConfig::get($cal, 'desgaste_pareja.dias_sin_interaccion_nada', 2);
        foreach ($partida['relaciones_romanticas'] ?? [] as $i => $rel) {
            if (!is_array($rel) || empty($rel['estabilidad_pareja']['activa'])) {
                continue;
            }
            $v = $rel['estabilidad_pareja']['valor'] ?? null;
            if (!is_numeric($v)) {
                continue;
            }
            $ini = $rel['fecha_inicio']['dia'] ?? null;
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            $soc = RelacionEngine::obtenerEntre($partida, (string) $rel['persona_a'], (string) $rel['persona_b'])['social'] ?? null;
            $uc = is_array($soc) ? ($soc['ultimo_contacto']['dia'] ?? $soc['ultimo_contacto_significativo']['dia'] ?? null) : null;
            $ref = $uc ?? $ini;
            if ($ref === null || ($dia - (int) $ref) < $diasMin) {
                continue;
            }
            $nv = (int) round((float) $v - $delta);
            if ($nv < 0) {
                $nv = 0;
            }
            $rel['estabilidad_pareja']['valor'] = $nv;
            $partida['relaciones_romanticas'][$i] = $rel;
            $n++;
        }
        return $n;
    }

    public static function horasSinContacto(array $partida, array $rel): ?int
    {
        $uc = $rel['ultimo_contacto'] ?? $rel['ultimo_contacto_significativo'] ?? null;
        if (!is_array($uc)) {
            return null;
        }
        $now = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        $then = ((int) ($uc['dia'] ?? 0)) * 24 + (int) ($uc['hora'] ?? 0);
        return $now - $then;
    }

    /**
     * Helper de laboratorio: proyecta N días sin contacto.
     */
    public static function proyectarValor(int $valor, int $dias, array $cal): int
    {
        $base = (float) CalibracionConfig::get($cal, 'desgaste_social.base_diaria', 2.0);
        $exp = (float) CalibracionConfig::get($cal, 'desgaste_social.exponente', 2.0);
        $v = (float) $valor;
        $resto = 0.0;
        for ($i = 0; $i < $dias; $i++) {
            if ($v <= 0) {
                return 0;
            }
            $rate = $base * pow(1.0 - ($v / 100.0), $exp);
            $resto += $rate;
            $enteros = (int) floor($resto);
            $resto -= $enteros;
            $v = max(0.0, $v - $enteros);
        }
        return (int) $v;
    }
}
