<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Tras una coincidencia técnica, delega en InteraccionCasual (tubo canónico).
 * Coincidir ≠ conocerse. No duplica probabilidad ni RNG propio.
 */
final class CoincidenciasInteraccionBridge
{
    /**
     * @param array{key:string,dia:int,hora:int,lugar_id:string,residentes:list<string>} $entry
     * @return array<string, mixed>|null
     */
    public static function intentarTrasCoincidencia(
        array &$partida,
        array $entry,
        string $projectRoot,
        ?GameLogger $logger = null
    ): ?array {
        $residentes = is_array($entry['residentes'] ?? null) ? $entry['residentes'] : [];
        $lugarId = (string) ($entry['lugar_id'] ?? '');
        $dia = (int) ($entry['dia'] ?? ($partida['reloj']['dia_pueblo'] ?? 1));
        $hora = (int) ($entry['hora'] ?? ($partida['reloj']['hora_actual'] ?? 0));
        if (count($residentes) < 2 || $lugarId === '') {
            return null;
        }
        $ids = array_values(array_unique(array_map('strval', $residentes)));
        sort($ids);
        if (count($ids) < 2) {
            return null;
        }

        $cal = CalibracionConfig::load($projectRoot);
        $rng = RngService::fromPartida($partida);
        $catalog = new Catalog($projectRoot);

        $hechos = InteraccionCasual::resolverGrupo(
            $partida,
            $ids,
            $lugarId,
            $dia,
            $hora,
            $rng,
            $cal,
            $catalog,
            ['bonus_patron' => true, 'solo_desconocidos' => true]
        );
        $rng->persistToPartida($partida);

        if ($hechos === []) {
            return null;
        }

        $hecho = $hechos[0];
        DomainEventDispatcher::emit($partida, DomainEvents::COINCIDENCIA_INTERACCION, [
            'coincidencia_key' => (string) ($entry['key'] ?? ''),
            'lugar_id' => $lugarId,
            'dia' => $dia,
            'hora' => $hora,
            'residentes' => [$hecho['a'] ?? $ids[0], $hecho['b'] ?? $ids[1]],
            'actores' => [$hecho['a'] ?? $ids[0], $hecho['b'] ?? $ids[1]],
            'interaccion' => $hecho,
        ], $logger, 'CoincidenciasInteraccionBridge', $ids);

        return $hecho;
    }
}
