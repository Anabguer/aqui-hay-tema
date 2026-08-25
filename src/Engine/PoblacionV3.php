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
        $disponibles = $cat->listPersonajeIdsJugables();
        $rng = RngService::fromPartida($partida);
        // Regla global: sin nombres duplicados en la partida. Se respetan los
        // nombres de residentes ya presentes (p. ej. residentes_iniciales del config)
        // y cada escogido reserva su nombre; los descartados se sustituyen por
        // otro id válido hasta completar la cantidad prevista (o agotar pool).
        $usados = NombresReservadosPartida::usados($partida, $root);
        $picked = NombresReservadosPartida::escogerSinRepetirNombre($rng, $disponibles, $n, $usados, $root);
        foreach ($picked as $id) {
            $ops->incorporarCatalogo($partida, $id, 'residente');
        }
        $rng->persistToPartida($partida);
        $inc = (int) ($pv3['incorporaciones_aleatorias'] ?? 0);
        if ($inc > 0) {
            $cola = NombresReservadosPartida::escogerSinRepetirNombre($rng, $disponibles, $inc, $usados, $root);
            $rng->persistToPartida($partida);
            $partida['llegadas']['tutorial_cola'] = $cola;
        }
    }
}
