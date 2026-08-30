<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Lab catch-up offline: Vida alta/media/baja × ausencias reales.
 * Usa CatchUpEngine real + misiones diarias sin cumplir (jugador ausente).
 */
final class SimuladorCatchUpOffline
{
    public const PERFILES_VIDA = [
        'alta' => 85,
        'media' => 65,
        'baja' => 45,
    ];

    public const AUSENCIAS_DIAS = [1, 2, 4, 5, 7, 14, 30];

    /**
     * @return array<string, mixed>
     */
    public static function ejecutar(string $projectRoot): array
    {
        $cal = CalibracionConfig::load($projectRoot);
        $tabla = [];
        foreach (self::PERFILES_VIDA as $perfil => $vidaInicial) {
            $tabla[$perfil] = [];
            foreach (self::AUSENCIAS_DIAS as $dias) {
                $tabla[$perfil][(string) $dias] = self::correrEscenario(
                    $projectRoot,
                    $cal,
                    $perfil,
                    $vidaInicial,
                    $dias
                );
            }
        }

        return [
            '_provisional' => true,
            '_nota' => 'Catch-up REAL: misiones caducan, vida_dia_ignorado por día. Sin aplicarAusencia.',
            'vida_dia_ignorado' => (int) CalibracionConfig::get($cal, 'misiones_diarias.vida_dia_ignorado', -2),
            'catch_up_cfg' => CatchUpEngine::cfg($cal),
            'tabla' => $tabla,
            'cumple_intencion' => self::evaluarIntencion($tabla, $cal),
        ];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function correrEscenario(
        string $projectRoot,
        array $cal,
        string $perfil,
        int $vidaInicial,
        int $diasAusencia
    ): array {
        $ahora = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
        $desde = $ahora->sub(new \DateInterval('P' . max(0, $diasAusencia) . 'D'));

        $rng = new RngService('catchup-lab-' . $perfil . '-' . $diasAusencia);
        $partida = SimuladorMisionesDiarias::partidaLab(8, $rng, $cal);
        unset($partida['_lab_misiones_b3']);
        $partida['features'] = [
            VidaPuebloEngine::FLAG => true,
            MisionDiariaEngine::FLAG => true,
            CatchUpEngine::FLAG => true,
            'encuentros_enabled' => false,
            'npc_autonomy_enabled' => false,
        ];
        VidaPuebloEngine::ensure($partida, $cal);
        $deltaSetup = $vidaInicial - VidaPuebloEngine::valor($partida);
        if ($deltaSetup !== 0) {
            VidaPuebloEngine::aplicar($partida, $deltaSetup, [
                'causa' => VidaPuebloEngine::CAUSA_LAB_SETUP,
                'origen' => VidaPuebloEngine::ORIGEN_LAB,
                'atribuible_celestine' => true,
                'positivo_valido_latido' => false,
                'lab' => true,
            ], $cal);
        }
        MisionDiariaEngine::alComenzarDia($partida, $cal, $rng);

        $partida['reloj']['ultimo_catch_up_iso'] = $desde->format(DATE_ATOM);
        $partida['reloj']['ultima_sesion_iso'] = $desde->format(DATE_ATOM);
        $diaAntes = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $vidaAntes = VidaPuebloEngine::valor($partida);

        $r = CatchUpEngine::ejecutarAlCargar($partida, $projectRoot, $cal, null, null, $ahora);

        $penalizaciones = 0;
        foreach ($partida['vida_pueblo']['ledger'] ?? [] as $e) {
            if (($e['causa'] ?? '') === VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO) {
                $penalizaciones++;
            }
        }

        return [
            'perfil' => $perfil,
            'dias_ausencia' => $diasAusencia,
            'vida_inicial' => $vidaAntes,
            'vida_final' => VidaPuebloEngine::valor($partida),
            'delta_vida' => VidaPuebloEngine::valor($partida) - $vidaAntes,
            'dia_antes' => $diaAntes,
            'dia_despues' => (int) ($partida['reloj']['dia_pueblo'] ?? $diaAntes),
            'horas_avanzadas' => (int) ($r['horas_juego_avanzadas'] ?? 0),
            'dias_penalizados' => $penalizaciones,
            'game_over' => (bool) ($partida['vida_pueblo']['game_over_activo'] ?? false),
            'game_over_pendiente' => (bool) ($partida['vida_pueblo']['game_over_pendiente'] ?? false),
            'ejecutado' => (bool) ($r['ejecutado'] ?? false),
        ];
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $tabla
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function evaluarIntencion(array $tabla, array $cal): array
    {
        $vidaIgn = (int) CalibracionConfig::get($cal, 'misiones_diarias.vida_dia_ignorado', -2);
        $obs = [];
        $parametro = 'misiones_diarias.vida_dia_ignorado (actual ' . $vidaIgn . ')';

        foreach (['alta', 'media'] as $perfil) {
            $d1 = abs((int) ($tabla[$perfil]['1']['delta_vida'] ?? 0));
            $d2 = abs((int) ($tabla[$perfil]['2']['delta_vida'] ?? 0));
            if ($d1 > 6 || $d2 > 9) {
                $obs[] = "{$perfil}: 1-2d impacto alto (Δ{$d1}/Δ{$d2})";
            }
        }
        foreach (['alta', 'media', 'baja'] as $perfil) {
            $d7 = abs((int) ($tabla[$perfil]['7']['delta_vida'] ?? 0));
            if ($d7 < 12) {
                $obs[] = "{$perfil}: 7d poco visible (Δ{$d7})";
            }
        }
        foreach (['alta', 'media'] as $perfil) {
            if (!empty($tabla[$perfil]['30']['game_over'])) {
                $obs[] = "{$perfil}: GO a 30d desde vida sana — inaceptable";
            }
        }

        return [
            'cumple' => $obs === [],
            'observaciones' => $obs,
            'parametro_calibrar_si_no_cumple' => $obs !== [] ? $parametro : null,
        ];
    }
}
