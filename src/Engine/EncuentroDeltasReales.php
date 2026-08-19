<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Mapea experiencia (muy_mal…muy_bien) a deltas acotados.
 * Solo se usa cuando resolucion_encuentro.aplicar_deltas_reales o lab_vida_activa.
 * No crea pareja ni salta hitos.
 */
final class EncuentroDeltasReales
{
    /**
     * @param array<string, mixed> $cal
     */
    public static function activo(array $partida, array $cal): bool
    {
        if (!empty($partida['lab_deltas_reales']) || !empty($partida['lab_vida_activa'])) {
            return true;
        }
        return (bool) CalibracionConfig::get($cal, 'resolucion_encuentro.aplicar_deltas_reales', false);
    }

    /**
     * @param array<string, mixed> $cal
     * @return array{social:int,romance:int,conflicto:?int,calidad:string,signo:int}
     */
    public static function deResultado(string $resultado, string $tipoEncuentro, array $cal): array
    {
        $mapa = CalibracionConfig::get($cal, 'resolucion_encuentro.deltas_por_resultado', []);
        if (!is_array($mapa)) {
            $mapa = [];
        }
        $row = is_array($mapa[$resultado] ?? null) ? $mapa[$resultado] : null;
        if (!is_array($row)) {
            return [
                'social' => 0,
                'romance' => 0,
                'conflicto' => null,
                'calidad' => ContactoCalidad::NORMAL,
                'signo' => 1,
            ];
        }
        $social = (int) ($row['social'] ?? 0);
        $romance = (int) ($row['romance'] ?? 0);
        $conflicto = array_key_exists('conflicto', $row) && $row['conflicto'] !== null
            ? (int) $row['conflicto']
            : null;
        $calidad = ContactoCalidad::canon((string) ($row['calidad'] ?? ContactoCalidad::NORMAL));
        if ($tipoEncuentro === 'romantico' && $romance === 0 && $social > 0) {
            $romance = (int) max(1, round($social * 0.5));
        }
        if ($tipoEncuentro !== 'romantico' && $tipoEncuentro !== 'cita') {
            // Social hangouts can nudge romance slightly only if already some interest (applied later).
            $romance = (int) round($romance * 0.35);
        }
        $social = ContactoCalidad::techo($social, $cal);
        $romance = ContactoCalidad::techo($romance, $cal);
        return [
            'social' => $social,
            'romance' => $romance,
            'conflicto' => $conflicto,
            'calidad' => $calidad,
            'signo' => $social >= 0 ? 1 : -1,
        ];
    }
}
