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
    private const TIPO_CITA = 'cita';
    private const TIPO_DECLARACION = 'declaracion';
    private const LOG_MAX = 500;

    /** Experiencias que cortan la continuidad autónoma (canon: corte_si_ultimo_malo). */
    private const EXPERIENCIAS_MALAS = ['mal', 'muy_mal'];

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
            // R7 · Post-ruptura: sin citas románticas autónomas entre exes.
            if ($est === ParejaEngine::EX) {
                $gate = 'ex_sin_vuelta';
                break;
            }
            // R1: exclusividad — nadie inicia nada romántico nuevo emparejado con un tercero.
            if (SenalRomantica::enParejaConTercero($partida, $desde, $hacia)) {
                $gate = 'en_pareja_con_otro';
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
        // R1/INC-1: la cita autónoma NPC NO gasta el cupo de intervenciones de
        // Celestine (canon RELACIONES_Y_CITAS; alineado con F2A y A1).
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
        $ok = str_starts_with($resultado, 'primera_cita_agendada')
            || str_starts_with($resultado, 'cita_autonoma_agendada');
        return array_merge(['ok' => $ok], $row);
    }

    // ==================================================================
    // FASE 2A — Continuidad: segundas/siguientes citas autónomas.
    //
    // NO forma parejas, NO declara, NO toca ParejaEngine.
    // Reutiliza: SenalRomantica, RomanceElegibilidad, ParentescoVeto,
    // VoluntadPonderadaEvaluator (media geométrica, UNA tirada),
    // PropuestaCooldown, RechazoMemoria, AgendaConjunta, EncuentroEngine
    // (tipo canónico 'cita', intencion='autonomo_npc') y cooldowns vivos.
    //
    // Anti-aceleración: el gap mínimo entre citas románticas resueltas del
    // par REUTILIZA el contrato canónico cooldowns.por_familia.romance (48 h)
    // — no introduce cifras nuevas. El disparador NUNCA crea la cita en el
    // mismo tick de la resolución: deja un marcador con hora de intento
    // >= última cita + gap; el consumidor corre en ticks posteriores.
    // ==================================================================

    public static function gapMinimoCitas(array $cal): int
    {
        return max(1, (int) CalibracionConfig::get($cal, 'cooldowns.por_familia.romance', 48));
    }

    public static function ensureContinuidad(array &$partida): void
    {
        $partida['continuidad_romantica'] ??= [];
    }

    private static function ahoraAbs(array $partida): int
    {
        return ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
    }

    /**
     * Marcador de continuidad tras resolver una cita romántica del par.
     * Idempotente por par (reemplaza). No programa nada: solo fecha el intento.
     *
     * R2 · Si el par es DECLARABLE (elegibilidad determinista completa) el
     * marcador pasa a accion='declaracion' con la misma respiración temporal
     * (gap canónico de citas); si no, sigue siendo 'cita' (comportamiento F2A).
     *
     * @param array<string, mixed> $cal
     */
    public static function registrarContinuidadPostCita(
        array &$partida,
        string $a,
        string $b,
        ?string $resultadoExperiencia,
        array $cal = []
    ): void {
        if ($a === '' || $b === '' || $a === $b) {
            return;
        }
        self::ensureContinuidad($partida);
        $par = [$a, $b];
        sort($par);
        $parKey = $par[0] . '>' . $par[1];

        // R4 · Vida de pareja autónoma: la pareja sigue quedando con gap canónico
        // de la familia 'pareja' (36 h). En CRISIS/EX no hay citas recreativas.
        $est = ParejaEngine::estado($partida, $a, $b);
        if ($est === ParejaEngine::PAREJA && self::vidaParejaActiva($cal)) {
            $entry = [
                'par' => $par,
                'accion' => 'cita',
                'desde_abs' => self::ahoraAbs($partida) + self::gapParejaHoras($cal),
                'gap_horas' => self::gapParejaHoras($cal),
                'ultima_experiencia' => $resultadoExperiencia,
                'creado' => [
                    'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                    'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
                ],
            ];
            $resto = array_values(array_filter(
                $partida['continuidad_romantica'],
                static fn ($m) => is_array($m) && (($m['par'][0] ?? '') . '>' . ($m['par'][1] ?? '')) !== $parKey
            ));
            $resto[] = $entry;
            $partida['continuidad_romantica'] = $resto;
            return;
        }
        if ($est !== ParejaEngine::NINGUNA) {
            // CRISIS/EX: sin citas recreativas y SIN marcadores huérfanos previos.
            self::purgarMarcadoresPar($partida, $a, $b);
            return;
        }

        $accion = 'cita';
        $extraDecl = [];
        if (self::declaracionActiva($cal)) {
            $el = self::elegibilidadDeclaracion($partida, $a, $b, $cal);
            if (!empty($el['ok'])) {
                $accion = 'declaracion';
                $extraDecl = [
                    'declara' => (string) ($el['desde'] ?? ''),
                    'citas' => (int) ($el['citas'] ?? 0),
                ];
            }
        }
        $entry = array_merge([
            'par' => $par,
            'accion' => $accion,
            'desde_abs' => self::ahoraAbs($partida) + self::gapMinimoCitas($cal),
            'gap_horas' => self::gapMinimoCitas($cal),
            'ultima_experiencia' => $resultadoExperiencia,
            'creado' => [
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            ],
        ], $extraDecl);
        $resto = array_values(array_filter(
            $partida['continuidad_romantica'],
            static fn ($m) => is_array($m) && (($m['par'][0] ?? '') . '>' . ($m['par'][1] ?? '')) !== $parKey
        ));
        $resto[] = $entry;
        $partida['continuidad_romantica'] = $resto;
    }

    /**
     * Última cita ROMÁNTICA RESUELTA del par, derivada de los encuentros reales
     * (única fuente de verdad: sin contadores paralelos).
     *
     * @return array{abs:int,tipo:string,experiencia:string}|null
     */
    private static function ultimaCitaResuelta(array $partida, string $a, string $b): ?array
    {
        $rank = ['muy_mal' => 0, 'mal' => 1, 'normal' => 2, 'bien' => 3, 'muy_bien' => 4];
        $mejor = null;
        foreach (($partida['encuentros'] ?? []) as $enc) {
            if (!is_array($enc) || ($enc['estado'] ?? '') !== 'terminado') {
                continue;
            }
            $tipo = (string) ($enc['tipo'] ?? '');
            if ($tipo !== self::TIPO && $tipo !== self::TIPO_CITA) {
                continue;
            }
            $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            if (!in_array($a, $parts, true) || !in_array($b, $parts, true)) {
                continue;
            }
            $abs = ((int) ($enc['dia'] ?? 0)) * 24 + (int) ($enc['hora'] ?? 0)
                + max(1, (int) ($enc['duracion_horas'] ?? 1));
            $res = is_array($enc['resultado'] ?? null) ? $enc['resultado'] : [];
            $expWorst = '';
            $rankWorst = 99;
            foreach ($parts as $pid) {
                $e = (string) (($res['por_participante'] ?? [])[(string) $pid]['resultado'] ?? '');
                if ($e === '') {
                    continue;
                }
                $r = $rank[$e] ?? 2;
                if ($r < $rankWorst) {
                    $rankWorst = $r;
                    $expWorst = $e;
                }
            }
            if ($mejor === null || $abs >= $mejor['abs']) {
                $mejor = ['abs' => $abs, 'tipo' => $tipo, 'experiencia' => $expWorst];
            }
        }
        return $mejor;
    }

    private static function citaEnMarchaDelPar(array $partida, string $a, string $b): bool
    {
        foreach (($partida['encuentros'] ?? []) as $e) {
            if (!is_array($e) || (($e['tipo'] ?? '') !== self::TIPO_CITA)) {
                continue;
            }
            $parts = is_array($e['participantes'] ?? null) ? $e['participantes'] : [];
            if (!in_array($a, $parts, true) || !in_array($b, $parts, true)) {
                continue;
            }
            if (in_array((string) ($e['estado'] ?? ''), ['programado', 'en_curso', 'pendiente'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * FASE 2A · Intento autónomo de SIGUIENTE cita del par (tipo 'cita').
     * Nunca lanza; devuelve resumen para trazabilidad/tests.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function intentarSiguienteCita(
        array &$partida,
        string $desde,
        string $hacia,
        array $cal,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);

        // ---- gates de continuidad (sin RNG) ----
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
            // R7 · Post-ruptura: sin citas románticas autónomas entre exes.
            if ($est === ParejaEngine::EX) {
                $gate = 'ex_sin_vuelta';
                break;
            }
            // R1: exclusividad — nadie inicia nada romántico nuevo emparejado con un tercero.
            if (SenalRomantica::enParejaConTercero($partida, $desde, $hacia)) {
                $gate = 'en_pareja_con_otro';
                break;
            }
            if (!SenalRomantica::yaHuboPrimeraCita($partida, $desde, $hacia)) {
                $gate = 'sin_primera_cita';
                break;
            }
            if (self::citaEnMarchaDelPar($partida, $desde, $hacia)) {
                $gate = 'cita_en_marcha';
                break;
            }
            $ultima = self::ultimaCitaResuelta($partida, $desde, $hacia);
            if ($ultima !== null && in_array($ultima['experiencia'], self::EXPERIENCIAS_MALAS, true)) {
                $gate = 'continuidad_ultima_experiencia_mala';
                break;
            }
            if ($ultima !== null) {
                $gap = self::gapMinimoCitas($cal);
                if ((self::ahoraAbs($partida) - $ultima['abs']) < $gap) {
                    $gate = 'cooldown_cita';
                    break;
                }
            }
            $senal = SenalRomantica::desdeHacia($partida, $desde, $hacia, $cal);
            if (empty($senal['ok'])) {
                $gate = 'sin_senal';
                break;
            }
            if (PropuestaCooldown::activo($partida, $desde, $hacia, self::TIPO_CITA, $cal)) {
                $gate = 'cooldown_propuesta';
                break;
            }
            break;
        }
        if ($gate !== 'ok') {
            return self::fin($partida, 'gate_' . $gate, $desde, $hacia);
        }

        // ---- voluntad de AMBOS (tipo 'cita'; media geométrica difiere la tirada) ----
        $prop = [
            'participantes' => [$desde, $hacia],
            'tipo' => self::TIPO_CITA,
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
                return self::fin($partida, 'cooldown_en_voluntad', $desde, $hacia, ['quien' => $quien]);
            }
            $motivo = (string) ($r['motivo_tipo'] ?? 'banal');
            RechazoMemoria::registrar($partida, $quien, $otro, $motivo, $cal, self::TIPO_CITA);
            return self::fin($partida, 'rechazo_voluntad_' . $motivo, $desde, $hacia, ['quien_rechaza' => $quien]);
        }

        // ---- resolución conjunta canon 20/08: p_plan = √(pA·pB), tirada única ----
        $pA = (float) ($ra['p'] ?? 0);
        $pB = (float) ($rb['p'] ?? 0);
        $pPlan = sqrt(max(0.0, $pA) * max(0.0, $pB));
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $pPlan)) {
            $quienRechaza = $pB < $pA ? $hacia : $desde;
            $otro = $quienRechaza === $desde ? $hacia : $desde;
            $motivo = VoluntadPonderadaEvaluator::motivoRechazoPublic($partida, $quienRechaza, $otro, $cal);
            RechazoMemoria::registrar($partida, $quienRechaza, $otro, $motivo, $cal, self::TIPO_CITA);
            return self::fin($partida, 'plan_geom_rechazado_' . $motivo, $desde, $hacia, [
                'quien_rechaza' => $quienRechaza,
                'p_plan' => round($pPlan, 4),
            ]);
        }

        // ---- franja conjunta futura (agenda/sueño/trabajo/doble reserva/lugar abierto) ----
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

        // ---- encuentro tipo CANÓNICO 'cita' (no primera_cita), sin cupo Celestine ----
        $r = EncuentroEngine::programar(
            $partida,
            [$desde, $hacia],
            (int) $franja['dia'],
            (int) $franja['hora'],
            self::TIPO_CITA,
            $lugarElegido,
            null,
            $logger,
            false
        );
        if (!($r['ok'] ?? false)) {
            return self::fin($partida, 'error_programar_' . (string) ($r['error'] ?? '?'), $desde, $hacia);
        }
        self::marcarAutonoma($partida, (string) ($r['encuentro']['id'] ?? ''));
        return self::fin($partida, 'cita_autonoma_agendada', $desde, $hacia, [
            'programado_dia' => (int) $franja['dia'],
            'programado_hora' => (int) $franja['hora'],
            'lugar' => $lugarElegido,
            'p_plan' => round($pPlan, 4),
        ]);
    }

    /**
     * R4 · Intento autónomo de cita de PAREJA (estado=pareja, vida_pareja_activa).
     * Mismo contrato de voluntad geométrica y agenda que la continuidad F2A;
     * gap canónico de la familia 'pareja'. Nunca forma/rompe nada.
     */
    public static function intentarCitaPareja(
        array &$partida,
        string $desde,
        string $hacia,
        array $cal,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);

        // ---- gates (sin RNG) ----
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
            $est = ParejaEngine::estado($partida, $desde, $hacia);
            if ($est !== ParejaEngine::PAREJA) {
                $gate = 'no_es_pareja';
                break;
            }
            if (SenalRomantica::enParejaConTercero($partida, $desde, $hacia)) {
                $gate = 'en_pareja_con_otro';
                break;
            }
            if (self::citaEnMarchaDelPar($partida, $desde, $hacia)) {
                $gate = 'cita_en_marcha';
                break;
            }
            $ultima = self::ultimaCitaResuelta($partida, $desde, $hacia);
            if ($ultima !== null
                && (self::ahoraAbs($partida) - $ultima['abs']) < self::gapParejaHoras($cal)) {
                $gate = 'cooldown_cita_pareja';
                break;
            }
            if (PropuestaCooldown::activo($partida, $desde, $hacia, self::TIPO_CITA, $cal)) {
                $gate = 'cooldown_propuesta';
                break;
            }
            break;
        }
        if ($gate !== 'ok') {
            return self::fin($partida, 'gate_' . $gate, $desde, $hacia);
        }

        // ---- voluntad de AMBOS (tipo 'cita'; conflicto/emocional ya penalizan) ----
        $prop = [
            'participantes' => [$desde, $hacia],
            'tipo' => self::TIPO_CITA,
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
                || ($r['motivo_tecnico'] ?? '') === 'cooldown_propuesta') {
                return self::fin($partida, 'cooldown_en_voluntad', $desde, $hacia, ['quien' => $quien]);
            }
            $motivo = (string) ($r['motivo_tipo'] ?? 'banal');
            RechazoMemoria::registrar($partida, $quien, $otro, $motivo, $cal, self::TIPO_CITA);
            return self::fin($partida, 'rechazo_voluntad_' . $motivo, $desde, $hacia, ['quien_rechaza' => $quien]);
        }

        // ---- resolución conjunta canon 20/08 ----
        $pA = (float) ($ra['p'] ?? 0);
        $pB = (float) ($rb['p'] ?? 0);
        $pPlan = sqrt(max(0.0, $pA) * max(0.0, $pB));
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $pPlan)) {
            $quienRechaza = $pB < $pA ? $hacia : $desde;
            $otro = $quienRechaza === $desde ? $hacia : $desde;
            $motivo = VoluntadPonderadaEvaluator::motivoRechazoPublic($partida, $quienRechaza, $otro, $cal);
            RechazoMemoria::registrar($partida, $quienRechaza, $otro, $motivo, $cal, self::TIPO_CITA);
            return self::fin($partida, 'plan_geom_rechazado_' . $motivo, $desde, $hacia, [
                'quien_rechaza' => $quienRechaza,
                'p_plan' => round($pPlan, 4),
            ]);
        }

        // ---- franja conjunta futura + programación (idéntico a F2A) ----
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
        $r = EncuentroEngine::programar(
            $partida,
            [$desde, $hacia],
            (int) $franja['dia'],
            (int) $franja['hora'],
            self::TIPO_CITA,
            $lugarElegido,
            null,
            $logger,
            false
        );
        if (!($r['ok'] ?? false)) {
            return self::fin($partida, 'error_programar_' . (string) ($r['error'] ?? '?'), $desde, $hacia);
        }
        self::marcarAutonoma($partida, (string) ($r['encuentro']['id'] ?? ''));
        SimFunnelProbe::on($partida, 'encuentro_resuelto', [
            'ev' => 'cita_pareja_agendada',
            '_k' => 'cita_pareja',
            'desde' => $desde,
            'hacia' => $hacia,
            'p_plan' => round($pPlan, 4),
        ]);
        return self::fin($partida, 'cita_pareja_agendada', $desde, $hacia, [
            'programado_dia' => (int) $franja['dia'],
            'programado_hora' => (int) $franja['hora'],
            'lugar' => $lugarElegido,
            'p_plan' => round($pPlan, 4),
        ]);
    }

    private static function marcarAutonoma(array &$partida, string $encuentroId): void
    {
        if ($encuentroId === '') {
            return;
        }
        foreach (($partida['encuentros'] ?? []) as $idx => $enc) {
            if (($enc['id'] ?? '') === $encuentroId) {
                $partida['encuentros'][$idx]['intencion'] = 'autonomo_npc';
            }
        }
    }

    /**
     * Consumidor de marcadores de continuidad vencidos. Corre en tick horario
     * POSTERIOR a la resolución (nunca el mismo instante); consume cada marcador
     * exactamente una vez (idempotencia anti-duplicado).
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    public static function procesarContinuidad(array &$partida, array $cal, ?GameLogger $logger = null): array
    {
        self::ensureContinuidad($partida);
        $now = self::ahoraAbs($partida);
        $resultados = [];
        // R2: se trabaja sobre copia y se VACÍA el bag real: un intento puede
        // re-arma marcadores (declaración fallida → cita) durante el consumo;
        // esos añadidos NO deben ser pisados por la lista de restantes.
        $trabajo = array_values(array_filter($partida['continuidad_romantica'], 'is_array'));
        $partida['continuidad_romantica'] = [];
        foreach ($trabajo as $marker) {
            $par = is_array($marker['par'] ?? null) ? $marker['par'] : [];
            if (count($par) !== 2) {
                continue;
            }
            if ((int) ($marker['desde_abs'] ?? PHP_INT_MAX) > $now) {
                $partida['continuidad_romantica'][] = $marker;
                continue;
            }
            [$a, $b] = [(string) $par[0], (string) $par[1]];
            // Inicia quien más romance siente (empate → orden canónico del par).
            $romAB = RelacionEngine::romanceHacia($partida, $a, $b) ?? 0;
            $romBA = RelacionEngine::romanceHacia($partida, $b, $a) ?? 0;
            $desde = $romAB >= $romBA ? $a : $b;
            $hacia = $desde === $a ? $b : $a;
            // R2: dispatch por accion del marcador (cita = F2A; declaracion = F2B).
            // R4: si el par ya es PAREJA, la cita la gestiona el carril de pareja.
            $accion = (string) ($marker['accion'] ?? 'cita');
            if ($accion === 'declaracion') {
                $r = self::intentarDeclaracion($partida, $desde, $hacia, $cal, $logger);
            } elseif (ParejaEngine::estado($partida, $a, $b) === ParejaEngine::PAREJA
                && self::vidaParejaActiva($cal)) {
                $r = self::intentarCitaPareja($partida, $desde, $hacia, $cal, $logger);
            } else {
                $r = self::intentarSiguienteCita($partida, $desde, $hacia, $cal, $logger);
            }
            $resultados[] = [
                'par' => [$a, $b],
                'desde' => $desde,
                'accion' => $accion,
                'resultado' => (string) ($r['resultado'] ?? '?'),
            ];
        }
        return $resultados;
    }

    // ==================================================================
    // FASE 2B (R2) — Declaración romántica autónoma.
    //
    // NO es inevitable: gate determinista multi-condición + voluntad
    // geométrica de ambos (canon 20/08) con p_max < 1 + cooldowns. La
    // declaración NO pasa por familias_en_play ni por el hueco de vida:
    // usa flag propio romance_autonomo.declaracion_activa (+pareja_activa,
    // su consumidor natural). Rechazo → memoria canónica completa y la
    // continuidad de citas se re-arma (la relación no muere por un no).
    // ==================================================================

    public static function declaracionActiva(array $cal): bool
    {
        return (bool) CalibracionConfig::get($cal, 'romance_autonomo.declaracion_activa', false)
            && (bool) CalibracionConfig::get($cal, 'romance_autonomo.pareja_activa', false);
    }

    /** R4 · flag de vida de pareja autónoma (citas de pareja). */
    public static function vidaParejaActiva(array $cal): bool
    {
        return (bool) CalibracionConfig::get($cal, 'romance_autonomo.vida_pareja_activa', false);
    }

    /** Gap canónico entre citas de pareja: cooldowns.por_familia.pareja. */
    public static function gapParejaHoras(array $cal): int
    {
        return max(1, (int) CalibracionConfig::get($cal, 'cooldowns.por_familia.pareja', 36));
    }

    private static function citasMinimasDeclaracion(array $cal): int
    {
        return max(1, (int) CalibracionConfig::get($cal, 'romance_autonomo.declaracion.citas_minimas', 2));
    }

    private static function ventanaDeclaracionHoras(array $cal): int
    {
        return max(1, (int) CalibracionConfig::get($cal, 'romance_autonomo.declaracion.ventana_horas', 336));
    }

    /** Cap global anti-cascada: hitos románticos registrados HOY en todo el pueblo. */
    public static function hitosRomanticosHoy(array $partida): int
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $tipos = [
            RelacionBitacora::DECLARACION,
            RelacionBitacora::INICIO_PAREJA,
            RelacionBitacora::VUELTA,
            RelacionBitacora::CRISIS,
            RelacionBitacora::RUPTURA,
            RelacionBitacora::RECONCILIACION,
        ];
        $n = 0;
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            if ((int) (($h['fecha']['dia'] ?? -1)) !== $dia) {
                continue;
            }
            if (in_array((string) ($h['tipo'] ?? ''), $tipos, true)) {
                $n++;
            }
        }
        return $n;
    }

    public static function capHitosDisponible(array $partida, array $cal): bool
    {
        $cap = max(1, (int) CalibracionConfig::get($cal, 'romance_autonomo.max_hitos_por_dia', 1));
        return self::hitosRomanticosHoy($partida) < $cap;
    }

    /**
     * Estadísticas de citas románticas RESUELTAS del par (fuente única: encuentros).
     *
     * @return array{n:int,abs_ultima:int,peor_ultima:string,abs_ultima_buena:int,n_buenas:int}
     */
    private static function estadisticasCitas(array $partida, string $a, string $b): array
    {
        $rank = ['muy_mal' => 0, 'mal' => 1, 'normal' => 2, 'bien' => 3, 'muy_bien' => 4];
        $n = 0;
        $absUltima = -1;
        $peorUltima = '';
        $absUltimaBuena = -1;
        $nBuenas = 0;
        foreach (($partida['encuentros'] ?? []) as $enc) {
            if (!is_array($enc) || ($enc['estado'] ?? '') !== 'terminado') {
                continue;
            }
            $tipo = (string) ($enc['tipo'] ?? '');
            if ($tipo !== self::TIPO && $tipo !== self::TIPO_CITA) {
                continue;
            }
            $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            if (!in_array($a, $parts, true) || !in_array($b, $parts, true)) {
                continue;
            }
            $abs = ((int) ($enc['dia'] ?? 0)) * 24 + (int) ($enc['hora'] ?? 0)
                + max(1, (int) ($enc['duracion_horas'] ?? 1));
            $res = is_array($enc['resultado'] ?? null) ? $enc['resultado'] : [];
            $expWorst = '';
            $rankWorst = 99;
            foreach ($parts as $pid) {
                $e = (string) (($res['por_participante'] ?? [])[(string) $pid]['resultado'] ?? '');
                if ($e === '') {
                    continue;
                }
                $r = $rank[$e] ?? 2;
                if ($r < $rankWorst) {
                    $rankWorst = $r;
                    $expWorst = $e;
                }
            }
            $n++;
            if ($abs >= $absUltima) {
                $absUltima = $abs;
                $peorUltima = $expWorst;
            }
            if (in_array($expWorst, ['bien', 'muy_bien'], true)) {
                $nBuenas++;
                $absUltimaBuena = max($absUltimaBuena, $abs);
            }
        }
        return [
            'n' => $n,
            'abs_ultima' => $absUltima,
            'peor_ultima' => $peorUltima,
            'abs_ultima_buena' => $absUltimaBuena,
            'n_buenas' => $nBuenas,
        ];
    }

    /**
     * Elegibilidad DETERMINISTA de declaración para un par (sin RNG).
     * Es el gate que decide si tras una cita el marcador pasa a 'declaracion'
     * y también se re-verifica en el momento del intento.
     *
     * @param array<string, mixed> $cal
     * @return array{ok:bool,motivo:string,desde:string,citas:int}
     */
    public static function elegibilidadDeclaracion(array $partida, string $a, string $b, array $cal): array
    {
        $no = static fn (string $m): array => ['ok' => false, 'motivo' => $m, 'desde' => '', 'citas' => 0];
        if (!self::declaracionActiva($cal)) {
            return $no('flag_off');
        }
        if ($a === '' || $b === '' || $a === $b) {
            return $no('par_invalido');
        }
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return $no('sin_conocerse');
        }
        if (ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal)) {
            return $no('parentesco_veto');
        }
        $el = RomanceElegibilidad::par($partida, $a, $b, $cal);
        if (empty($el['ok'])) {
            return $no('no_elegible_' . (string) ($el['motivo'] ?? '?'));
        }
        $est = ParejaEngine::estado($partida, $a, $b);
        if ($est !== ParejaEngine::NINGUNA) {
            return $no('estado_no_declarable');
        }
        if (SenalRomantica::enParejaConTercero($partida, $a, $b)) {
            return $no('en_pareja_con_otro');
        }
        if (!SenalRomantica::yaHuboPrimeraCita($partida, $a, $b)) {
            return $no('sin_primera_cita');
        }
        $stats = self::estadisticasCitas($partida, $a, $b);
        if ($stats['n'] < self::citasMinimasDeclaracion($cal)) {
            return $no('citas_insuficientes');
        }
        if ($stats['peor_ultima'] !== '' && in_array($stats['peor_ultima'], self::EXPERIENCIAS_MALAS, true)) {
            return $no('ultima_experiencia_mala');
        }
        if ($stats['abs_ultima_buena'] < 0
            || (self::ahoraAbs($partida) - $stats['abs_ultima_buena']) > self::ventanaDeclaracionHoras($cal)) {
            return $no('fuera_ventana');
        }
        // Reciprocidad viva: señal mutua (flechazo o tilín) EN AMBAS direcciones.
        $ab = SenalRomantica::desdeHacia($partida, $a, $b, $cal);
        $ba = SenalRomantica::desdeHacia($partida, $b, $a, $cal);
        if (empty($ab['ok']) || empty($ba['ok'])) {
            return $no('sin_reciprocidad');
        }
        // Banda mínima del DECLARANTE (el de mayor romance) ≥ interes.
        $romAB = RelacionEngine::romanceHacia($partida, $a, $b) ?? 0;
        $romBA = RelacionEngine::romanceHacia($partida, $b, $a) ?? 0;
        $desde = $romAB >= $romBA ? $a : $b;
        $romMax = max($romAB, $romBA);
        $interes = (int) CalibracionConfig::get($cal, 'romance.cortes.interes', 22);
        if ($romMax < $interes) {
            return $no('banda_baja');
        }
        if (MemoriaEventos::enCooldown($partida, 'romance_hito', [$a, $b], $cal)) {
            return $no('cooldown_hito');
        }
        if (PropuestaCooldown::activo($partida, $desde, $desde === $a ? $b : $a, self::TIPO_DECLARACION, $cal)
            || PropuestaCooldown::activo($partida, $desde === $a ? $b : $a, $desde, self::TIPO_DECLARACION, $cal)) {
            return $no('cooldown_propuesta');
        }
        if (!self::capHitosDisponible($partida, $cal)) {
            return $no('cap_hitos_dia');
        }
        return ['ok' => true, 'motivo' => 'ok', 'desde' => $desde, 'citas' => $stats['n']];
    }

    /**
     * R2 · Intento autónomo de DECLARACIÓN (desde → hacia). Nunca lanza.
     * Éxito → ParejaEngine::formar (R3). Fallo → memoria canónica completa +
     * re-arma marcador de CITA (la continuidad no muere por un no).
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function intentarDeclaracion(
        array &$partida,
        string $desde,
        string $hacia,
        array $cal,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);

        // ---- gates (sin RNG; re-verificación al consumo del marcador) ----
        $el = self::elegibilidadDeclaracion($partida, $desde, $hacia, $cal);
        if (empty($el['ok'])) {
            self::armarMarkerCitaTrasDeclaracion($partida, $desde, $hacia, $cal);
            return self::fin($partida, 'gate_declaracion_' . (string) $el['motivo'], $desde, $hacia);
        }

        // ---- voluntad de AMBOS tipo 'declaracion'; media geométrica difiere tirada ----
        $prop = [
            'participantes' => [$desde, $hacia],
            'tipo' => self::TIPO_DECLARACION,
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
                || ($r['motivo_tecnico'] ?? '') === 'cooldown_propuesta') {
                self::armarMarkerCitaTrasDeclaracion($partida, $desde, $hacia, $cal);
                return self::fin($partida, 'cooldown_en_voluntad', $desde, $hacia, ['quien' => $quien]);
            }
            $motivo = (string) ($r['motivo_tipo'] ?? 'banal');
            RechazoMemoria::registrar($partida, $quien, $otro, $motivo, $cal, self::TIPO_DECLARACION);
            self::registrarRechazoDeclaracion($partida, $otro, $quien, $cal);
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'rechazada_voluntad',
                '_k' => 'declaracion_rechazada',
                'desde' => $desde,
                'hacia' => $hacia,
                'motivo' => $motivo,
            ]);
            self::armarMarkerCitaTrasDeclaracion($partida, $desde, $hacia, $cal);
            return self::fin($partida, 'declaracion_rechazada_' . $motivo, $desde, $hacia, ['quien_rechaza' => $quien]);
        }

        // ---- resolución conjunta canon 20/08: p_plan=√(pA·pB), UNA tirada ----
        $pA = (float) ($ra['p'] ?? 0);
        $pB = (float) ($rb['p'] ?? 0);
        $pPlan = sqrt(max(0.0, $pA) * max(0.0, $pB));
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $pPlan)) {
            $quienRechaza = $pB < $pA ? $hacia : $desde;
            $otro = $quienRechaza === $desde ? $hacia : $desde;
            $motivo = VoluntadPonderadaEvaluator::motivoRechazoPublic($partida, $quienRechaza, $otro, $cal);
            RechazoMemoria::registrar($partida, $quienRechaza, $otro, $motivo, $cal, self::TIPO_DECLARACION);
            self::registrarRechazoDeclaracion($partida, $otro, $quienRechaza, $cal);
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'rechazada_geom',
                '_k' => 'declaracion_rechazada',
                'desde' => $desde,
                'hacia' => $hacia,
                'quien_rechaza' => $quienRechaza,
                'p_plan' => round($pPlan, 4),
            ]);
            self::armarMarkerCitaTrasDeclaracion($partida, $desde, $hacia, $cal);
            return self::fin($partida, 'declaracion_rechazada_' . $motivo, $desde, $hacia, [
                'quien_rechaza' => $quienRechaza,
                'p_plan' => round($pPlan, 4),
            ]);
        }

        // ---- aceptación → formación de pareja (R3) ----
        $form = ParejaEngine::formar($partida, $desde, $hacia, true, true, RelacionBitacora::DECLARACION, $cal);
        if (!($form['ok'] ?? false)) {
            self::armarMarkerCitaTrasDeclaracion($partida, $desde, $hacia, $cal);
            return self::fin($partida, 'error_formar_' . (string) ($form['error'] ?? '?'), $desde, $hacia);
        }
        MemoriaEventos::registrar($partida, 'pareja', [$desde, $hacia], null, 'inicio_pareja');
        self::purgarMarcadoresPar($partida, $desde, $hacia);
        SimFunnelProbe::on($partida, 'declaracion', [
            'ev' => 'aceptada',
            '_k' => 'declaracion_ok',
            'desde' => $desde,
            'hacia' => $hacia,
            'vuelta' => (bool) ($form['vuelta'] ?? false),
        ]);
        return self::fin($partida, 'declaracion_aceptada', $desde, $hacia, [
            'p_plan' => isset($pPlan) ? round($pPlan, 4) : null,
            'estado_pareja' => ParejaEngine::PAREJA,
        ]);
    }

    /** Memoria canónica de una declaración rechazada (bitácora+cooldowns). */
    private static function registrarRechazoDeclaracion(array &$partida, string $declara, string $rechaza, array $cal): void
    {
        RelacionBitacora::registrar(
            $partida,
            RelacionBitacora::DECLARACION,
            [$declara, $rechaza],
            $declara . '>' . $rechaza,
            ['acepta_a' => true, 'acepta_b' => false]
        );
        MemoriaEventos::registrar($partida, 'romance_hito', [$declara, $rechaza], null, 'declaracion_rechazada');
        PropuestaCooldown::marcar($partida, $declara, $rechaza, self::TIPO_DECLARACION, $cal);
    }

    /**
     * Re-arma el marcador de SIGUIENTE CITA tras una declaración fallida
     * (explícito, sin re-evaluar elegibilidad de declaración: evita bucles).
     */
    private static function armarMarkerCitaTrasDeclaracion(array &$partida, string $a, string $b, array $cal): void
    {
        if ($a === '' || $b === '' || $a === $b) {
            return;
        }
        self::ensureContinuidad($partida);
        $par = [$a, $b];
        sort($par);
        $parKey = $par[0] . '>' . $par[1];
        $entry = [
            'par' => $par,
            'accion' => 'cita',
            'desde_abs' => self::ahoraAbs($partida) + self::gapMinimoCitas($cal),
            'gap_horas' => self::gapMinimoCitas($cal),
            'ultima_experiencia' => null,
            'creado' => [
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            ],
        ];
        $resto = array_values(array_filter(
            $partida['continuidad_romantica'],
            static fn ($m) => is_array($m) && (($m['par'][0] ?? '') . '>' . ($m['par'][1] ?? '')) !== $parKey
        ));
        $resto[] = $entry;
        $partida['continuidad_romantica'] = $resto;
    }

    /** Elimina TODOS los marcadores de continuidad de un par (formación/ruptura). */
    public static function purgarMarcadoresPar(array &$partida, string $a, string $b): void
    {
        if (!isset($partida['continuidad_romantica']) || !is_array($partida['continuidad_romantica'])) {
            return;
        }
        $ids = [$a, $b];
        sort($ids);
        $parKey = $ids[0] . '>' . $ids[1];
        $partida['continuidad_romantica'] = array_values(array_filter(
            $partida['continuidad_romantica'],
            static fn ($m) => is_array($m) && (($m['par'][0] ?? '') . '>' . ($m['par'][1] ?? '')) !== $parKey
        ));
    }
}
