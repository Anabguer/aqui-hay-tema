<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * A1 — Iniciativa social autónoma deliberada NPC→NPC (NO romántica).
 *
 * Compone piezas canónicas existentes:
 *   - Elegibilidad:   relación social / conocerse; bloqueo explícito de romance
 *   - Voluntad:       VoluntadPonderadaEvaluator + resolución media_geométrica
 *   - Lugar:          LugarAutonomo::elegir (ConocimientoNpc vía $otro)
 *   - Agenda:         AgendaConjunta::primeraFranja
 *   - Encuentro:      EncuentroEngine::programar tipo conocerse|quedar,
 *                     intencion='autonomo_npc_social', sin presupuesto Celestine
 *
 * NO es intervención del jugador ni petición de Mensajitos.
 */
final class IniciativaSocial
{
    private const TIPOS_SOCIALES = ['conocerse', 'quedar', 'cita'];
    private const INTENCION = 'autonomo_npc_social';
    private const LOG_MAX = 500;

    public static function ensure(array &$partida): void
    {
        $partida['iniciativa_social_log'] ??= [];
    }

    /**
     * Tick horario: quizá un residente toma iniciativa social deliberada.
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function quizasDelTick(
        array &$partida,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger = null
    ): ?array {
        if (!FeatureConfig::isEnabled($partida, 'npc_autonomy_enabled')) {
            return null;
        }
        $n = count($partida['residentes'] ?? []);
        $k = (float) CalibracionConfig::get($cal, 'iniciativa_social.cupo_dia_sqrt', 0.45);
        $off = (float) CalibracionConfig::get($cal, 'iniciativa_social.cupo_dia_offset', 0.25);
        $cupoDia = (int) max(0, round($k * sqrt(max(1, $n)) + $off));
        if ($cupoDia <= 0) {
            return null;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hechas = 0;
        foreach ($partida['iniciativa_social_log'] ?? [] as $ev) {
            if ((int) ($ev['dia'] ?? 0) === $dia && str_starts_with((string) ($ev['resultado'] ?? ''), 'quedada_agendada')) {
                $hechas++;
            }
        }
        if ($hechas >= $cupoDia) {
            return null;
        }
        $prob = (float) CalibracionConfig::get($cal, 'iniciativa_social.prob_por_tick', 0.08);
        if ($rng->nextFloat() > $prob) {
            return null;
        }
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $desde = self::elegirIniciador($partida, $cal, $rng, $dia, $hora);
        if ($desde === null) {
            return null;
        }
        $hacia = self::elegirObjetivo($partida, $desde, $cal, $rng, $dia, $hora);
        if ($hacia === null) {
            return null;
        }
        return self::intentarQuedada($partida, $desde, $hacia, $cal, $catalog, $rng, $logger);
    }

    /**
     * Intento deliberado de $desde hacia $hacia. Nunca lanza.
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function intentarQuedada(
        array &$partida,
        string $desde,
        string $hacia,
        array $cal,
        ?Catalog $catalog = null,
        ?RngService $rng = null,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);
        $rng = $rng ?? RngService::fromPartida($partida);

        if (TutorialPrimerosPasos::bloqueaAutonomiaSobreParejaMision1($partida, $desde, $hacia)) {
            return self::fin($partida, 'tutorial_reserva_pareja_m1', $desde, $hacia);
        }

        $gate = 'ok';
        $tipo = 'conocerse';
        while (true) {
            if ($desde === '' || $hacia === '' || $desde === $hacia) {
                $gate = 'par_invalido';
                break;
            }
            if (self::tieneTensionRomantica($partida, $desde, $hacia, $cal)) {
                $gate = 'tension_romantica';
                break;
            }
            $tipo = self::tipoSocial($partida, $desde, $hacia);
            if (!in_array($tipo, self::TIPOS_SOCIALES, true)) {
                $gate = 'tipo_no_social';
                break;
            }
            if (!PropuestaNivel::permite($partida, $desde, $hacia, $tipo, $cal)) {
                $gate = 'tipo_no_permitido';
                break;
            }
            if (self::encuentroSocialEnMarcha($partida, $desde, $hacia)) {
                $gate = 'encuentro_ya';
                break;
            }
            if (PropuestaCooldown::activo($partida, $desde, $hacia, $tipo, $cal)) {
                $gate = 'cooldown_propuesta';
                break;
            }
            if (MemoriaEventos::enCooldown($partida, 'iniciativa_social', [$desde, $hacia], $cal)) {
                $gate = 'cooldown_familia';
                break;
            }
            break;
        }
        if ($gate !== 'ok') {
            return self::fin($partida, 'gate_' . $gate, $desde, $hacia, ['tipo' => $tipo]);
        }

        $prop = [
            'participantes' => [$desde, $hacia],
            'tipo' => $tipo,
            'lugar' => null,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 12),
        ];
        $vol = new VoluntadPonderadaEvaluator($cal);
        $ra = $vol->evaluar($partida, $prop, $desde);
        $rb = $vol->evaluar($partida, $prop, $hacia);

        foreach ([[$ra, $desde, $hacia], [$rb, $hacia, $desde]] as [$r, $quien, $otro]) {
            if (($r['decision'] ?? '') !== PropuestaEncuentro::DECISION_RECHAZA) {
                continue;
            }
            if (($r['clase'] ?? '') === PropuestaEncuentro::CLASE_COOLDOWN
                || ($r['motivo_tecnico'] ?? '') === 'cooldown_propuesta'
            ) {
                return self::fin($partida, 'cooldown_en_voluntad', $desde, $hacia, ['quien' => $quien, 'tipo' => $tipo]);
            }
            $motivo = (string) ($r['motivo_tipo'] ?? 'banal');
            RechazoMemoria::registrar($partida, $quien, $otro, $motivo, $cal, $tipo);
            return self::fin($partida, 'rechazo_voluntad_' . $motivo, $desde, $hacia, [
                'quien_rechaza' => $quien,
                'tipo' => $tipo,
            ]);
        }

        $pA = (float) ($ra['p'] ?? 0);
        $pB = (float) ($rb['p'] ?? 0);
        $pPlan = sqrt(max(0.0, $pA) * max(0.0, $pB));
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $pPlan)) {
            $quienRechaza = $pB < $pA ? $hacia : $desde;
            $otro = $quienRechaza === $desde ? $hacia : $desde;
            $motivo = VoluntadPonderadaEvaluator::motivoRechazoPublic($partida, $quienRechaza, $otro, $cal);
            RechazoMemoria::registrar($partida, $quienRechaza, $otro, $motivo, $cal, $tipo);
            return self::fin($partida, 'plan_geom_rechazado_' . $motivo, $desde, $hacia, [
                'quien_rechaza' => $quienRechaza,
                'p_plan' => round($pPlan, 4),
                'tipo' => $tipo,
            ]);
        }

        $ops = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if (!is_array($ops) || $ops === []) {
            $ops = ['lug_cafeteria', 'lug_parque'];
        }
        $lugarElegido = LugarAutonomo::elegir($partida, $desde, $hacia, $ops, $rng, $catalog, $cal);
        if ($lugarElegido === null || $lugarElegido === '') {
            $lugarElegido = (string) $ops[0];
        }
        $attr = LugarAtributos::de($lugarElegido);
        $franja = null;
        foreach ($ops as $lid) {
            if (!is_string($lid) || $lid === '') {
                continue;
            }
            $attrTry = LugarAtributos::de($lid);
            $f = AgendaConjunta::primeraFranja(
                $partida,
                [$desde, $hacia],
                max(1, (int) ($attrTry['horas'] ?? 1)),
                9,
                22,
                (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                3,
                $lid
            );
            if (empty($f['ok'])) {
                continue;
            }
            $horaF = (int) ($f['hora'] ?? -1);
            if ($horaF < 0 || !ComplejoCatalog::estaAbierto($lid, $horaF)) {
                continue;
            }
            $franja = $f;
            $lugarElegido = $lid;
            $attr = $attrTry;
            break;
        }
        if ($franja === null) {
            return self::fin($partida, 'sin_franja_agenda', $desde, $hacia, ['tipo' => $tipo]);
        }

        $usadasAntes = (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
        $r = EncuentroEngine::programar(
            $partida,
            [$desde, $hacia],
            (int) $franja['dia'],
            (int) $franja['hora'],
            $tipo,
            $lugarElegido,
            null,
            $logger,
            false
        );
        if (!($r['ok'] ?? false)) {
            return self::fin($partida, 'error_programar_' . (string) ($r['error'] ?? '?'), $desde, $hacia, ['tipo' => $tipo]);
        }
        if (isset($r['encuentro']['id'])) {
            foreach ($partida['encuentros'] as $i => $enc) {
                if (($enc['id'] ?? '') === $r['encuentro']['id']) {
                    $partida['encuentros'][$i]['intencion'] = self::INTENCION;
                    $partida['encuentros'][$i]['duracion_minutos'] = $attr['duracion_minutos'];
                    $partida['encuentros'][$i]['duracion_horas'] = $attr['horas'];
                    $partida['encuentros'][$i]['reserva_agenda'] = ['tipo' => 'encuentro', 'origen' => 'autonomo'];
                }
            }
        }
        MemoriaEventos::registrar($partida, 'iniciativa_social', [$desde, $hacia], null, $tipo);
        PropuestaCooldown::marcar($partida, $desde, $hacia, $tipo, $cal);

        return self::fin($partida, 'quedada_agendada', $desde, $hacia, [
            'programado_dia' => (int) $franja['dia'],
            'programado_hora' => (int) $franja['hora'],
            'lugar' => $lugarElegido,
            'tipo' => $tipo,
            'p_plan' => round($pPlan, 4),
            'intervenciones_celeste_antes' => $usadasAntes,
            'intervenciones_celeste_despues' => (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0),
        ]);
    }

    public static function encuentroSocialEnMarcha(array $partida, string $a, string $b): bool
    {
        foreach ($partida['encuentros'] ?? [] as $e) {
            if (!is_array($e)) {
                continue;
            }
            $tipo = (string) ($e['tipo'] ?? '');
            if (!in_array($tipo, self::TIPOS_SOCIALES, true)) {
                continue;
            }
            if (!in_array((string) ($e['estado'] ?? ''), ['programado', 'en_curso', 'pendiente'], true)) {
                continue;
            }
            $parts = is_array($e['participantes'] ?? null) ? $e['participantes'] : [];
            if (in_array($a, $parts, true) && in_array($b, $parts, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function tieneTensionRomantica(array $partida, string $a, string $b, array $cal): bool
    {
        $est = ParejaEngine::estado($partida, $a, $b);
        if ($est === ParejaEngine::PAREJA) {
            return false;
        }
        if ($est === ParejaEngine::CRISIS) {
            return true;
        }
        $umbral = (int) CalibracionConfig::get($cal, 'iniciativa_social.romance_umbral_bloqueo', 22);
        $romAb = (int) (RelacionEngine::romanceHacia($partida, $a, $b) ?? 0);
        $romBa = (int) (RelacionEngine::romanceHacia($partida, $b, $a) ?? 0);
        if ($romAb >= $umbral || $romBa >= $umbral) {
            return true;
        }
        if (!empty(SenalRomantica::desdeHacia($partida, $a, $b, $cal)['ok'])) {
            return true;
        }
        if (!empty(SenalRomantica::desdeHacia($partida, $b, $a, $cal)['ok'])) {
            return true;
        }
        return false;
    }

    private static function tipoSocial(array $partida, string $desde, string $hacia): string
    {
        $est = ParejaEngine::estado($partida, $desde, $hacia);
        if ($est === ParejaEngine::PAREJA) {
            return 'cita';
        }
        return RelacionEngine::seConocen($partida, $desde, $hacia) ? 'quedar' : 'conocerse';
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function elegirIniciador(array $partida, array $cal, RngService $rng, int $dia, int $hora): ?string
    {
        $pesos = [];
        foreach (array_keys($partida['residentes'] ?? []) as $id) {
            $id = (string) $id;
            $disp = AgendaEngine::estaDisponible($partida, $id, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                continue;
            }
            $w = 1.0;
            $emo = (string) ($partida['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
            $w += ((int) EstadoEmocional::modificadores($emo, $cal)['iniciativa_social']) / 35.0;
            $ult = (int) ($partida['residentes'][$id]['runtime']['ultimo_protagonismo_dia'] ?? 0);
            if ($ult === 0 || ($dia - $ult) >= 3) {
                $w *= (float) CalibracionConfig::get($cal, 'autonomia.poco_activo_bonus', 1.6);
            }
            $pesos[] = ['id' => $id, 'w' => max(0.05, $w)];
        }
        return self::pickPeso($pesos, $rng);
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function elegirObjetivo(
        array $partida,
        string $desde,
        array $cal,
        RngService $rng,
        int $dia,
        int $hora
    ): ?string {
        $pesos = [];
        foreach (array_keys($partida['residentes'] ?? []) as $id) {
            $id = (string) $id;
            if ($id === $desde) {
                continue;
            }
            $disp = AgendaEngine::estaDisponible($partida, $id, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                continue;
            }
            if (self::tieneTensionRomantica($partida, $desde, $id, $cal)) {
                continue;
            }
            if (self::encuentroSocialEnMarcha($partida, $desde, $id)) {
                continue;
            }
            $w = 1.0;
            if (RelacionEngine::seConocen($partida, $desde, $id)) {
                $w += 2.5;
                $w += abs(RelacionEngine::valorSocialHacia($partida, $desde, $id)) / 28.0;
            } else {
                $w += 0.6;
            }
            $pesos[] = ['id' => $id, 'w' => max(0.05, $w)];
        }
        return self::pickPeso($pesos, $rng);
    }

    /**
     * @param list<array<string, mixed>> $pesos
     */
    private static function pickPeso(array $pesos, RngService $rng): ?string
    {
        if ($pesos === []) {
            return null;
        }
        $sum = 0.0;
        foreach ($pesos as $p) {
            $sum += (float) ($p['w'] ?? 0);
        }
        if ($sum <= 0) {
            return (string) ($pesos[0]['id'] ?? null);
        }
        $pick = $rng->nextFloat() * $sum;
        $acc = 0.0;
        foreach ($pesos as $p) {
            $acc += (float) ($p['w'] ?? 0);
            if ($pick <= $acc) {
                return (string) ($p['id'] ?? '');
            }
        }
        return (string) ($pesos[count($pesos) - 1]['id'] ?? null);
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function fin(array &$partida, string $resultado, string $desde, string $hacia, array $extra = []): array
    {
        self::ensure($partida);
        $row = array_merge([
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'desde' => $desde,
            'hacia' => $hacia,
            'resultado' => $resultado,
        ], $extra);
        $partida['iniciativa_social_log'][] = $row;
        if (count($partida['iniciativa_social_log']) > self::LOG_MAX) {
            $partida['iniciativa_social_log'] = array_slice($partida['iniciativa_social_log'], -self::LOG_MAX);
        }
        return array_merge(['ok' => str_starts_with($resultado, 'quedada_agendada')], $row);
    }
}
