<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Agrupa entradas reales del diario. No inventa cotilleos. */
final class VistaCotilleoV3
{
    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function de(array $partida): array
    {
        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $viejos = [];
        foreach ($partida['diario'] ?? [] as $e) {
            if (!is_array($e)) {
                continue;
            }
            if ((int) ($e['dia'] ?? 0) < $hoy - 1) {
                $viejos[] = $e;
            }
        }
        return [
            'hoy' => DiarioEngine::listarPorDia($partida, $hoy),
            'ayer' => $hoy > 1 ? DiarioEngine::listarPorDia($partida, $hoy - 1) : [],
            'viejos' => array_values($viejos),
        ];
    }
}
