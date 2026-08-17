<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Smoke técnico 100 residentes placeholder — NO canónico. */
final class StressTestRunner
{
    public static function run(string $projectRoot, int $count = 100): array
    {
        $count = max(1, min(200, $count));
        $t0 = microtime(true);

        $service = new PartidaService($projectRoot);
        $partida = $service->nuevaPartida('debug_v0', 'stress-' . $count);
        $partidaId = $partida['meta']['partida_id'];

        for ($i = 1; $i <= $count; $i++) {
            $runtime = ResidenteRuntime::crearPlaceholderDev($i);
            $id = $runtime['catalog_id'];
            $partida['residentes'][$id] = $runtime;
            $asig = BloqueA::asignarAutomatico($partida, $id);
            if ($asig['error'] !== null) {
                $partida['residentes'][$id]['vivienda_id'] = null;
            }
        }

        $agendas = 0;
        foreach (array_keys($partida['residentes']) as $rid) {
            AgendaEngine::resolverDia($partida, $rid, 1);
            $agendas++;
        }

        $service->guardar($partida);
        $path = (new PartidaRepository($projectRoot))->pathFor($partidaId);
        $saveBytes = is_file($path) ? filesize($path) : 0;

        $loaded = $service->cargar($partidaId);
        $t1 = microtime(true);

        return [
            'ok' => true,
            '_nota' => 'Prueba DEV no canónica. Placeholders sintéticos.',
            'residentes' => count($loaded['residentes']),
            'agendas_resueltas' => $agendas,
            'save_bytes' => $saveBytes,
            'ms_total' => round(($t1 - $t0) * 1000, 2),
            'partida_id' => $partidaId,
            'bloque_ocupadas' => BloqueA::resumen($loaded)['ocupadas'],
        ];
    }
}
