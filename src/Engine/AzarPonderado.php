<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Azar ponderado: las circunstancias cargan la tirada; no determinan el resultado.
 * Compensación "dos malas -> toca buena" está prohibida.
 */
final class AzarPonderado
{
    /**
     * @param list<string> $resultados
     * @param array<string, mixed> $cal
     */
    public static function tirar(RngService $rng, array $resultados, float $carga, array $cal): array
    {
        if ($resultados === []) {
            return ['resultado' => null, 'carga' => 0.0, '_provisional' => true];
        }
        $min = (float) CalibracionConfig::get($cal, 'azar_ponderado.carga_min', -1);
        $max = (float) CalibracionConfig::get($cal, 'azar_ponderado.carga_max', 1);
        if ($carga < $min) {
            $carga = $min;
        }
        if ($carga > $max) {
            $carga = $max;
        }
        $n = count($resultados);
        $pesos = [];
        for ($i = 0; $i < $n; $i++) {
            $t = $n === 1 ? 0.0 : ($i / ($n - 1)) * 2 - 1;
            $pesos[] = max(0.01, 1 + ($carga * $t));
        }
        $sum = array_sum($pesos);
        $pick = $rng->nextFloat() * $sum;
        $acc = 0.0;
        $idx = $n - 1;
        foreach ($pesos as $i => $w) {
            $acc += $w;
            if ($pick <= $acc) {
                $idx = $i;
                break;
            }
        }
        return [
            '_provisional' => true,
            'resultado' => $resultados[$idx],
            'carga' => $carga,
            'pesos' => $pesos,
            'compensacion_obligatoria' => false,
        ];
    }

    /**
     * @param list<string> $recientes
     */
    public static function rachaArtificial(array $recientes, string $tipo, ?int $umbral): bool
    {
        if ($umbral === null || $umbral <= 0 || $recientes === []) {
            return false;
        }
        $n = 0;
        foreach (array_reverse($recientes) as $r) {
            if ($r !== $tipo) {
                break;
            }
            $n++;
        }
        return $n >= $umbral;
    }
}
