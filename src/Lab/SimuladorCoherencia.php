<?php
declare(strict_types=1);

namespace AquiHayTema\Lab;

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoGeneradorEspontaneo;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\NarrativeCoherenceEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PersistenciaCaps;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;

/**
 * Lab de coherencia narrativa.
 *
 * - Lab corto: 1 partida × 20 días, coherencia post-encuentro.
 * - Lab largo: N seeds × M días, sin violaciones.
 */
final class SimuladorCoherencia
{
    /**
     * Lab corto: rápido, reproducible, CI/preflight.
     *
     * @return array{ok: bool, errores: int, hallazgos: list, dias: int, tiempo_ms: int}
     */
    public static function labCorto(string $root, string $seed = 'lab-corto-1'): array
    {
        $start = microtime(true);
        DomainBootstrap::boot();
        $svc = new PartidaService($root);
        $cal = CalibracionConfig::load($root);
        $p = $svc->nuevaPartida('juego_v1', $seed);
        $p['features']['buzon_enabled'] = true;
        $p['calibracion'] = $cal;

        $rng = RngService::fromPartida($p);

        for ($dia = 1; $dia <= 20; $dia++) {
            $p['reloj']['dia_pueblo'] = $dia;
            $p['reloj']['hora_actual'] = 12;

            MotorVidaDiaria::alComenzarDia($p, $cal, $rng);

            $residentes = array_keys($p['residentes'] ?? []);
            if (count($residentes) >= 2) {
                $a = $residentes[0];
                $b = $residentes[1];
                RelacionEngine::registrarContacto($p, $a, $b, 'casual', $cal);
            }

            foreach ($residentes as $rid) {
                MensajitoGeneradorEspontaneo::evaluar($p, $rid, $cal, $rng);
            }

            PersistenciaCaps::aplicar($p);
            $rng->persistToPartida($p);
        }

        $h = NarrativeCoherenceEngine::verificar($p, $cal);
        $errores = count(array_filter($h, fn($x) => ($x['severidad'] ?? '') === 'ALTA'));
        $elapsed = (int) ((microtime(true) - $start) * 1000);

        return [
            'ok' => $errores === 0,
            'errores' => $errores,
            'hallazgos' => $h,
            'dias' => 20,
            'tiempo_ms' => $elapsed,
        ];
    }

    /**
     * Lab largo: muchas seeds, muchos días, sin violaciones.
     *
     * @return array{ok: bool, seeds: int, dias_total: int, errores_total: int, tiempo_ms: int, detalle: list}
     */
    public static function labLargo(string $root, int $seeds = 10, int $diasPorSeed = 100): array
    {
        $start = microtime(true);
        DomainBootstrap::boot();
        $svc = new PartidaService($root);
        $cal = CalibracionConfig::load($root);

        $detalle = [];
        $erroresTotal = 0;
        $diasTotal = 0;

        for ($s = 0; $s < $seeds; $s++) {
            $seed = "lab-largo-seed-$s";
            $p = $svc->nuevaPartida('juego_v1', $seed);
            $p['features']['buzon_enabled'] = true;
            $p['calibracion'] = $cal;
            $rng = RngService::fromPartida($p);
            $erroresSeed = 0;

            for ($dia = 1; $dia <= $diasPorSeed; $dia++) {
                $p['reloj']['dia_pueblo'] = $dia;
                $p['reloj']['hora_actual'] = 12;

                MotorVidaDiaria::alComenzarDia($p, $cal, $rng);

                $residentes = array_keys($p['residentes'] ?? []);
                if (count($residentes) >= 2) {
                    $a = $residentes[array_rand($residentes)];
                    $b = $residentes[array_rand($residentes)];
                    if ($a !== $b) {
                        RelacionEngine::registrarContacto($p, $a, $b, 'casual', $cal);
                    }
                }

                foreach ($residentes as $rid) {
                    MensajitoGeneradorEspontaneo::evaluar($p, $rid, $cal, $rng);
                }

                PersistenciaCaps::aplicar($p);
                $rng->persistToPartida($p);
            }

            $h = NarrativeCoherenceEngine::verificar($p, $cal);
            $erroresSeed = count(array_filter($h, fn($x) => ($x['severidad'] ?? '') === 'ALTA'));
            $erroresTotal += $erroresSeed;
            $diasTotal += $diasPorSeed;

            $detalle[] = [
                'seed' => $seed,
                'dias' => $diasPorSeed,
                'errores' => $erroresSeed,
                'memoria_eventos' => count($p['memoria_eventos'] ?? []),
                'buzon' => count($p['buzon'] ?? []),
            ];
        }

        $elapsed = (int) ((microtime(true) - $start) * 1000);

        return [
            'ok' => $erroresTotal === 0,
            'seeds' => $seeds,
            'dias_total' => $diasTotal,
            'errores_total' => $erroresTotal,
            'tiempo_ms' => $elapsed,
            'detalle' => $detalle,
        ];
    }
}
