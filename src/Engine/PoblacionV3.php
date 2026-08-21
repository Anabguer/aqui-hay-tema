<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Arranque poblacional V3 desde config prevalidada. */
final class PoblacionV3
{
    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $config
     */
    public static function incorporarIniciales(
        array &$partida,
        array $config,
        string $root,
        ResidenteOperations $ops
    ): void {
        $pv3 = $config['poblacion_v3'] ?? null;
        if (!is_array($pv3)) {
            return;
        }
        $n = (int) ($pv3['iniciales_aleatorios'] ?? 0);
        if ($n <= 0) {
            return;
        }
        $cat = new Catalog($root);
        $pool = $cat->listPersonajeIds();
        $rng = RngService::fromPartida($partida);
        $picked = $rng->pickUnique($pool, min($n, count($pool)));
        $rng->persistToPartida($partida);
        foreach ($picked as $id) {
            $ops->incorporarCatalogo($partida, (string) $id, 'residente');
        }
        $inc = (int) ($pv3['incorporaciones_aleatorias'] ?? 0);
        if ($inc > 0) {
            $rest = array_values(array_diff($pool, $picked));
            $cola = $rng->pickUnique($rest, min($inc, count($rest)));
            $rng->persistToPartida($partida);
            $partida['llegadas']['tutorial_cola'] = array_values($cola);
        }
    }
}
