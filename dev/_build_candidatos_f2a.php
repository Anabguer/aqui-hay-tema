<?php
declare(strict_types=1);

// Construcción de candidatos F2A+PRE DESDE LOS BLOBS DE PRODUCCIÓN.
// Cada hunk es una sustitución con aguja exacta; si una aguja no aparece,
// el script FALLA (no se construye nada a ciegas).

$root = dirname(__DIR__);
$stage = null;
foreach (glob($root . '/dev/prod_fetch_f2a_*', GLOB_ONLYDIR) ?: [] as $d) {
    if ($stage === null || $d > $stage) {
        $stage = $d;
    }
}
if ($stage === null) {
    fwrite(STDERR, "sin staging\n");
    exit(1);
}
$outDir = $stage . '/candidatos';
@mkdir($outDir, 0777, true);

function leer(string $p): string
{
    $c = file_get_contents($p);
    if ($c === false) {
        fwrite(STDERR, "no legible: $p\n");
        exit(1);
    }
    // Normaliza BOM y CRLF para trabajar LF puro.
    if (str_starts_with($c, "\xEF\xBB\xBF")) {
        $c = substr($c, 3);
    }
    return str_replace("\r\n", "\n", $c);
}

/** Sustituye UNA aguja exacta; falla si no está o si estuviera duplicada. */
function parche(string $nombre, string &$blob, string $aguja, string $cambio): void
{
    $n = substr_count($blob, $aguja);
    if ($n !== 1) {
        fwrite(STDERR, "AGUJA '$nombre' encontrada $n veces (esperaba 1)\n");
        exit(1);
    }
    $blob = str_replace($aguja, $cambio, $blob);
    echo "hunk ok: $nombre\n";
}

$candidatos = [];

// ============================================================
// 1) MotorVidaDiaria.php  (prod = base + hook A1)
// ============================================================
$m = leer($stage . '/src__Engine__MotorVidaDiaria.php');

parche('MVD/PRE-gate', $m,
"        \$familiasPlay = CalibracionConfig::get(\$cal, 'acontecimientos_dia.familias_en_play', null);
        \$enPlay = empty(\$partida['lab_vida_activa']) && FeatureConfig::isEnabled(\$partida, 'npc_autonomy_enabled');
        if (\$enPlay && is_array(\$familiasPlay) && \$familiasPlay !== []) {
            \$items = array_values(array_filter(\$items, static function (\$item) use (\$familiasPlay) {
                return in_array((string) (\$item['familia'] ?? ''), \$familiasPlay, true);
            }));
        }",
"        // PRE FASE 2A — familias_en_play es el CONTRATO EFECTIVO de familias
        // permitidas fuera de laboratorio. El gate antiguo exigía además
        // npc_autonomy_enabled, flag global a false, lo que dejaba el filtro
        // muerto y permitía romance_hito/pareja (declaracion/crisis/ruptura)
        // en play contra lo documentado en Fase 1. Ahora el criterio es solo
        // lab vs no-lab; los flags técnicos NO condicionan el contrato.
        \$familiasPlay = CalibracionConfig::get(\$cal, 'acontecimientos_dia.familias_en_play', null);
        \$items = self::filtrarFamiliasEnPlay(
            \$items,
            is_array(\$familiasPlay) ? \$familiasPlay : [],
            !empty(\$partida['lab_vida_activa'])
        );"
);

parche('MVD/F2A-consumidor', $m,
"        \$out['iniciativa_social'] = IniciativaSocial::tick(\$partida, \$catalog, \$cal, \$rng, \$logger);
        \$rng->persistToPartida(\$partida);",
"        \$out['iniciativa_social'] = IniciativaSocial::tick(\$partida, \$catalog, \$cal, \$rng, \$logger);
        // FASE 2A: continuidad romántica. Solo consume marcadores VENCIDOS
        // (última cita + gap 48 h canónicas); nunca crea nada en el tick en
        // que se resolvió la cita anterior. Último hook del tick, tras la
        // vida autónoma previa (hueco/salidas/casuales/A1), sin reordenar.
        \$out['continuidad'] = IniciativaRomantica::procesarContinuidad(\$partida, \$cal, \$logger);
        \$rng->persistToPartida(\$partida);"
);

parche('MVD/helper-filtro', $m,
"            \$disp = AgendaEngine::estaDisponible(\$partida, \$quien, \$d, \$h);
            if (!(\$disp['disponible'] ?? false)) {
                continue;
            }
            return ['dia' => \$d, 'hora' => \$h];
        }
        return null;
    }
}",
"            \$disp = AgendaEngine::estaDisponible(\$partida, \$quien, \$d, \$h);
            if (!(\$disp['disponible'] ?? false)) {
                continue;
            }
            return ['dia' => \$d, 'hora' => \$h];
        }
        return null;
    }

    /**
     * PRE FASE 2A: contrato de familias del hueco de vida.
     * - Fuera de laboratorio (play): SOLO familias_en_play (romance_hito/pareja
     *   quedan fuera: sin declaracion/crisis/ruptura/reconciliacion autónomas).
     * - En laboratorio (lab_vida_activa): catálogo completo, sin filtro.
     * - Config vacía/ausente: comportamiento legacy sin filtro.
     *
     * @param list<array<string, mixed>> \$items
     * @param array<int|string, mixed> \$familiasPlay
     * @param bool \$esLabVidaActiva
     * @return list<array<string, mixed>>
     */
    public static function filtrarFamiliasEnPlay(array \$items, array \$familiasPlay, bool \$esLabVidaActiva): array
    {
        if (\$esLabVidaActiva || \$familiasPlay === []) {
            return \$items;
        }
        return array_values(array_filter(\$items, static function (\$item) use (\$familiasPlay) {
            return in_array((string) (\$item['familia'] ?? ''), \$familiasPlay, true);
        }));
    }
}"
);
$candidatos['src/Engine/MotorVidaDiaria.php'] = $m;

// ============================================================
// 2) EncuentroEngine.php  (prod == base)
// ============================================================
$e = leer($stage . '/src__Engine__EncuentroEngine.php');

parche('EE/validarContexto-firma', $e,
"    public static function validarContexto(
        array \$partida,
        array \$participantes,
        string \$tipo = 'conocerse',
        ?string \$lugarId = null,
        ?GameLogger \$logger = null
    ): array {",
"    public static function validarContexto(
        array \$partida,
        array \$participantes,
        string \$tipo = 'conocerse',
        ?string \$lugarId = null,
        ?GameLogger \$logger = null,
        bool \$cuentaComoCelestine = true
    ): array {"
);

parche('EE/limite-guarda', $e,
"        \$limite = self::limiteIntervencionesDia(\$partida);
        if (\$limite !== null) {",
"        \$limite = self::limiteIntervencionesDia(\$partida);
        if (\$cuentaComoCelestine && \$limite !== null) {"
);

parche('EE/programar-firma', $e,
"        ?string \$actividad = null,
        ?GameLogger \$logger = null
    ): array {
        \$ctx = self::validarContexto(\$partida, \$participantes, \$tipo, \$lugarId, \$logger);",
"        ?string \$actividad = null,
        ?GameLogger \$logger = null,
        bool \$cuentaComoCelestine = true
    ): array {
        \$ctx = self::validarContexto(\$partida, \$participantes, \$tipo, \$lugarId, \$logger, \$cuentaComoCelestine);"
);

parche('EE/contador-guarda', $e,
"        \$partida['encuentros'][] = \$encuentro;
        if (\$tipo !== 'individual') {",
"        \$partida['encuentros'][] = \$encuentro;
        if (\$cuentaComoCelestine && \$tipo !== 'individual') {"
);
$candidatos['src/Engine/EncuentroEngine.php'] = $e;

// ============================================================
// 3) IniciativaRomantica.php  (prod == base F1)
// ============================================================
$i = leer($stage . '/src__Engine__IniciativaRomantica.php');

parche('IR/constantes', $i,
"final class IniciativaRomantica
{
    private const TIPO = 'primera_cita';
    private const LOG_MAX = 500;",
"final class IniciativaRomantica
{
    private const TIPO = 'primera_cita';
    private const TIPO_CITA = 'cita';
    private const LOG_MAX = 500;

    /** Experiencias que cortan la continuidad autónoma (canon: corte_si_ultimo_malo). */
    private const EXPERIENCIAS_MALAS = ['mal', 'muy_mal'];"
);

$colaBase = <<<'TXT'
        $partida['iniciativa_romantica_log'][] = $row;
        if (count($partida['iniciativa_romantica_log']) > self::LOG_MAX) {
            $partida['iniciativa_romantica_log'] = array_slice($partida['iniciativa_romantica_log'], -self::LOG_MAX);
        }
        return array_merge(['ok' => str_starts_with($resultado, 'primera_cita_agendada')], $row);
    }
}
TXT;

$colaNuevo = <<<'TXT'
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
        $entry = [
            'par' => $par,
            'desde_abs' => self::ahoraAbs($partida) + self::gapMinimoCitas($cal),
            'gap_horas' => self::gapMinimoCitas($cal),
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
        $restantes = [];
        foreach ($partida['continuidad_romantica'] as $marker) {
            if (!is_array($marker)) {
                continue;
            }
            $par = is_array($marker['par'] ?? null) ? $marker['par'] : [];
            if (count($par) !== 2) {
                continue;
            }
            if ((int) ($marker['desde_abs'] ?? PHP_INT_MAX) > $now) {
                $restantes[] = $marker;
                continue;
            }
            [$a, $b] = [(string) $par[0], (string) $par[1]];
            // Inicia quien más romance siente (empate → orden canónico del par).
            $romAB = RelacionEngine::romanceHacia($partida, $a, $b) ?? 0;
            $romBA = RelacionEngine::romanceHacia($partida, $b, $a) ?? 0;
            $desde = $romAB >= $romBA ? $a : $b;
            $hacia = $desde === $a ? $b : $a;
            $r = self::intentarSiguienteCita($partida, $desde, $hacia, $cal, $logger);
            $resultados[] = [
                'par' => [$a, $b],
                'desde' => $desde,
                'resultado' => (string) ($r['resultado'] ?? '?'),
            ];
        }
        $partida['continuidad_romantica'] = $restantes;
        return $resultados;
    }
}
TXT;

parche('IR/F2A-bloque-completo', $i, $colaBase, $colaNuevo);
$candidatos['src/Engine/IniciativaRomantica.php'] = $i;

// ============================================================
// 4) EncuentroResolver.php  (prod DRIFT: multiplicadores+probe)
// ============================================================
$r = leer($stage . '/src__Engine__EncuentroResolver.php');

parche('ER/marcador-continuidad', $r,
"        MemoriaEventos::registrar(
            \$partida,
            'encuentro',
            \$participantes,
            null,
            (string) (\$encuentro['tipo'] ?? 'encuentro'),
            \$resultado['por_participante'][\$a]['resultado'] ?? null
        );
    }",
"        MemoriaEventos::registrar(
            \$partida,
            'encuentro',
            \$participantes,
            null,
            (string) (\$encuentro['tipo'] ?? 'encuentro'),
            \$resultado['por_participante'][\$a]['resultado'] ?? null
        );

        // FASE 2A: tras resolver una cita romántica queda FECHADO el intento de
        // continuidad (última cita + gap canónico 48 h). Aquí solo se registra
        // el marcador; la cita futura, si llega, la decide un tick posterior
        // con voluntad real (IniciativaRomantica::procesarContinuidad).
        if (\$tipoEnc === PropuestaNivel::PRIMERA_CITA || \$tipoEnc === PropuestaNivel::CITA) {
            \$expA = (string) (\$resultado['por_participante'][\$a]['resultado'] ?? '');
            \$expB = (string) (\$resultado['por_participante'][\$b]['resultado'] ?? '');
            \$rankExp = ['muy_mal' => 0, 'mal' => 1, 'normal' => 2, 'bien' => 3, 'muy_bien' => 4];
            \$peor = \$expA;
            if (\$expB !== '' && (\$peor === '' || (\$rankExp[\$expB] ?? 2) < (\$rankExp[\$peor] ?? 2))) {
                \$peor = \$expB;
            }
            IniciativaRomantica::registrarContinuidadPostCita(\$partida, \$a, \$b, \$peor !== '' ? \$peor : null, is_array(\$resultado['_cal'] ?? null) ? \$resultado['_cal'] : []);
        }
    }"
);
$candidatos['src/Engine/EncuentroResolver.php'] = $r;

// ============================================================
// Volcado de candidatos
// ============================================================
foreach ($candidatos as $rutaRel => $contenido) {
    $dest = $outDir . '/' . str_replace('/', '__', $rutaRel);
    file_put_contents($dest, $contenido);
    echo "candidato: $rutaRel -> ", basename($dest), " (", strlen($contenido), " bytes)\n";
}
echo "OK candidatos en $outDir\n";
