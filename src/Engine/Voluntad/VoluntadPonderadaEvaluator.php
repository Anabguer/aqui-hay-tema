<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Voluntad;

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\ConsejoEngine;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\PlanAfinidad;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\RechazoMemoria;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;

/**
 * Sí/no ponderado. Nunca 100%. Excelente ≈ muy alto, no garantizado.
 */
final class VoluntadPonderadaEvaluator implements VoluntadEvaluator
{
    /** @var array<string, mixed> */
    private $cal;

    /**
     * @param array<string, mixed> $cal
     */
    public function __construct(array $cal = [])
    {
        $this->cal = $cal;
    }

    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        $cal = $this->cal !== [] ? $this->cal : [];
        $ids = is_array($propuesta['participantes'] ?? null) ? $propuesta['participantes'] : [];
        $otro = '';
        foreach ($ids as $id) {
            if ((string) $id !== $residenteId) {
                $otro = (string) $id;
                break;
            }
        }

        $cd = PropuestaCooldown::activo($partida, $residenteId, $otro, (string) ($propuesta['tipo'] ?? 'conocerse'), $cal);
        if ($cd) {
            return [
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => 'cooldown',
                'motivo_tecnico' => 'cooldown_propuesta',
                'motivo_tipo' => 'banal',
                'copy_id' => 'hoy_no_me_da_la_vida',
                'score' => null,
                'p' => 0.0,
                '_bloqueado_decision' => false,
            ];
        }

        $score = self::score($partida, $propuesta, $residenteId, $otro, $cal);
        $pMin = (float) CalibracionConfig::get($cal, 'voluntad.p_min', 0.08);
        $pMax = (float) CalibracionConfig::get($cal, 'voluntad.p_max', 0.94);
        $excelente = (int) CalibracionConfig::get($cal, 'voluntad.score_excelente', 88);
        $pExc = (float) CalibracionConfig::get($cal, 'voluntad.p_excelente', 0.92);
        $p = $pMin + (max(0, min(100, $score)) / 100.0) * ($pMax - $pMin);
        if ($score >= $excelente) {
            $p = $pExc;
        }
        if ($p > $pMax) {
            $p = $pMax;
        }
        if ($p < $pMin) {
            $p = $pMin;
        }

        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        $acepta = $tirada < $p;
        $copy = null;
        $motivo = 'ponderada';
        if (!$acepta) {
            $motivo = self::motivoRechazo($partida, $residenteId, $otro, $cal);
            $copy = self::copyBanal($rng, $cal);
        }
        return [
            'decision' => $acepta ? PropuestaEncuentro::DECISION_ACEPTA : PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => $acepta ? null : PropuestaEncuentro::CLASE_VOLUNTAD,
            'motivo_tecnico' => $acepta ? 'voluntad_acepta' : 'voluntad_rechaza_' . $motivo,
            'motivo_tipo' => $acepta ? null : $motivo,
            'copy_id' => $copy,
            'score' => $score,
            'p' => $p,
            '_bloqueado_decision' => false,
        ];
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function score(array $partida, array $propuesta, string $quien, string $otro, array $cal): int
    {
        $s = (int) CalibracionConfig::get($cal, 'voluntad.base', 48);
        $emo = (string) ($partida['residentes'][$quien]['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
        $mods = EstadoEmocional::modificadores($emo, $cal);
        $s += (int) ($mods['aceptar_planes'] ?? 0);

        if ($otro !== '') {
            $conocen = RelacionEngine::seConocen($partida, $quien, $otro);
            if (!$conocen) {
                $s -= 12;
            }
            $soc = RelacionEngine::valorSocialHacia($partida, $quien, $otro);
            $s += (int) round($soc * 0.28);
            $rom = RelacionEngine::romanceHacia($partida, $quien, $otro);
            if ($rom !== null) {
                $s += (int) round($rom * 0.18);
            }
            $conf = RelacionEngine::obtenerEntre($partida, $quien, $otro)['conflicto']['intensidad'] ?? null;
            if (is_numeric($conf)) {
                $s -= (int) $conf;
            }
            $nRech = RechazoMemoria::countHacia($partida, $quien, $otro);
            if ($nRech >= 2) {
                $s -= min(20, ($nRech - 1) * 6);
            }
            foreach (ConsejoEngine::activas($partida, $quien, $otro) as $c) {
                $idc = (string) ($c['consejo_id'] ?? '');
                if ($idc === 'lanzate' || $idc === 'queda_mas') {
                    $s += (int) CalibracionConfig::get($cal, 'consejo.inclinacion', 10);
                }
                if ($idc === 'no_es_el_momento' || $idc === 'tomar_distancia') {
                    $s -= (int) CalibracionConfig::get($cal, 'consejo.inclinacion', 10);
                }
            }
        }

        $lugar = isset($propuesta['lugar']) ? (string) $propuesta['lugar'] : null;
        $afin = PlanAfinidad::paraParticipante($partida, $quien, $lugar, null);
        $s += (int) ($afin['aporte'] ?? 0);
        $s -= (int) ($afin['penalizacion'] ?? 0);

        $tipo = (string) ($propuesta['tipo'] ?? '');
        if ($tipo === 'romantico' || $tipo === 'pareja') {
            $s += (int) ($mods['iniciativa_romantica'] ?? 0) / 2;
        }

        if ($s < 0) {
            return 0;
        }
        if ($s > 100) {
            return 100;
        }
        return (int) $s;
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function motivoRechazo(array $partida, string $quien, string $otro, array $cal): string
    {
        $emo = EstadoEmocional::canonId((string) ($partida['residentes'][$quien]['runtime']['estado_emocional']['id'] ?? 'neutro'));
        if ($emo === EstadoEmocional::ENFADADO || $emo === EstadoEmocional::TRISTE) {
            return 'emocional';
        }
        if ($otro !== '') {
            $conf = RelacionEngine::obtenerEntre($partida, $quien, $otro)['conflicto']['intensidad'] ?? null;
            if (is_numeric($conf) && (int) $conf >= 8) {
                return 'relacional';
            }
        }
        return 'banal';
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function copyBanal(RngService $rng, array $cal): string
    {
        $pool = CalibracionConfig::get($cal, 'voluntad.copy_banal', ['hoy_no_me_da_la_vida']);
        if (!is_array($pool) || $pool === []) {
            return 'hoy_no_me_da_la_vida';
        }
        $idx = $rng->nextInt(0, count($pool) - 1);
        return (string) $pool[$idx];
    }
}
