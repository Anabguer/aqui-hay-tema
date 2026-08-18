<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Simulación QA sin jugador — fixtures controlados, no autonomía rica. */
final class SimulationRunner
{
    public static function run(
        string $projectRoot,
        int $days,
        ?string $seed = null,
        string $configId = 'test_fixtures_v0'
    ): array {
        $t0 = microtime(true);
        $days = max(1, min(365, $days));
        $informe = [
            'ok' => true,
            '_nota' => 'Simulación QA no canónica',
            'days' => $days,
            'seed' => $seed,
            'errores' => [],
            'invariantes_rotas' => [],
            'encuentros_programados' => 0,
            'encuentros_resueltos' => 0,
            'conflictos_agenda' => 0,
            'eventos_dominio' => 0,
            'relaciones_cambios' => 0,
        ];

        $service = new PartidaService($projectRoot);
        try {
            $partida = $service->nuevaPartida($configId, $seed ?? 'sim-' . $days);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $ph = $service->crearResidentePlaceholderDev($partida);
        $qa = 'per_qa_valid';
        $phId = $ph['residente']['catalog_id'];

        for ($d = 0; $d < $days; $d++) {
            $dia = (int) $partida['reloj']['dia_pueblo'];
            $slots = DisponibilidadEngine::slotsCompatibles($partida, [$qa, $phId], 'conocerse', $dia, 12, 1, 3);
            if (($slots['ok'] ?? false) && !empty($slots['slots'])) {
                $slot = $slots['slots'][0];
                $r = $service->programarEncuentro($partida, [$qa, $phId], $slot['dia'], $slot['hora'], 'conocerse');
                if ($r['ok'] ?? false) {
                    $informe['encuentros_programados']++;
                } else {
                    $informe['errores'][] = ['dia' => $dia, 'programar' => $r['error'] ?? 'fail'];
                }
            }

            $adv = $service->avanzarReloj($partida, 24);
            $informe['encuentros_resueltos'] += (int) ($adv['encuentros_resueltos'] ?? 0);

            $cal = DevCalendarService::vistaDia($partida, $dia, $service->getCatalog());
            $informe['conflictos_agenda'] += count($cal['conflictos'] ?? []);

            self::checkInvariants($partida, $informe);
        }

        $service->guardar($partida);
        $path = (new PartidaRepository($projectRoot))->pathFor($partida['meta']['partida_id']);
        $t1 = microtime(true);

        $informe['partida_id'] = $partida['meta']['partida_id'];
        $informe['save_bytes'] = is_file($path) ? filesize($path) : 0;
        $informe['ms_total'] = round(($t1 - $t0) * 1000, 2);
        $informe['eventos_dominio'] = count($partida['domain_events'] ?? []);
        $informe['relaciones_cambios'] = count($partida['historial_relaciones'] ?? []);
        $informe['audit_trail_size'] = count($partida['audit_trail'] ?? []);
        $informe['residentes'] = count($partida['residentes']);

        return $informe;
    }

    private static function checkInvariants(array $partida, array &$informe): void
    {
        if ((int) ($partida['reloj']['hora_actual'] ?? -1) < 0 || (int) ($partida['reloj']['hora_actual'] ?? 99) > 23) {
            $informe['invariantes_rotas'][] = 'hora_fuera_rango';
        }
        foreach ($partida['encuentros'] ?? [] as $enc) {
            foreach ($enc['participantes'] ?? [] as $p) {
                if (!isset($partida['residentes'][$p])) {
                    $informe['invariantes_rotas'][] = 'encuentro_participante_huérfano:' . $enc['id'];
                }
            }
        }
        if (count($partida['audit_trail'] ?? []) > 500) {
            $informe['invariantes_rotas'][] = 'audit_trail_excede_cap';
        }
    }
}
