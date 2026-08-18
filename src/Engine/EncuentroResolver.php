<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Compatibility\PlaceholderEvaluator;

/** Resuelve encuentros. Social y romance evolucionan de forma independiente. */
final class EncuentroResolver
{
    public static function resolver(array $partida, array $encuentro, ?GameLogger $logger = null): array
    {
        $evaluator = new PlaceholderEvaluator();
        $participantes = $encuentro['participantes'] ?? [];
        $tipo = $encuentro['tipo'] ?? 'conocerse';

        $deltaSocial = [];
        $deltaRomance = [];
        if (count($participantes) >= 2) {
            $a = $participantes[0];
            $b = $participantes[1];
            $ctx = [
                'tipo_encuentro' => $tipo,
                'lugar' => $encuentro['lugar'] ?? null,
                'dia' => $encuentro['dia'] ?? null,
                'hora' => $encuentro['hora'] ?? null,
            ];
            $deltaSocial = $evaluator->evaluateSocial($partida, $a, $b, $ctx);
            if ($tipo === 'romantico') {
                $deltaRomance = $evaluator->evaluateRomantic($partida, $a, $b, $ctx);
            }
        }

        $resultado = [
            '_placeholder' => true,
            'delta_social' => $deltaSocial,
            'delta_romance' => $deltaRomance,
            'conflicto' => null,
            'descubrimientos' => [],
            'eventos_derivados' => [],
            'texto_resumen' => '[PLACEHOLDER] Encuentro ' . $tipo . ' terminado.',
        ];

        \aht_log_optional($logger, $partida, 'encuentro_resuelto', [
            'encuentro_id' => $encuentro['id'] ?? null,
            'tipo' => $tipo,
            'delta_social' => $deltaSocial,
            'delta_romance' => $deltaRomance,
        ]);

        return $resultado;
    }

    public static function aplicarResultado(array &$partida, array $encuentro, array $resultado, ?GameLogger $logger = null): void
    {
        $participantes = $encuentro['participantes'] ?? [];
        if (count($participantes) < 2) {
            return;
        }
        [$a, $b] = [$participantes[0], $participantes[1]];

        $ds = $resultado['delta_social'] ?? [];
        if (!empty($ds)) {
            RelacionEngine::upsertSocial(
                $partida,
                $a,
                $b,
                (string) ($ds['tipo'] ?? 'conocidos'),
                isset($ds['intensidad']) ? (int) $ds['intensidad'] : null,
                isset($ds['se_soportan']) ? (bool) $ds['se_soportan'] : null
            );
            \aht_log_optional($logger, $partida, 'relacion_delta_social', [
                'persona_a' => $a,
                'persona_b' => $b,
                'delta' => $ds,
            ]);
        }

        $dr = $resultado['delta_romance'] ?? [];
        if (!empty($dr)) {
            RelacionEngine::upsertRomance($partida, $a, $b, $dr);
            \aht_log_optional($logger, $partida, 'relacion_delta_romance', [
                'persona_a' => $a,
                'persona_b' => $b,
                'delta' => $dr,
            ]);
        }
    }
}
