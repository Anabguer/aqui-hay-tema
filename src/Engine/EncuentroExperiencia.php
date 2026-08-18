<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Experiencia de encuentro por participante. Circunstancias primero; azar después.
 * Pesos de circunstancias null = carga 0 (azar uniforme provisional).
 */
final class EncuentroExperiencia
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function resolver(
        array $partida,
        array $encuentro,
        Catalog $catalog,
        RngService $rng,
        array $cal
    ): array {
        $snap = EncuentroPonderacion::snapshot($partida, $encuentro, $catalog);
        $ids = array_values($encuentro['participantes'] ?? []);
        $resultados = ['malo', 'regular', 'bueno', 'excelente'];
        $por = [];
        $rachaUmbral = CalibracionConfig::get($cal, 'azar_ponderado.racha_penaliza_tras', null);
        $rachaN = is_numeric($rachaUmbral) ? (int) $rachaUmbral : null;
        foreach ($ids as $pid) {
            $pid = (string) $pid;
            $carga = self::cargaDe($snap, $pid, $cal);
            $recientes = [];
            foreach (MemoriaEventos::recientes($partida, [$pid], 5) as $ev) {
                if (isset($ev['resultado_experiencia'])) {
                    $recientes[] = (string) $ev['resultado_experiencia'];
                }
            }
            $avisoRacha = AzarPonderado::rachaArtificial($recientes, 'excelente', $rachaN)
                || AzarPonderado::rachaArtificial($recientes, 'malo', $rachaN);
            $tirada = AzarPonderado::tirar($rng, $resultados, $carga, $cal);
            $por[$pid] = [
                'satisfaccion' => null,
                'texto' => null,
                'resultado' => $tirada['resultado'],
                'carga' => $carga,
                'aviso_racha' => $avisoRacha,
                'compatibilidad_hacia_otro' => $snap['por_participante'][$pid]['compatibilidad_hacia_otro'] ?? null,
                '_bloqueado_decision' => ['satisfaccion_numerica', 'copy'],
            ];
        }
        $snap['por_participante'] = $por;
        $snap['azar_ponderado'] = true;
        return $snap;
    }

    /**
     * @param array<string, mixed> $snap
     * @param array<string, mixed> $cal
     */
    public static function cargaDe(array $snap, string $pid, array $cal): float
    {
        $pesos = CalibracionConfig::get($cal, 'resolucion_encuentro.pesos', []);
        if (!is_array($pesos)) {
            return 0.0;
        }
        foreach ($pesos as $v) {
            if ($v !== null) {
                return 0.0;
            }
        }
        return 0.0;
    }
}
