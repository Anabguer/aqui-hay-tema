<?php
declare(strict_types=1);

namespace AquiHayTema\Engine\Voluntad;

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\ConsejoEngine;
use AquiHayTema\Engine\CopyVoluntad;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\PlanAfinidad;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RechazoMemoria;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SenalRomantica;

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
                'motivo_tipo' => 'cooldown',
                'copy_id' => $copyCd,
                'score' => null,
                'p' => 0.0,
                '_bloqueado_decision' => false,
            ];
        }

        $desglose = self::desglose($partida, $propuesta, $residenteId, $otro, $cal);
        $score = (int) ($desglose['score'] ?? 0);
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
        $tipo = PropuestaNivel::aliasTipo((string) ($propuesta['tipo'] ?? ''));
        // P2: peso del conflicto SOLO en tipos de cita jugables (primera_cita, cita).
        // No aplica a 'romantico' legacy ni a quedar/conocerse/pareja.
        $aplicaConfMultCita = $tipo === PropuestaNivel::PRIMERA_CITA || $tipo === PropuestaNivel::CITA;
        $confMultCita = $aplicaConfMultCita
            ? max(1.0, (float) CalibracionConfig::get($cal, 'voluntad.conflicto_mult_cita', 1.0))
            : 1.0;
        // Estado emocional: vigencia (anti-stale) y dirección del enfado.
        $emoRow = is_array(($partida['residentes'][$quien]['runtime']['estado_emocional'] ?? null))
            ? $partida['residentes'][$quien]['runtime']['estado_emocional']
            : [];
        $emo = EstadoEmocional::canonId((string) ($emoRow['id'] ?? EstadoEmocional::NEUTRO));
        $emoVigente = true;
        if ($emo !== EstadoEmocional::NEUTRO
            && isset($emoRow['hasta'])
            && is_array($emoRow['hasta'])
            && EstadoEmocional::vencido($emoRow['hasta'], $partida['reloj'] ?? [])
        ) {
            // Vencido respecto al reloj actual: ni penaliza ni bonifica voluntad.
            // No muta el save (la expiración canónica sigue en RelojOperations).
            $emoVigente = false;
        }
        // Mods emocionales: si el estado no está vigente, se computan como neutro.
        $mods = $emoVigente
            ? EstadoEmocional::modificadores($emo, $cal)
            : EstadoEmocional::modificadores(EstadoEmocional::NEUTRO, $cal);
        $modEmo = (int) ($mods['aceptar_planes'] ?? 0);
        // Enfado direccional: solo con datos reales ya persistidos.
        // 'dirigida' (contra la persona del plan) o 'indeterminada' -> penalización completa.
        // 'ajena' (demostrablemente causada por otra persona) -> penalización mitigada.
        $emocionDireccion = null;
        if ($emoVigente && $emo === EstadoEmocional::ENFADADO) {
            $emocionDireccion = self::resolverEnfado($partida, $emoRow, $otro);
            if ($emocionDireccion === 'ajena') {
                $mitigada = CalibracionConfig::get($cal, 'emociones_v1.enfadado_ajeno_aceptar_planes', null);
                if (is_numeric($mitigada)) {
                    $modEmo = (int) $mitigada;
                }
            }
        }
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
                $modConf = -(int) round(((int) $conf) * $confMultCita);
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
        $modRomTipo = 0;
        if (PropuestaNivel::esTipoCita($tipo) || $tipo === 'pareja') {
            $modRomTipo = (int) (($mods['iniciativa_romantica'] ?? 0) / 2);
            $s += $modRomTipo;
        }
        $modTipo = self::modTipo($tipo, $cal);
        $s += $modTipo;
        // P2: Bonus de primera cita SOLO con señal romántica real en AMBAS direcciones
        // (canon: SenalRomantica::desdeHacia; flechazo o romance >= tilín).
        $bonusReciprocaPC = 0;
        if ($otro !== '' && $tipo === PropuestaNivel::PRIMERA_CITA) {
            $brCfg = (int) CalibracionConfig::get($cal, 'voluntad.bonus_primera_cita_reciproca', 0);
            if ($brCfg > 0
                && !empty(SenalRomantica::desdeHacia($partida, $quien, $otro, $cal)['ok'])
                && !empty(SenalRomantica::desdeHacia($partida, $otro, $quien, $cal)['ok'])
            ) {
                $bonusReciprocaPC = $brCfg;
                $s += $bonusReciprocaPC;
            }
        }

        // Continuidad reciente: bonus por encuentros positivos recientes con esta persona
        $modContinuidad = 0;
        if ($otro !== '') {
            $modContinuidad = self::calcularContinuidadReciente($partida, $quien, $otro, $cal);
            $s += $modContinuidad;
        }

        // B4 núcleo modificado: Celestine cumple el núcleo de una petición pero
        // añadió compañía no pedida. Bonus fuerte, nunca garantía.
        $modPeticionNucleo = 0;
        $bonusMap = is_array($propuesta['_bonus_voluntad'] ?? null) ? $propuesta['_bonus_voluntad'] : [];
        if (isset($bonusMap[$quien]) && is_numeric($bonusMap[$quien])) {
            $modPeticionNucleo = (int) round((float) $bonusMap[$quien]);
            $s += $modPeticionNucleo;
        }
        $score = max(0, min(100, $s));
        return [
            'score' => $score,
            'bonus_peticion_nucleo' => $modPeticionNucleo,
            'base' => $base,
            'estado_emocional' => $emo,
            'estado_emocional_vigente' => $emoVigente,
            'emocion_direccion' => $emocionDireccion,
            'mod_estado_emocional_aceptar_planes' => $modEmo,
            'relacion_previa_se_conocen' => $conocen,
            'mod_aun_no_se_conocen' => $modConocer,
            'social' => $soc,
            'mod_social' => $modSoc,
            'romance' => $rom,
            'mod_romance' => $modRom,
            'conflicto' => $conf,
            'mod_conflicto' => $modConf,
            'conflicto_mult_cita' => $confMultCita,
            'rechazos_previos' => $nRech,
            'mod_rechazos' => $modRech,
            'mod_consejo' => $modConsejo,
            'lugar' => $lugar,
            'afinidad_aporte' => $aporteAfin,
            'afinidad_penalizacion' => $penAfin,
            'tipo' => $tipo,
            'mod_tipo' => $modTipo,
            'bonus_primera_cita_reciproca' => $bonusReciprocaPC,
            'mod_continuidad_reciente' => $modContinuidad,
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
     * Dirección del enfado usando SOLO datos reales ya persistidos.
     *
     * - contexto.hacia presente -> 'dirigida' si apunta a $otro, 'ajena' si no.
     * - origen 'encuentro' + contexto.encuentro_id resoluble en partida['encuentros']
     *   -> 'dirigida' si $otro participó, 'ajena' si no.
     * - cualquier otra cosa -> 'indeterminada' (conservador: no se inventa culpable).
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $emoRow
     */
    private static function resolverEnfado(array $partida, array $emoRow, string $otro): string
    {
        if ($otro === '') {
            return 'indeterminada';
        }
        $ctx = is_array($emoRow['contexto'] ?? null) ? $emoRow['contexto'] : [];
        $hacia = (string) ($ctx['hacia'] ?? '');
        if ($hacia !== '') {
            return $hacia === $otro ? 'dirigida' : 'ajena';
        }
        if ((string) ($emoRow['origen'] ?? '') !== 'encuentro') {
            return 'indeterminada';
        }
        $encId = (string) ($ctx['encuentro_id'] ?? '');
        if ($encId === '') {
            return 'indeterminada';
        }
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc) || (string) ($enc['id'] ?? '') !== $encId) {
                continue;
            }
            $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            if ($parts === []) {
                return 'indeterminada';
            }
            foreach ($parts as $pid) {
                if ((string) $pid === $otro) {
                    return 'dirigida';
                }
            }
            return 'ajena';
        }
        // Encuentro origen no encontrado: comportamiento conservador.
        return 'indeterminada';
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
     * Calcula el bonus de continuidad reciente basado en memoria_eventos.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return int
     */
    private static function calcularContinuidadReciente(array $partida, string $quien, string $otro, array $cal): int
    {
        $cfg = CalibracionConfig::get($cal, 'voluntad.continuidad_reciente', []);
        if (empty($cfg['activo'])) {
            return 0;
        }

        $bonusMuyBien = (int) ($cfg['bonus_muy_bien'] ?? 10);
        $bonusBien = (int) ($cfg['bonus_bien'] ?? 5);
        $bonusDosBuenos = (int) ($cfg['bonus_dos_buenos_48h'] ?? 3);
        $halfLife = (float) ($cfg['decay_halflife_horas'] ?? 12);
        $maxBonus = (int) ($cfg['max_bonus'] ?? 12);
        $corteSiMalo = (bool) ($cfg['corte_si_ultimo_malo'] ?? true);
        $mirarUltimos = (int) ($cfg['mirar_ultimos'] ?? 5);

        $recientes = MemoriaEventos::recientes($partida, [$quien, $otro], $mirarUltimos);
        if ($recientes === []) {
            return 0;
        }

        // Filtrar solo encuentros (familia 'encuentro')
        $encuentros = array_filter($recientes, static function ($ev) {
            return ($ev['familia'] ?? '') === 'encuentro';
        });

        if ($encuentros === []) {
            return 0;
        }

        // Verificar si hay un mal/muy_mal POSTERIOR al último bueno
        // (ordenados por recencia, el primero es el más reciente = índice 0)
        $ultimoBueno = null;
        $ultimoBuenoIdx = -1;
        $hayMaloPosterior = false;

        // Primera pasada: encontrar el bueno más reciente (menor índice)
        foreach ($encuentros as $idx => $ev) {
            $res = $ev['resultado_experiencia'] ?? '';
            if ($res === 'muy_bien' || $res === 'bien') {
                $ultimoBueno = $res;
                $ultimoBuenoIdx = $idx;
                break; // El primero que encontremos es el más reciente
            }
        }

        // Segunda pasada: verificar si hay mal/muy_mal ANTES (más reciente) que el bueno
        if ($corteSiMalo && $ultimoBueno !== null) {
            for ($idx = 0; $idx < $ultimoBuenoIdx; $idx++) {
                $res = $encuentros[$idx]['resultado_experiencia'] ?? '';
                if ($res === 'mal' || $res === 'muy_mal') {
                    $hayMaloPosterior = true;
                    break;
                }
            }
        }

        if ($hayMaloPosterior || $ultimoBueno === null) {
            return 0;
        }

        // Calcular horas desde el último bueno
        $reloj = $partida['reloj'] ?? [];
        $ahora = ((int) ($reloj['dia_pueblo'] ?? 1)) * 24 + (int) ($reloj['hora_actual'] ?? 0);
        $evBueno = $encuentros[$ultimoBuenoIdx];
        $entonces = ((int) ($evBueno['dia'] ?? 0)) * 24 + (int) ($evBueno['hora'] ?? 0);
        $horas = max(0, $ahora - $entonces);

        // Bonus base
        $base = $ultimoBueno === 'muy_bien' ? $bonusMuyBien : $bonusBien;

        // Bonus por dos buenos en 48h (el siguiente más antiguo)
        if ($ultimoBuenoIdx + 1 < count($encuentros)) {
            $siguiente = $encuentros[$ultimoBuenoIdx + 1];
            $resSig = $siguiente['resultado_experiencia'] ?? '';
            if (($resSig === 'muy_bien' || $resSig === 'bien') && $horas <= 48) {
                $entoncesSig = ((int) ($siguiente['dia'] ?? 0)) * 24 + (int) ($siguiente['hora'] ?? 0);
                $horasEntre = $entonces - $entoncesSig;
                if ($horasEntre <= 48) {
                    $base += $bonusDosBuenos;
                }
            }
        }

        // Decay exponencial
        $decay = pow(0.5, $horas / $halfLife);
        $bonus = (int) round($base * $decay);

        return min($maxBonus, max(0, $bonus));
    }
}
