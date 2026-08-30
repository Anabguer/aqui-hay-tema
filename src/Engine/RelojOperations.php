<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class RelojOperations
{
    private string $projectRoot;
    private ?GameLogger $logger;
    private ?EmotionalStateService $emociones;

    public function __construct(
        string $projectRoot,
        ?GameLogger $logger = null,
        ?EmotionalStateService $emociones = null
    ) {
        $this->projectRoot = $projectRoot;
        $this->logger = $logger;
        $this->emociones = $emociones;
    }

    /**
     * @param array<string, mixed> $opts catch_up: true omite RNG horario/coincidencias/mensajitos espontáneos
     */
    public function avanzar(array &$partida, int $horas, array $opts = []): array
    {
        if ($horas < 0) {
            return GameError::respuesta(GameError::RELOJ_NO_REWIND, ['horas' => $horas]);
        }

        $catchUp = (bool) ($opts['catch_up'] ?? false);
        $offlineEventos = 0;
        $offlineSalidas = 0;
        $antes = $partida['reloj'];
        Reloj::avanzarHoras($partida, $horas);
        $diaAntes = (int) ($antes['dia_pueblo'] ?? 1);
        $diaDespues = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ($diaDespues > $diaAntes) {
            $cal = CalibracionConfig::load($this->projectRoot);
            $catalog = new Catalog($this->projectRoot);
            RelacionDesgaste::alCerrarDia($partida, $cal);
            if (!$catchUp) {
                AcontecimientoDiario::alCerrarDia($partida, $catalog, $cal, $this->logger);
            }
            $calMarcha = CalibracionConfig::load($this->projectRoot);
            if (!$catchUp && FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
                MensajitoDudaPermanenciaEngine::evaluarAlCerrarDia($partida, $calMarcha, $this->logger);
            }
            MarchaEngine::evaluarAlCerrarDia($partida, $this->projectRoot, $this->logger);

            // F10 — rituales contextuales (cumpleaños) al inicio del nuevo día
            if (!$catchUp && FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
                $calCtx = CalibracionConfig::load($this->projectRoot);
                $catalogCtx = new Catalog($this->projectRoot);
                MensajitoContextualEngine::evaluarAlComenzarDia($partida, $calCtx, $catalogCtx, $this->logger);
            }

            // Mensajitos espontaneos: cola + prioridad + presupuesto al inicio del nuevo dia
            if (!$catchUp && FeatureConfig::isEnabled($partida, 'mensajitos_espontaneos_enabled')) {
                $calMens = CalibracionConfig::load($this->projectRoot);
                $rngMens = RngService::fromPartida($partida);
                MensajitosCadenciaEngine::tickEspontaneosAlCerrarDia($partida, $calMens, $rngMens);
                $rngMens->persistToPartida($partida);
            }

            // Seguimiento de consejos (F9)
            if (FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
                $calSeg = CalibracionConfig::load($this->projectRoot);
                SeguimientoConsejoEngine::evaluarPendientes($partida, $calSeg, $this->logger);
            }

            // Catch-up batch: generar actividad NPC limitada por día offline
            if ($catchUp && FeatureConfig::isEnabled($partida, 'npc_autonomy_enabled')) {
                $calBatch = CalibracionConfig::load($this->projectRoot);
                $catalogBatch = new Catalog($this->projectRoot);
                $rngBatch = RngService::fromPartida($partida);
                $batchResult = MotorVidaDiaria::catchUpBatchDia($partida, $catalogBatch, $calBatch, $rngBatch, $this->logger);
                $rngBatch->persistToPartida($partida);
                $offlineEventos += ($batchResult['eventos'] ?? 0);
                $offlineSalidas += ($batchResult['salidas'] ?? 0);
            }
        }
        $calTick = CalibracionConfig::load($this->projectRoot);
        // Vida horaria autónoma (huecos, casuales, declaración/ruptura): independiente del
        // planificador diario batch (acontecimientos_dia.activo_en_play).
        $runVidaHoraria = !$catchUp && (
            !empty($partida['lab_vida_activa'])
            || (bool) CalibracionConfig::get($calTick, 'acontecimientos_dia.activo_en_play', false)
            || FeatureConfig::isEnabled($partida, 'npc_autonomy_enabled')
            || FeatureConfig::isEnabled($partida, 'encuentros_enabled')
        );
        if ($runVidaHoraria) {
            $rngTick = RngService::fromPartida($partida);
            MotorVidaDiaria::tickHora($partida, new Catalog($this->projectRoot), $calTick, $rngTick, $this->logger);
            $rngTick->persistToPartida($partida);
        }
        // Coincidencias ANTES de sincronizar: los encuentros siguen programado/en_curso
        // y aún ocupan lugar. Coincidir ≠ interactuar.
        $coins = [];
        if (!$catchUp) {
            $coins = CoincidenciasEngine::detectarEnIntervalo(
                $partida,
                $this->projectRoot,
                $antes,
                $horas,
                $this->logger
            );
        }
        $sync = EncuentroLifecycle::sincronizarConReloj($partida, $this->logger, new Catalog($this->projectRoot));
        $expirados = $this->emociones !== null ? $this->emociones->expirarVencidos($partida) : 0;
        $peticionesCaducadas = PeticionEngine::caducarVencidas($partida, $this->logger);
        $propuestasCaducadas = PropuestaEncuentroEngine::caducarVencidas($partida);

        // Llegadas: tutorial día 1 + candidatos post-tutorial
        $partida['llegadas']['_tick_por_hora'] = true;
        TutorialBucle::flushIncorporacionesPendientes($partida, $this->projectRoot, $this->logger);
        TutorialIncorporaciones::tickDia1($partida, $this->projectRoot, $this->logger);
        if ($diaDespues > $diaAntes) {
            TutorialIncorporaciones::alCerrarDia1SiToca($partida, $this->projectRoot, $this->logger);
        }
        $llegadasTick = CandidatoLlegadaEngine::tick($partida, $this->projectRoot, $this->logger, $horas);

        if (PeticionPuebloEngine::activa($partida)) {
            $calPet = CalibracionConfig::load($this->projectRoot);
            PeticionPuebloEngine::tick(
                $partida,
                $calPet,
                RngService::fromPartida($partida),
                $this->logger,
                $horas
            );
        }

        if ($diaDespues > $diaAntes && MisionDiariaEngine::activa($partida)) {
            $calMis = CalibracionConfig::load($this->projectRoot);
            for ($d = $diaAntes; $d < $diaDespues; $d++) {
                MisionDiariaEngine::alCerrarDia($partida, $d, $calMis, $this->logger);
            }
            MisionDiariaEngine::alComenzarDia(
                $partida,
                $calMis,
                RngService::fromPartida($partida),
                $this->logger
            );
        }

        if ($diaDespues > $diaAntes) {
            $calEvt = CalibracionConfig::load($this->projectRoot);
            if (EventosPuebloEngine::catalogItems(new Catalog($this->projectRoot)) !== []
                && ((bool) CalibracionConfig::get($calEvt, 'eventos_pueblo.activo', false)
                    || FeatureConfig::isEnabled($partida, 'eventos_pueblo_enabled'))
            ) {
                $rngEvt = RngService::fromPartida($partida);
                EventosPuebloEngine::alComenzarDia(
                    $partida,
                    $calEvt,
                    $rngEvt,
                    new Catalog($this->projectRoot),
                    $this->logger
                );
                $rngEvt->persistToPartida($partida);
            }
        }

        DomainEventDispatcher::emit($partida, DomainEvents::TIEMPO_AVANZADO, [
            'horas' => $horas,
            'antes' => $antes,
            'despues' => $partida['reloj'],
            'encuentros_resueltos' => $sync['resueltos'],
            'estados_expirados' => $expirados,
            'coincidencias_detectadas' => count($coins),
        ], $this->logger, 'RelojOperations::avanzar');

        return [
            'ok' => true,
            'reloj' => $partida['reloj'],
            'texto' => Reloj::formatear($partida['reloj']),
            'encuentros_resueltos' => $sync['resueltos'],
            'estados_expirados' => $expirados,
            'coincidencias_detectadas' => count($coins),
            'peticiones_caducadas' => $peticionesCaducadas,
            'propuestas_caducadas' => $propuestasCaducadas,
            'llegadas' => $llegadasTick,
            'horas' => $horas,
            'offline_eventos' => $offlineEventos,
            'offline_salidas' => $offlineSalidas,
        ];
    }

    /**
     * Avanza hora a hora para no saltar lifecycle/eventos intermedios.
     * Uso play (+8h, ir al próximo). La simulación QA puede seguir usando avanzar() en bloque.
     */
    public function avanzarPasoAPaso(array &$partida, int $horas): array
    {
        if ($horas < 0) {
            return GameError::respuesta(GameError::RELOJ_NO_REWIND, ['horas' => $horas]);
        }

        $acum = [
            'ok' => true,
            'encuentros_resueltos' => 0,
            'estados_expirados' => 0,
            'coincidencias_detectadas' => 0,
            'peticiones_caducadas' => 0,
            'pasos' => 0,
            'horas' => $horas,
        ];

        $snap = AvanceResumen::snapshot($partida);
        $snapGuia = PlaytestGuia::activa($partida)
            ? PlaytestGuia::snapshot($partida, $this->projectRoot)
            : null;
        $iter = $horas === 0 ? 1 : $horas;
        $pasoHoras = $horas === 0 ? 0 : 1;
        for ($i = 0; $i < $iter; $i++) {
            $r = $this->avanzar($partida, $pasoHoras);
            if (($r['ok'] ?? true) === false) {
                return $r;
            }
            $acum['encuentros_resueltos'] += (int) ($r['encuentros_resueltos'] ?? 0);
            $acum['estados_expirados'] += (int) ($r['estados_expirados'] ?? 0);
            $acum['coincidencias_detectadas'] += (int) ($r['coincidencias_detectadas'] ?? 0);
            $acum['peticiones_caducadas'] += (int) ($r['peticiones_caducadas'] ?? 0);
            $acum['pasos']++;
            if ($horas === 0) {
                break;
            }
        }

        $acum['reloj'] = $partida['reloj'];
        $acum['texto'] = Reloj::formatear($partida['reloj']);
        $acum['resumen_avance'] = self::enriquecerResumen($partida, AvanceResumen::desdeSnapshot($partida, $snap));
        if ($snapGuia !== null) {
            $acum['playtest_guia_evento'] = PlaytestGuia::trasAvance(
                $partida,
                $this->projectRoot,
                $snapGuia,
                $horas
            );
            $acum['playtest_guia'] = PlaytestGuia::vista($partida, $this->projectRoot);
            $resumen = $acum['resumen_avance'] ?? ['lineas' => []];
            $lineas = [];
            foreach (($resumen['lineas'] ?? []) as $l) {
                if (is_array($l)) {
                    $lineas[] = (string) ($l['texto'] ?? $l['tipo'] ?? '');
                }
            }
            PlaytestDiag::push($partida, 'AVANCE_TIEMPO', [
                'horas' => $horas,
                'paso_a_paso' => true,
                'resumen_lineas' => $lineas,
                'encuentros_resueltos' => (int) ($acum['encuentros_resueltos'] ?? 0),
                'coincidencias_detectadas' => (int) ($acum['coincidencias_detectadas'] ?? 0),
                'guia_evento' => $acum['playtest_guia_evento'] ?? null,
            ]);
            $acum['playtest_diag'] = PlaytestDiag::vista($partida);
        }
        return $acum;
    }

    /**
     * Atajo de play: avanza hasta el inicio del siguiente encuentro programado.
     * No rebobina. Procesa cada hora con la misma tubería que +1h.
     */
    public function irAlProximoEncuentro(array &$partida): array
    {
        $next = self::proximoEncuentroProgramado($partida);
        if ($next === null) {
            return GameError::respuesta(GameError::SIN_PROXIMO_ENCUENTRO);
        }

        $actual = self::ahoraAbsoluto($partida);
        $objetivo = ((int) $next['dia']) * 24 + (int) $next['hora'];
        if ($objetivo < $actual) {
            return GameError::respuesta(GameError::RELOJ_NO_REWIND, [
                'encuentro_id' => $next['id'] ?? null,
                'actual' => ['dia' => $partida['reloj']['dia_pueblo'], 'hora' => $partida['reloj']['hora_actual']],
                'objetivo' => ['dia' => $next['dia'], 'hora' => $next['hora']],
            ]);
        }

        $horas = $objetivo - $actual;
        $adv = $this->avanzarPasoAPaso($partida, $horas);
        if (($adv['ok'] ?? true) === false) {
            return $adv;
        }

        $actualizado = null;
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (($enc['id'] ?? '') === ($next['id'] ?? '')) {
                $actualizado = $enc;
                break;
            }
        }

        return [
            'ok' => true,
            '_atajo_play' => true,
            'horas_avanzadas' => $horas,
            'encuentro' => $actualizado ?? $next,
            'reloj' => $adv,
            'resumen_avance' => $adv['resumen_avance'] ?? ['lineas' => [], 'total' => 0],
            'playtest_guia_evento' => $adv['playtest_guia_evento'] ?? null,
            'playtest_guia' => $adv['playtest_guia'] ?? (PlaytestGuia::activa($partida)
                ? PlaytestGuia::vista($partida, $this->projectRoot)
                : null),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function proximoEncuentroProgramado(array $partida): ?array
    {
        $now = self::ahoraAbsoluto($partida);
        $best = null;
        $bestT = null;
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (($enc['estado'] ?? '') !== 'programado') {
                continue;
            }
            $t = ((int) ($enc['dia'] ?? 0)) * 24 + (int) ($enc['hora'] ?? 0);
            if ($t < $now) {
                continue;
            }
            if ($bestT === null || $t < $bestT) {
                $best = $enc;
                $bestT = $t;
            }
        }
        return is_array($best) ? $best : null;
    }

    public static function ahoraAbsoluto(array $partida): int
    {
        return ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24
            + (int) ($partida['reloj']['hora_actual'] ?? 0);
    }

    /**
     * @param array<string, mixed> $resumen
     * @return array<string, mixed>
     */
    private function enriquecerResumen(array $partida, array $resumen): array
    {
        $catalog = new Catalog($this->projectRoot);
        $vistas = [];
        foreach ($resumen['encuentros_terminados_ids'] ?? [] as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            foreach (EncuentroEngine::list($partida) as $enc) {
                if (($enc['id'] ?? '') === $id) {
                    $vistas[] = EncuentroResultadoVista::de($partida, $enc, $catalog, $this->projectRoot);
                    break;
                }
            }
        }
        $resumen['encuentros_terminados'] = $vistas;
        $resumen['encuentros_terminados_count'] = count($vistas);
        return $resumen;
    }
}
