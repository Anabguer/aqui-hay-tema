<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * FASE 1 ÔÇö Iniciativa rom├íntica aut├│noma tras se├▒al (flechazo/til├¡n).
 *
 * NO es un segundo motor de romance: compone piezas can├│nicas existentes.
 *   - Condici├│n:      SenalRomantica::desdeHacia (AÔåÆB; unilateral basta para INTENTAR)
 *   - Elegibilidad:   RomanceElegibilidad::par + ParentescoVeto (orientaci├│n/dealbreakers/edad)
 *   - Idempotencia:   hito PRIMERA_CITA / encuentro primera_cita activo del par
 *   - Cooldowns:      PropuestaCooldown (par+tipo) y MemoriaEventos por familia
 *   - Voluntad:       VoluntadPonderadaEvaluator para AMBOS + resoluci├│n can├│nica
 *                     media_geometrica p_plan=ÔêÜ(pA┬ÀpB) con tirada ├║nica y atribuci├│n
 *                     al de menor p (canon 20/08, espejo de PropuestaEncuentroEngine).
 *   - Rechazos:       RechazoMemoria::registrar (cooldown, erosi├│n, tristeza, hito)
 *   - Agenda/lugar:   AgendaConjunta::primeraFranja sobre lugares desbloqueados
 *   - Encuentro:      EncuentroEngine::programar tipo CAN├ôNICO 'primera_cita',
 *                     intencion='autonomo_npc' (no pisa planes del jugador:
 *                     primeraFranja respeta reservas existentes).
 *
 * La iniciativa NO garantiza aceptaci├│n: la voluntad del receptor manda.
 */
final class IniciativaRomantica
{
    private const TIPO = 'primera_cita';
    private const LOG_MAX = 500;

    public static function ensure(array &$partida): void
    {
        $partida['iniciativa_romantica_log'] ??= [];
    }

    /**
     * ┬┐El par ya tiene primera cita hecha (hito) o en marcha (encuentro activo)?
     */
    public static function primeraCitaEnMarchaOHecha(array $partida, string $a, string $b): bool
    {
        if (SenalRomantica::yaHuboPrimeraCita($partida, $a, $b)) {
            return true;
        }
        foreach ($partida['encuentros'] ?? [] as $e) {
            if (!is_array($e) || (($e['tipo'] ?? '') !== self::TIPO)) {
                continue;
            }
            $parts = is_array($e['participantes'] ?? null) ? $e['participantes'] : [];
            if (count($parts) < 2) {
                continue;
            }
            if (in_array($a, $parts, true)
                && in_array($b, $parts, true)
                && in_array((string) ($e['estado'] ?? ''), ['programado', 'en_curso', 'pendiente'], true)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Intento de primera cita aut├│noma de $desde hacia $hacia.
     * Nunca lanza; devuelve resumen para trazabilidad/tests.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function intentarPrimeraCita(
        array &$partida,
        string $desde,
        string $hacia,
        array $cal,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);

        if (TutorialPrimerosPasos::bloqueaAutonomiaSobreParejaMision1($partida, $desde, $hacia)) {
            return self::fin($partida, 'tutorial_reserva_pareja_m1', $desde, $hacia);
        }

        // ---- gates de elegibilidad (sin RNG) ----
        $gate = 'ok';
        while (true) {
            if ($desde === '' || $hacia === '' || $desde === $hacia) {
                $gate = 'par_invalido';
                break;
            }
            if (!RelacionEngine::seConocen($partida, $desde, $hacia)) {
                $gate = 'sin_conocerse';
                break;
            }
            if (ParentescoVeto::bloqueaRomance($partida, $desde, $hacia, $cal)) {
                $gate = 'parentesco_veto';
                break;
            }
            $el = RomanceElegibilidad::par($partida, $desde, $hacia, $cal);
            if (empty($el['ok'])) {
                $gate = 'no_elegible_' . (string) ($el['motivo'] ?? '?');
                break;
            }
            $est = ParejaEngine::estado($partida, $desde, $hacia);
            if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
                $gate = 'ya_pareja_o_crisis';
                break;
            }
            $senal = SenalRomantica::desdeHacia($partida, $desde, $hacia, $cal);
            if (empty($senal['ok'])) {
                $gate = 'sin_senal';
                break;
            }
            if (self::primeraCitaEnMarchaOHecha($partida, $desde, $hacia)) {
                $gate = 'primera_cita_ya';
                break;
            }
            if (PropuestaCooldown::activo($partida, $desde, $hacia, self::TIPO, $cal)) {
                $gate = 'cooldown_propuesta';
                break;
            }
            if (MemoriaEventos::enCooldown($partida, 'romance_accion', [$desde, $hacia], $cal)) {
                $gate = 'cooldown_familia';
                break;
            }
            break;
        }
        if ($gate !== 'ok') {
            return self::fin($partida, 'gate_' . $gate, $desde, $hacia);
        }

        // ---- voluntad de AMBOS (evaluaci├│n individual; media_geom├®trica difiere la tirada) ----
        $prop = [
            'participantes' => [$desde, $hacia],
            'tipo' => self::TIPO,
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
            // Clase cooldown dentro del evaluador: NO es un rechazo real (canon:
            // RechazoMemoria excluye agenda/cooldown). Solo corta el intento.
            if (($r['clase'] ?? '') === PropuestaEncuentro::CLASE_COOLDOWN
                || ($r['motivo_tecnico'] ?? '') === 'cooldown_propuesta'
            ) {
                return self::fin($partida, 'cooldown_en_voluntad', $desde, $hacia, ['quien' => $quien]);
            }
            // Rechazo duro expl├¡cito: canon de rechazos completo.
            $motivo = (string) ($r['motivo_tipo'] ?? 'banal');
            RechazoMemoria::registrar($partida, $quien, $otro, $motivo, $cal, self::TIPO);
            return self::fin($partida, 'rechazo_voluntad_' . $motivo, $desde, $hacia, ['quien_rechaza' => $quien]);
        }

        // ---- resoluci├│n conjunta canon 20/08: p_plan = ÔêÜ(pA┬ÀpB), tirada ├║nica ----
        $pA = (float) ($ra['p'] ?? 0);
        $pB = (float) ($rb['p'] ?? 0);
        $pPlan = sqrt(max(0.0, $pA) * max(0.0, $pB));
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $pPlan)) {
            // El de menor p "planta"; el otro habr├¡a aceptado a nivel individual.
            $quienRechaza = $pB < $pA ? $hacia : $desde;
            $otro = $quienRechaza === $desde ? $hacia : $desde;
            $motivo = VoluntadPonderadaEvaluator::motivoRechazoPublic($partida, $quienRechaza, $otro, $cal);
            RechazoMemoria::registrar($partida, $quienRechaza, $otro, $motivo, $cal, self::TIPO);
            return self::fin($partida, 'plan_geom_rechazado_' . $motivo, $desde, $hacia, [
                'quien_rechaza' => $quienRechaza,
                'p_plan' => round($pPlan, 4),
            ]);
        }

        // ---- franja conjunta futura (agenda/sue├▒o/trabajo/doble reserva/lugar abierto) ----
        $ops = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if (!is_array($ops) || $ops === []) {
            $ops = ['lug_cafeteria', 'lug_parque'];
        }
        $franja = null;
        $lugarElegido = null;
        foreach ($ops as $lid) {
            if (!is_string($lid) || $lid === '') {
                continue;
            }
            $attr = LugarAtributos::de($lid);
            $f = AgendaConjunta::primeraFranja(
                $partida,
                [$desde, $hacia],
                max(1, (int) ($attr['horas'] ?? 1)),
                9,
                22,
                (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                3,
                $lid
            );
            if (!empty($f['ok'])) {
                // La franja valida agenda/doble reserva; la apertura del lugar se
                // verifica aqu├¡ para no llegar a programar con LUGAR_CERRADO.
                $horaF = (int) ($f['hora'] ?? -1);
                if ($horaF < 0 || !ComplejoCatalog::estaAbierto($lid, $horaF)) {
                    continue;
                }
                $franja = $f;
                $lugarElegido = $lid;
                break;
            }
        }
        if ($franja === null) {
            return self::fin($partida, 'sin_franja_agenda', $desde, $hacia);
        }

        // ---- encuentro tipo CAN├ôNICO primera_cita ----
        $r = EncuentroEngine::programar(
            $partida,
            [$desde, $hacia],
            (int) $franja['dia'],
            (int) $franja['hora'],
            self::TIPO,
            $lugarElegido,
            null,
            $logger,
            false
        );
        if (!($r['ok'] ?? false)) {
            return self::fin($partida, 'error_programar_' . (string) ($r['error'] ?? '?'), $desde, $hacia);
        }
        if (isset($r['encuentro']['id'])) {
            foreach ($partida['encuentros'] as $i => $enc) {
                if (($enc['id'] ?? '') === $r['encuentro']['id']) {
                    $partida['encuentros'][$i]['intencion'] = 'autonomo_npc';
                }
            }
        }
        return self::fin($partida, 'primera_cita_agendada', $desde, $hacia, [
            'programado_dia' => (int) $franja['dia'],
            'programado_hora' => (int) $franja['hora'],
            'lugar' => $lugarElegido,
            'p_plan' => round($pPlan, 4),
        ]);
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
        $partida['iniciativa_romantica_log'][] = $row;
        if (count($partida['iniciativa_romantica_log']) > self::LOG_MAX) {
            $partida['iniciativa_romantica_log'] = array_slice($partida['iniciativa_romantica_log'], -self::LOG_MAX);
        }
        return array_merge(['ok' => str_starts_with($resultado, 'primera_cita_agendada')], $row);
    }
}
