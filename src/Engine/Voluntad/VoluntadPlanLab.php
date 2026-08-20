<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Voluntad;

/**
 * Lab de fórmulas de voluntad de PLAN (ambos participantes).
 * No canoniza: solo compara. Ver BLOQUEADO_DECISION_VOLUNTAD.
 */
final class VoluntadPlanLab
{
    /** @return list<string> */
    public static function formulas(): array
    {
        return [
            'producto',           // actual: pA×pB
            'media_geometrica',   // √(pA×pB)
            'minimo',             // min(pA,pB)
            'min_suave',          // min * (0.55 + 0.45*max)
            'umbral_dual',        // ambos ≥ umbral → geom; si no → producto
        ];
    }

    public static function pPlan(string $formula, float $pA, float $pB): float
    {
        $pA = max(0.0, min(1.0, $pA));
        $pB = max(0.0, min(1.0, $pB));
        switch ($formula) {
            case 'producto':
                return $pA * $pB;
            case 'media_geometrica':
                return sqrt($pA * $pB);
            case 'minimo':
                return min($pA, $pB);
            case 'min_suave':
                return min($pA, $pB) * (0.55 + 0.45 * max($pA, $pB));
            case 'umbral_dual':
                $u = 0.55;
                if ($pA >= $u && $pB >= $u) {
                    return sqrt($pA * $pB);
                }
                return $pA * $pB;
            default:
                return $pA * $pB;
        }
    }

    /**
     * Matriz de pares score→p vía fórmula calibración lineal, + Monte Carlo.
     *
     * @param list<array{0:int,1:int}> $pares
     * @return array<string, mixed>
     */
    public static function simular(
        array $pares,
        int $tiradasPorPar = 2000,
        float $pMin = 0.08,
        float $pMax = 0.94
    ): array {
        $scoreToP = static function (int $score) use ($pMin, $pMax): float {
            return $pMin + (max(0, min(100, $score)) / 100.0) * ($pMax - $pMin);
        };

        $out = ['formulas' => [], 'recomendacion' => null, 'bloqueado' => 'BLOQUEADO_DECISION_VOLUNTAD'];
        foreach (self::formulas() as $f) {
            $rows = [];
            foreach ($pares as $par) {
                $sA = (int) $par[0];
                $sB = (int) $par[1];
                $pA = $scoreToP($sA);
                $pB = $scoreToP($sB);
                $pEsperada = self::pPlan($f, $pA, $pB);
                $acept = 0;
                // LCG local reproducible
                $state = abs(crc32($f . ':' . $sA . ':' . $sB)) % 2147483647;
                if ($state === 0) {
                    $state = 1;
                }
                for ($i = 0; $i < $tiradasPorPar; $i++) {
                    $state = (48271 * $state) % 2147483647;
                    $tA = $state / 2147483646.0;
                    $state = (48271 * $state) % 2147483647;
                    $tB = $state / 2147483646.0;
                    // Cada fórmula define P(plan). Simulamos una tirada uniforme contra p_plan
                    // (equivalente a decisión conjunta), salvo producto que también puede
                    // modelarse como dos tiradas independientes — misma esperanza.
                    if ($tA < $pEsperada) {
                        $acept++;
                    }
                }
                $rows[] = [
                    'scores' => [$sA, $sB],
                    'pA' => round($pA, 3),
                    'pB' => round($pB, 3),
                    'p_plan' => round($pEsperada, 3),
                    'tasa_obs' => round($acept / $tiradasPorPar, 3),
                    'penaliza_min' => round(min($pA, $pB) - $pEsperada, 3),
                ];
            }
            $out['formulas'][$f] = [
                'matriz' => $rows,
                'p_70_70' => self::pPlan($f, $scoreToP(70), $scoreToP(70)),
                'p_95_95' => self::pPlan($f, $scoreToP(95), $scoreToP(95)),
                'p_95_20' => self::pPlan($f, $scoreToP(95), $scoreToP(20)),
                'p_20_20' => self::pPlan($f, $scoreToP(20), $scoreToP(20)),
            ];
        }

        // Recomendación: media geométrica — 70/70 ≈0.68; 95/20 castigado; no media aritmética.
        $out['recomendacion'] = [
            'formula' => 'media_geometrica',
            'motivos' => [
                '70/70 se siente razonable (~p individual), no ~0.40.',
                '95/20 sigue castigado por el miembro débil (no se comporta como 50/50).',
                '20/20 permanece muy bajo.',
                '95/95 alto sin llegar a 1.0.',
                'Mantiene voluntad individual en el cálculo (vía pA y pB), sin promediar scores.',
            ],
            'no_canonizado' => true,
        ];
        return $out;
    }
}
