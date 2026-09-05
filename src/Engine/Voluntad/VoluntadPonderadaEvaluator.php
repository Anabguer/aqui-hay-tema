<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Voluntad;

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\ConsejoEngine;
use AquiHayTema\Engine\CopyVoluntad;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\PlanAfinidad;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaNivel;
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
            $rngCd = RngService::fromPartida($partida);
            $copyCd = self::copyCooldown($rngCd, $cal);
            $rngCd->persistToPartida($partida);
            return [
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => PropuestaEncuentro::CLASE_COOLDOWN,
                'motivo_tecnico' => 'cooldown_propuesta',
                'motivo_tipo' => 'banal',
                'copy_id' => $copyCd,
                'score' => null,
                'p' => 0.0,
                '_bloqueado_decision' => false,
            ];
        }

        $desglose = self::desglose($partida, $propuesta, $residenteId, $otro, $cal);
        $score = (int) ($desglose['score'] ?? 0);
        $bonusNucleo = self::bonusPeticionNucleo($propuesta, $residenteId, $cal);
        if ($bonusNucleo > 0) {
            $score += $bonusNucleo;
            $desglose['bonus_peticion_nucleo'] = $bonusNucleo;
        }
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
        $resolucion = (string) CalibracionConfig::get($cal, 'voluntad.resolucion_plan', 'media_geometrica');
        // media_geometrica: no tirar aquí; PropuestaEncuentroEngine resuelve el plan con √(pA·pB).
        // Evitar doble registro en RechazoMemoria dentro de evaluarParticipante.
        if ($resolucion === 'media_geometrica') {
            $factores = $desglose;
            $factores['p'] = $p;
            $factores['resolucion_plan'] = 'media_geometrica';
            $factores['tirada_diferida'] = true;
            return [
                'decision' => PropuestaEncuentro::DECISION_ACEPTA,
                'clase' => null,
                'motivo_tecnico' => 'voluntad_p_calculada',
                'motivo_tipo' => null,
                'copy_id' => null,
                'score' => $score,
                'p' => $p,
                'factores' => $factores,
                '_bloqueado_decision' => false,
                '_joint_plan' => true,
            ];
        }
        $acepta = $tirada < $p;
        $copy = null;
        $motivo = 'ponderada';
        if (!$acepta) {
            $motivo = self::motivoRechazo($partida, $residenteId, $otro, $cal);
            $copy = $motivo;
        }
        $factores = $desglose;
        $factores['p'] = $p;
        $factores['tirada_rng'] = $tirada;
        $factores['umbral_p'] = $p;
        $factores['acepta_si_tirada_menor_que_p'] = $acepta;
        $factores['resolucion_plan'] = 'producto';
        return [
            'decision' => $acepta ? PropuestaEncuentro::DECISION_ACEPTA : PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => $acepta ? null : PropuestaEncuentro::CLASE_VOLUNTAD,
            'motivo_tecnico' => $acepta ? 'voluntad_acepta' : 'voluntad_rechaza_' . $motivo,
            'motivo_tipo' => $acepta ? null : $motivo,
            'copy_id' => $copy,
            'score' => $score,
            'p' => $p,
            'factores' => $factores,
            '_bloqueado_decision' => false,
        ];
    }

    /**
     * Desglose real del score (para playtest / diagnóstico).
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $propuesta
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function desglose(array $partida, array $propuesta, string $quien, string $otro, array $cal): array
    {
        $base = (int) CalibracionConfig::get($cal, 'voluntad.base', 48);
        $emo = (string) ($partida['residentes'][$quien]['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
        $mods = EstadoEmocional::modificadores($emo, $cal);
        $modEmo = (int) ($mods['aceptar_planes'] ?? 0);
        $s = $base + $modEmo;
        $conocen = false;
        $soc = 0;
        $rom = null;
        $conf = null;
        $nRech = 0;
        $modConocer = 0;
        $modSoc = 0;
        $modRom = 0;
        $modConf = 0;
        $modRech = 0;
        $modConsejo = 0;
        if ($otro !== '') {
            $conocen = RelacionEngine::seConocen($partida, $quien, $otro);
            if (!$conocen) {
                $modConocer = -12;
                $s += $modConocer;
            }
            $soc = RelacionEngine::valorSocialHacia($partida, $quien, $otro);
            $modSoc = (int) round($soc * 0.28);
            $s += $modSoc;
            $rom = RelacionEngine::romanceHacia($partida, $quien, $otro);
            if ($rom !== null) {
                $modRom = (int) round($rom * 0.18);
                $s += $modRom;
            }
            $conf = RelacionEngine::obtenerEntre($partida, $quien, $otro)['conflicto']['intensidad'] ?? null;
            if (is_numeric($conf)) {
                $modConf = -(int) $conf;
                $s += $modConf;
            }
            $nRech = RechazoMemoria::countHacia($partida, $quien, $otro);
            if ($nRech >= 2) {
                $modRech = -min(20, ($nRech - 1) * 6);
                $s += $modRech;
            }
            foreach (ConsejoEngine::activas($partida, $quien, $otro) as $c) {
                $idc = (string) ($c['consejo_id'] ?? '');
                $inc = (int) CalibracionConfig::get($cal, 'consejo.inclinacion', 10);
                if ($idc === 'lanzate' || $idc === 'queda_mas') {
                    $modConsejo += $inc;
                    $s += $inc;
                }
                if ($idc === 'no_es_el_momento' || $idc === 'tomar_distancia') {
                    $modConsejo -= $inc;
                    $s -= $inc;
                }
            }
        }
        $lugar = isset($propuesta['lugar']) ? (string) $propuesta['lugar'] : null;
        $afin = PlanAfinidad::paraParticipante($partida, $quien, $lugar, null);
        $aporteAfin = (int) ($afin['aporte'] ?? 0);
        $penAfin = (int) ($afin['penalizacion'] ?? 0);
        $s += $aporteAfin - $penAfin;
        $tipo = PropuestaNivel::aliasTipo((string) ($propuesta['tipo'] ?? ''));
        $modRomTipo = 0;
        if (PropuestaNivel::esTipoCita($tipo) || $tipo === 'pareja') {
            $modRomTipo = (int) (($mods['iniciativa_romantica'] ?? 0) / 2);
            $s += $modRomTipo;
        }
        $modTipo = self::modTipo($tipo, $cal);
        $s += $modTipo;
        $score = max(0, min(100, $s));
        return [
            'score' => $score,
            'base' => $base,
            'estado_emocional' => $emo,
            'mod_estado_emocional_aceptar_planes' => $modEmo,
            'relacion_previa_se_conocen' => $conocen,
            'mod_aun_no_se_conocen' => $modConocer,
            'social' => $soc,
            'mod_social' => $modSoc,
            'romance' => $rom,
            'mod_romance' => $modRom,
            'conflicto' => $conf,
            'mod_conflicto' => $modConf,
            'rechazos_previos' => $nRech,
            'mod_rechazos' => $modRech,
            'mod_consejo' => $modConsejo,
            'lugar' => $lugar,
            'afinidad_aporte' => $aporteAfin,
            'afinidad_penalizacion' => $penAfin,
            'tipo' => $tipo,
            'mod_tipo' => $modTipo,
            'mod_iniciativa_romantica' => $modRomTipo,
        ];
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function score(array $partida, array $propuesta, string $quien, string $otro, array $cal): int
    {
        return (int) (self::desglose($partida, $propuesta, $quien, $otro, $cal)['score'] ?? 0);
    }

    /**
     * Bonus/malus explícito por tipo de propuesta. No cambia la base global.
     *
     * @param array<string, mixed> $cal
     */
    public static function modTipo(string $tipo, array $cal): int
    {
        $tipo = PropuestaNivel::aliasTipo($tipo);
        if ($tipo === '') {
            return 0;
        }
        $v = CalibracionConfig::get($cal, 'voluntad.mod_tipo.' . $tipo, 0);
        return is_numeric($v) ? (int) $v : 0;
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function motivoRechazoPublic(array $partida, string $quien, string $otro, array $cal): string
    {
        return self::motivoRechazo($partida, $quien, $otro, $cal);
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function copyBanalPublic(RngService $rng, array $cal): string
    {
        return self::copyBanal($rng, $cal);
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function copyCooldownPublic(RngService $rng, array $cal): string
    {
        return self::copyCooldown($rng, $cal);
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

    /**
     * @param array<string, mixed> $cal
     */
    private static function copyCooldown(RngService $rng, array $cal): string
    {
        $pool = CalibracionConfig::get($cal, 'voluntad.copy_cooldown', CopyVoluntad::COOLDOWN_IDS);
        if (!is_array($pool) || $pool === []) {
            return 'hoy_no_me_da_la_vida';
        }
        $idx = $rng->nextInt(0, count($pool) - 1);
        return (string) $pool[$idx];
    }

    /**
     * Boost fuerte al peticionario cuando el plan cubre el núcleo pero no es exacto.
     *
     * @param array<string, mixed> $propuesta
     * @param array<string, mixed> $cal
     */
    private static function bonusPeticionNucleo(array $propuesta, string $residenteId, array $cal): int
    {
        if ($residenteId === '') {
            return 0;
        }
        $origen = is_array($propuesta['origen_peticion'] ?? null) ? $propuesta['origen_peticion'] : null;
        if ($origen !== null && (string) ($origen['nivel'] ?? '') === 'nucleo'
            && (string) ($origen['residente_id'] ?? '') === $residenteId
        ) {
            $bonusMap = is_array($propuesta['_bonus_voluntad'] ?? null) ? $propuesta['_bonus_voluntad'] : [];
            $b = (int) ($bonusMap[$residenteId] ?? 0);
            if ($b > 0) {
                return $b;
            }
            return (int) CalibracionConfig::get($cal, 'peticiones_pueblo.bonus_nucleo_modificado', 30);
        }
        $bonusMap = is_array($propuesta['_bonus_voluntad'] ?? null) ? $propuesta['_bonus_voluntad'] : [];
        return (int) ($bonusMap[$residenteId] ?? 0);
    }
}
