<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Laboratorio DEBUG: observación y comparación de periodos.
 * Solo lectura / serialización; no altera reglas de gameplay.
 */
final class PlayLab
{
    /** @var array<string, string> */
    private const HITO_ETIQUETAS = [
        RelacionBitacora::SE_CONOCIERON => 'Se conocieron',
        RelacionBitacora::PRIMERA_CITA => 'Primera cita',
        RelacionBitacora::FLECHAZO => 'Flechazo',
        RelacionBitacora::DECLARACION => 'Declaración',
        RelacionBitacora::INICIO_PAREJA => 'Inicio de pareja',
        RelacionBitacora::CRISIS => 'Crisis',
        RelacionBitacora::RUPTURA => 'Ruptura',
        RelacionBitacora::RECONCILIACION => 'Reconciliación',
        RelacionBitacora::VUELTA => 'Vuelta',
        RelacionBitacora::DISCUSION_FUERTE => 'Discusión fuerte',
        RelacionBitacora::RECHAZO_IMPORTANTE => 'Rechazo importante',
        RelacionBitacora::PLAN_SIGNIFICATIVO => 'Plan significativo',
        RelacionBitacora::APOYO_IMPORTANTE => 'Apoyo importante',
        RelacionBitacora::HITO_ROMANTICO => 'Hito romántico',
    ];

    /** @var array<string, string> */
    private const CATEGORIA_HITO = [
        RelacionBitacora::SE_CONOCIERON => 'SOCIAL',
        RelacionBitacora::PRIMERA_CITA => 'ROMANCE',
        RelacionBitacora::FLECHAZO => 'ROMANCE',
        RelacionBitacora::DECLARACION => 'ROMANCE',
        RelacionBitacora::INICIO_PAREJA => 'ROMANCE',
        RelacionBitacora::CRISIS => 'DRAMA',
        RelacionBitacora::RUPTURA => 'DRAMA',
        RelacionBitacora::RECONCILIACION => 'DRAMA',
        RelacionBitacora::VUELTA => 'DRAMA',
        RelacionBitacora::DISCUSION_FUERTE => 'DRAMA',
        RelacionBitacora::RECHAZO_IMPORTANTE => 'ROMANCE',
        RelacionBitacora::PLAN_SIGNIFICATIVO => 'ENCUENTROS',
        RelacionBitacora::APOYO_IMPORTANTE => 'SOCIAL',
        RelacionBitacora::HITO_ROMANTICO => 'ROMANCE',
    ];

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function resumenPueblo(array $partida, Catalog $catalog, string $root): array
    {
        $reloj = $partida['reloj'] ?? [];
        CapacidadViviendas::ensure($partida);
        $viviendasOcupadas = 0;
        $viviendasLibres = 0;
        foreach (CapacidadViviendas::slots($partida) as $slot) {
            if (!empty($slot['ocupante_id'])) {
                $viviendasOcupadas++;
            } else {
                $viviendasLibres++;
            }
        }

        $residentes = 0;
        $candidatosRes = 0;
        $antiguos = 0;
        foreach ($partida['residentes'] ?? [] as $res) {
            if (!is_array($res)) {
                continue;
            }
            $pres = (string) ($res['presencia'] ?? 'residente');
            if ($pres === 'residente') {
                $residentes++;
            } elseif ($pres === 'candidato') {
                $candidatosRes++;
            } elseif (in_array($pres, ['marchado', 'se_fue', 'ex_residente', 'ausente'], true)) {
                $antiguos++;
            }
        }

        $encuentrosActivos = EncuentroEngine::listarActivos($partida);
        $encuentrosProximos = [];
        $ahora = self::absolutoReloj($partida);
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $est = (string) ($enc['estado'] ?? '');
            if (!in_array($est, ['programado', 'en_curso', 'pendiente'], true)) {
                continue;
            }
            $dia = (int) ($enc['dia'] ?? 0);
            $hora = (int) ($enc['hora'] ?? 0);
            $abs = $dia * 24 + $hora;
            if ($abs >= $ahora) {
                $encuentrosProximos[] = self::vistaEncuentroBreve($partida, $enc);
            }
        }
        usort($encuentrosProximos, static fn($a, $b) => ($a['abs'] ?? 0) <=> ($b['abs'] ?? 0));

        $parejas = self::parejasActuales($partida);
        $relConocidas = self::contarParesConocidos($partida);
        $romance = self::metricasRomanceGlobales($partida);
        $crisisActivas = self::contarCrisisActivas($partida);
        $marchas = self::intencionesMarcha($partida);
        $buzon = self::metricasBuzon($partida);
        $llegadas = self::metricasLlegadas($partida);

        return [
            'reloj' => [
                'dia_pueblo' => (int) ($reloj['dia_pueblo'] ?? 1),
                'hora_actual' => (int) ($reloj['hora_actual'] ?? 0),
                'minuto_actual' => (int) ($reloj['minuto_actual'] ?? 0),
                'texto' => Reloj::formatear($reloj),
                'fecha_iso' => Reloj::fechaIso($reloj, (int) ($reloj['dia_pueblo'] ?? 1)),
                'fecha_corta' => Reloj::fechaCorta($reloj, (int) ($reloj['dia_pueblo'] ?? 1)),
            ],
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'config_id' => $partida['meta']['config_id'] ?? null,
            'poblacion' => [
                'residentes' => $residentes,
                'viviendas_ocupadas' => $viviendasOcupadas,
                'viviendas_libres' => $viviendasLibres,
                'candidatos_en_roster' => $candidatosRes,
                'candidato_activo_llegada' => $llegadas['candidato_activo'],
                'candidato_en_camino' => $llegadas['en_camino'],
                'antiguos_residentes' => $antiguos,
            ],
            'encuentros' => [
                'activos' => count($encuentrosActivos),
                'proximos' => array_slice($encuentrosProximos, 0, 8),
            ],
            'parejas' => $parejas,
            'relaciones' => [
                'pares_conocidos' => $relConocidas,
                'intereses_romanticos' => $romance['intereses_activos'],
                'flechazos_registrados' => $romance['flechazos_total'],
                'unilaterales' => $romance['unilaterales'],
                'reciprocos' => $romance['reciprocos'],
            ],
            'drama' => [
                'crisis_activas' => $crisisActivas,
            ],
            'marchas' => $marchas,
            'narrativa' => [
                'cotilleos_buzon' => $buzon['cotilleos'],
                'mensajitos_pendientes_decision' => $buzon['mensajitos_decision'],
                'mensajitos_pendientes_lectura' => $buzon['mensajitos_lectura'],
                'cotilleos_recientes' => self::cotilleosRecientes($partida, 6),
            ],
            'vida_pueblo' => VidaPuebloEngine::valor($partida),
            'vecinos' => self::listaVecinos($partida, $catalog),
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function inspectorVecino(array $partida, string $residenteId, Catalog $catalog, string $root): array
    {
        if (!isset($partida['residentes'][$residenteId]) || !is_array($partida['residentes'][$residenteId])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }

        $fichaJugador = null;
        try {
            $svc = new PartidaService($root);
            $fichaJugador = $svc->fichaResidente($partida, $residenteId, true);
        } catch (\Throwable $ignored) {
            $fichaJugador = null;
        }

        $res = $partida['residentes'][$residenteId];
        $perfil = PerfilPartida::de($partida, $residenteId) ?? PerfilPartida::deOLegacy($partida, $residenteId, $catalog);
        $store = $catalog->store();

        $vidaSocialMotor = self::vidaSocialResidente($partida, $residenteId, $catalog, false);
        $vidaSocialJugador = self::vidaSocialResidente($partida, $residenteId, $catalog, true);

        return [
            'ok' => true,
            'id' => $residenteId,
            'nombre' => IdentidadPublica::nombre($partida, $residenteId),
            'retrato_url' => $res['retrato_url'] ?? null,
            'motor' => [
                'identidad' => [
                    'edad' => PerfilPartida::edadResuelta($partida, $residenteId, $catalog),
                    'vivienda_id' => $res['vivienda_id'] ?? null,
                    'presencia' => $res['presencia'] ?? 'residente',
                    'estado_emocional' => $res['runtime']['estado_emocional'] ?? null,
                    'expresion_visual' => $res['runtime']['expresion_visual'] ?? null,
                ],
                'personalidad' => self::perfilLegibleMotor($perfil, $store),
                'agenda' => self::agendaResidente($partida, $residenteId),
                'vida_social' => $vidaSocialMotor,
            ],
            'jugador' => [
                'ficha' => $fichaJugador,
                'vida_social' => $vidaSocialJugador,
                'descubrimientos' => self::descubrimientosJugador($partida, $residenteId),
            ],
            'nota' => 'motor = datos reales del estado; jugador = lo visible/descubierto en play.',
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function inspectorPar(array $partida, string $a, string $b, Catalog $catalog): array
    {
        if ($a === '' || $b === '' || $a === $b) {
            return ['ok' => false, 'error' => 'par_invalido'];
        }
        if (!isset($partida['residentes'][$a], $partida['residentes'][$b])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }

        $entre = RelacionEngine::obtenerEntre($partida, $a, $b);
        $social = is_array($entre['social'] ?? null) ? $entre['social'] : [];
        $romance = is_array($entre['romance'] ?? null) ? $entre['romance'] : [];
        $conflicto = is_array($entre['conflicto'] ?? null) ? $entre['conflicto'] : null;

        CompatibilidadOculta::asegurarDireccional($partida, $a, $b, $catalog);
        CompatibilidadOculta::asegurarDireccional($partida, $b, $a, $catalog);
        $quim = QuimicaEngine::obtener($partida, $a, $b);

        $dirAb = self::direccionPar($partida, $a, $b, $catalog, $social, $romance);
        $dirBa = self::direccionPar($partida, $b, $a, $catalog, $social, $romance);

        $estadoPareja = ParejaEngine::estado($partida, $a, $b);
        $coincidencias = self::coincidenciasPar($partida, $a, $b);
        $encuentros = self::encuentrosPar($partida, $a, $b);

        return [
            'ok' => true,
            'par' => [
                'a' => ['id' => $a, 'nombre' => IdentidadPublica::nombre($partida, $a)],
                'b' => ['id' => $b, 'nombre' => IdentidadPublica::nombre($partida, $b)],
            ],
            'se_conocen' => RelacionEngine::seConocen($partida, $a, $b),
            'estado_pareja' => $estadoPareja,
            'es_ex' => $estadoPareja === ParejaEngine::EX,
            'crisis_activa' => $estadoPareja === ParejaEngine::CRISIS,
            'quimica_par' => $quim,
            'conflicto' => $conflicto,
            'social_fila' => $social,
            'romance_fila' => $romance,
            'flechazos' => $romance['flechazos'] ?? [],
            'a_hacia_b' => $dirAb,
            'b_hacia_a' => $dirBa,
            'ultimo_contacto' => $social['ultimo_contacto'] ?? null,
            'conocido_desde' => $social['conocido_desde'] ?? null,
            'coincidencias_n' => count($coincidencias),
            'encuentros_n' => count($encuentros),
            'timeline' => self::timelinePar($partida, $a, $b, $catalog),
        ];
    }

    /**
     * Simula N días con reloj canónico hora a hora (avanzarPasoAPaso).
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function simularPeriodo(
        PartidaService $service,
        array &$partida,
        int $dias,
        Catalog $catalog,
        string $root
    ): array {
        if ($dias < 1 || $dias > 60) {
            return ['ok' => false, 'error' => 'dias_fuera_rango', 'min' => 1, 'max' => 60];
        }

        $horas = $dias * 24;
        $marcadores = self::marcadoresPeriodo($partida);
        $antes = self::resumenPueblo($partida, $catalog, $root);
        $diaInicio = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $horaInicio = (int) ($partida['reloj']['hora_actual'] ?? 0);

        $t0 = microtime(true);
        $adv = $service->avanzarRelojPasoAPaso($partida, $horas);
        $elapsedMs = (int) round((microtime(true) - $t0) * 1000);

        if (($adv['ok'] ?? true) === false) {
            return $adv;
        }

        $despues = self::resumenPueblo($partida, $catalog, $root);
        $diaFin = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $horaFin = (int) ($partida['reloj']['hora_actual'] ?? 0);

        $analisis = self::analizarPeriodo($partida, $catalog, $marcadores, $diaInicio, $diaFin);

        return [
            'ok' => true,
            'simulacion' => [
                'dias' => $dias,
                'horas' => $horas,
                'dia_inicio' => $diaInicio,
                'hora_inicio' => $horaInicio,
                'dia_fin' => $diaFin,
                'hora_fin' => $horaFin,
                'motor' => 'avanzarRelojPasoAPaso',
                'elapsed_ms' => $elapsedMs,
            ],
            'antes' => $antes,
            'despues' => $despues,
            'periodo' => $analisis,
            'export' => self::exportPeriodo(
                $partida,
                $antes,
                $despues,
                $analisis,
                $dias,
                $diaInicio,
                $horaInicio,
                $diaFin,
                $horaFin
            ),
            'reloj' => $adv['reloj'] ?? $partida['reloj'],
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function marcadoresPeriodo(array $partida): array
    {
        return [
            'bitacora' => count($partida['bitacora_relaciones'] ?? []),
            'audit' => count($partida['audit_trail'] ?? []),
            'historial_rel' => count($partida['historial_relaciones'] ?? []),
            'coincidencias' => count($partida['historial_coincidencias'] ?? []),
            'buzon' => count($partida['buzon'] ?? []),
            'conocidos' => self::mapaConocidos($partida),
            'social' => self::mapaSocial($partida),
            'romance' => self::mapaRomance($partida),
            'parejas' => self::parejasActuales($partida),
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $marcadores
     * @return array<string, mixed>
     */
    private static function analizarPeriodo(
        array $partida,
        Catalog $catalog,
        array $marcadores,
        int $diaInicio,
        int $diaFin
    ): array {
        $hitosNuevos = array_slice($partida['bitacora_relaciones'] ?? [], (int) $marcadores['bitacora']);
        $auditNuevos = array_slice($partida['audit_trail'] ?? [], (int) $marcadores['audit']);

        $conteos = self::contarHitos($hitosNuevos);
        $cambios = self::cambiosRelacionales($partida, $marcadores, $catalog);
        $cronologia = self::cronologiaPeriodo($partida, $diaInicio, $diaFin, $hitosNuevos, $auditNuevos);
        $actividad = self::actividadPeriodo($conteos, $auditNuevos, $partida, $marcadores, $diaInicio, $diaFin);
        $poblacion = self::deltaPoblacion($marcadores, $partida);

        return [
            'rango' => ['dia_inicio' => $diaInicio, 'dia_fin' => $diaFin],
            'poblacion' => $poblacion,
            'vida_social' => [
                'nuevos_conocidos' => $actividad['nuevos_conocidos'],
                'vinculos_aumentaron' => $actividad['vinculos_aumentaron'],
                'amistades_hito' => $conteos[RelacionBitacora::APOYO_IMPORTANTE] ?? 0,
            ],
            'romance' => [
                'flechazos' => $conteos[RelacionBitacora::FLECHAZO] ?? 0,
                'unilaterales_nuevos' => $actividad['interes_unilateral_nuevo'],
                'reciprocos_nuevos' => $actividad['interes_reciproco_nuevo'],
                'primeras_citas' => $conteos[RelacionBitacora::PRIMERA_CITA] ?? 0,
                'parejas_nuevas' => $actividad['parejas_nuevas'],
                'rupturas' => $conteos[RelacionBitacora::RUPTURA] ?? 0,
            ],
            'drama' => [
                'discusiones' => $conteos[RelacionBitacora::DISCUSION_FUERTE] ?? 0,
                'crisis' => $conteos[RelacionBitacora::CRISIS] ?? 0,
                'rupturas' => $conteos[RelacionBitacora::RUPTURA] ?? 0,
            ],
            'encuentros' => $actividad['encuentros'],
            'narrativa' => $actividad['narrativa'],
            'marchas' => $actividad['marchas'],
            'actividad_periodo' => $actividad['contadores'],
            'cambios_importantes' => $cambios,
            'cronologia' => $cronologia,
        ];
    }

    /**
     * @param list<array<string, mixed>> $hitos
     * @return array<string, int>
     */
    private static function contarHitos(array $hitos): array
    {
        $out = [];
        foreach ($hitos as $h) {
            if (!is_array($h)) {
                continue;
            }
            $t = (string) ($h['tipo'] ?? '');
            if ($t === '') {
                continue;
            }
            $out[$t] = ($out[$t] ?? 0) + 1;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $marcadores
     * @return list<array<string, mixed>>
     */
    private static function cambiosRelacionales(array $partida, array $marcadores, Catalog $catalog): array
    {
        $out = [];
        $antesCon = is_array($marcadores['conocidos'] ?? null) ? $marcadores['conocidos'] : [];
        $despuesCon = self::mapaConocidos($partida);
        foreach ($despuesCon as $clave => $val) {
            if ($val && empty($antesCon[$clave])) {
                [$a, $b] = explode('|', $clave, 2);
                $out[] = [
                    'tipo' => 'nuevo_conocido',
                    'texto' => IdentidadPublica::nombre($partida, $a) . ' + ' . IdentidadPublica::nombre($partida, $b) . ': se conocen',
                    'par' => [$a, $b],
                ];
            }
        }

        $antesSoc = is_array($marcadores['social'] ?? null) ? $marcadores['social'] : [];
        $despuesSoc = self::mapaSocial($partida);
        foreach ($despuesSoc as $clave => $val) {
            $prev = (int) ($antesSoc[$clave] ?? 0);
            if ($val > $prev + 15) {
                [$desde, $hacia] = explode('->', $clave, 2);
                $out[] = [
                    'tipo' => 'vinculo_social',
                    'texto' => IdentidadPublica::nombre($partida, $desde) . ' → ' . IdentidadPublica::nombre($partida, $hacia)
                        . ': vínculo social +' . ($val - $prev),
                    'par' => [$desde, $hacia],
                    'delta' => $val - $prev,
                ];
            }
        }

        $antesRom = is_array($marcadores['romance'] ?? null) ? $marcadores['romance'] : [];
        $despuesRom = self::mapaRomance($partida);
        foreach ($despuesRom as $clave => $val) {
            $prev = $antesRom[$clave] ?? null;
            if ($prev === null && $val !== null && $val > 0) {
                [$desde, $hacia] = explode('->', $clave, 2);
                $out[] = [
                    'tipo' => 'interes_nuevo',
                    'texto' => IdentidadPublica::nombre($partida, $desde) . ' → ' . IdentidadPublica::nombre($partida, $hacia)
                        . ': interés romántico ' . $val,
                    'par' => [$desde, $hacia],
                    'valor' => $val,
                ];
            }
        }

        foreach (array_slice($partida['bitacora_relaciones'] ?? [], (int) $marcadores['bitacora']) as $h) {
            if (!is_array($h)) {
                continue;
            }
            $tipo = (string) ($h['tipo'] ?? '');
            if (!isset(self::HITO_ETIQUETAS[$tipo])) {
                continue;
            }
            $parts = is_array($h['participantes'] ?? null) ? $h['participantes'] : [];
            if (count($parts) < 2) {
                continue;
            }
            $a = (string) $parts[0];
            $b = (string) $parts[1];
            $dir = (string) ($h['direccion'] ?? '');
            $etq = self::HITO_ETIQUETAS[$tipo];
            $texto = $dir !== '' && str_contains($dir, '>')
                ? IdentidadPublica::nombre($partida, explode('>', $dir)[0]) . ' → ' . IdentidadPublica::nombre($partida, explode('>', $dir)[1]) . ': ' . strtolower($etq)
                : IdentidadPublica::nombre($partida, $a) . ' + ' . IdentidadPublica::nombre($partida, $b) . ': ' . strtolower($etq);
            $out[] = [
                'tipo' => 'hito_' . $tipo,
                'texto' => $texto,
                'hito' => $h,
            ];
        }

        foreach ($partida['marchas']['intenciones'] ?? [] as $int) {
            if (!is_array($int)) {
                continue;
            }
            $dia = (int) ($int['detectado_dia'] ?? $int['dia'] ?? 0);
            if ($dia < (int) ($marcadores['dia_inicio'] ?? 0)) {
                continue;
            }
            $rid = (string) ($int['residente_id'] ?? '');
            if ($rid === '') {
                continue;
            }
            if (($int['estado'] ?? '') === 'activa' || ($int['estado'] ?? '') === 'pendiente') {
                $out[] = [
                    'tipo' => 'marcha_intencion',
                    'texto' => IdentidadPublica::nombre($partida, $rid) . ': intención de marcha',
                    'residente_id' => $rid,
                ];
            }
        }

        return array_slice($out, 0, 80);
    }

    /**
     * @param list<array<string, mixed>> $hitosNuevos
     * @param list<array<string, mixed>> $auditNuevos
     * @return list<array<string, mixed>>
     */
    private static function cronologiaPeriodo(
        array $partida,
        int $diaInicio,
        int $diaFin,
        array $hitosNuevos,
        array $auditNuevos
    ): array {
        $items = [];
        $vistos = [];

        foreach ($hitosNuevos as $h) {
            if (!is_array($h)) {
                continue;
            }
            $tipo = (string) ($h['tipo'] ?? '');
            $fecha = is_array($h['fecha'] ?? null) ? $h['fecha'] : [];
            $dia = (int) ($fecha['dia'] ?? 0);
            if ($dia < $diaInicio || $dia > $diaFin) {
                continue;
            }
            $parts = is_array($h['participantes'] ?? null) ? $h['participantes'] : [];
            $clave = 'hito|' . $tipo . '|' . $dia . '|' . implode(',', $parts);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $cat = self::CATEGORIA_HITO[$tipo] ?? 'SOCIAL';
            $items[] = [
                'categoria' => $cat,
                'dia' => $dia,
                'hora' => (int) ($fecha['hora'] ?? 0),
                'texto' => self::textoHito($partida, $h),
                'fuente' => 'bitacora_relaciones',
            ];
        }

        foreach ($auditNuevos as $e) {
            if (!is_array($e)) {
                continue;
            }
            $tipo = (string) ($e['tipo'] ?? '');
            $ts = is_array($e['ts_juego'] ?? null) ? $e['ts_juego'] : [];
            $dia = (int) ($ts['dia'] ?? 0);
            if ($dia < $diaInicio || $dia > $diaFin) {
                continue;
            }
            $cat = self::categoriaAudit($tipo);
            if ($cat === null) {
                continue;
            }
            $cid = (string) ($e['correlacion_id'] ?? '');
            $clave = 'audit|' . $tipo . '|' . $cid . '|' . $dia;
            if ($cid !== '' && isset($vistos[$clave])) {
                continue;
            }
            if ($cid !== '') {
                $vistos[$clave] = true;
            }
            $items[] = [
                'categoria' => $cat,
                'dia' => $dia,
                'hora' => (int) ($ts['hora'] ?? 0),
                'texto' => self::textoAudit($partida, $e),
                'fuente' => 'audit_trail',
            ];
        }

        usort($items, static function ($a, $b) {
            $ka = ($a['dia'] ?? 0) * 24 + ($a['hora'] ?? 0);
            $kb = ($b['dia'] ?? 0) * 24 + ($b['hora'] ?? 0);
            return $ka <=> $kb;
        });

        return array_slice($items, 0, 120);
    }

    /**
     * @param array<string, mixed> $marcadores
     * @return array<string, mixed>
     */
    private static function actividadPeriodo(
        array $conteos,
        array $auditNuevos,
        array $partida,
        array $marcadores,
        int $diaInicio,
        int $diaFin
    ): array {
        $antesCon = is_array($marcadores['conocidos'] ?? null) ? $marcadores['conocidos'] : [];
        $despuesCon = self::mapaConocidos($partida);
        $nuevosConocidos = 0;
        foreach ($despuesCon as $k => $v) {
            if ($v && empty($antesCon[$k])) {
                $nuevosConocidos++;
            }
        }

        $antesSoc = is_array($marcadores['social'] ?? null) ? $marcadores['social'] : [];
        $despuesSoc = self::mapaSocial($partida);
        $vinculosSuben = 0;
        foreach ($despuesSoc as $k => $v) {
            if ($v > (int) ($antesSoc[$k] ?? 0) + 5) {
                $vinculosSuben++;
            }
        }

        $encAutonomos = 0;
        $encOrganizados = 0;
        $encRechazados = 0;
        foreach ($auditNuevos as $e) {
            if (!is_array($e)) {
                continue;
            }
            $tipo = (string) ($e['tipo'] ?? '');
            if ($tipo === DomainEvents::ENCUENTRO_TERMINADO) {
                $despues = is_array($e['despues'] ?? null) ? $e['despues'] : [];
                $enc = is_array($despues['encuentro'] ?? null) ? $despues['encuentro'] : [];
                $origen = (string) ($enc['origen'] ?? '');
                if ($origen === 'jugador') {
                    $encOrganizados++;
                } else {
                    $encAutonomos++;
                }
            }
            if ($tipo === DomainEvents::ENCUENTRO_CANCELADO) {
                $encRechazados++;
            }
        }

        $cotilleosN = 0;
        $mensajitosN = 0;
        $offsetBuzon = (int) ($marcadores['buzon'] ?? 0);
        foreach (array_slice($partida['buzon'] ?? [], $offsetBuzon) as $m) {
            if (!is_array($m)) {
                continue;
            }
            $clas = (string) ($m['clasificacion'] ?? '');
            $canal = (string) ($m['canal'] ?? BuzonEngine::canalDe($clas));
            if ($clas === BuzonEngine::COTILLEO || $canal === BuzonEngine::CANAL_COTILLEO) {
                $cotilleosN++;
            } else {
                $mensajitosN++;
            }
        }

        $marchas = 0;
        foreach ($partida['marchas']['historial'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            $dia = (int) ($h['dia'] ?? 0);
            if ($dia >= $diaInicio && $dia <= $diaFin && ($h['resultado'] ?? '') === 'marchado') {
                $marchas++;
            }
        }

        $parejasAntes = array_map(static fn($p) => implode('|', $p['ids'] ?? []), $marcadores['parejas'] ?? []);
        $parejasDespues = array_map(static fn($p) => implode('|', $p['ids'] ?? []), self::parejasActuales($partida));
        $parejasNuevas = count(array_diff($parejasDespues, $parejasAntes));

        $unilateral = 0;
        $reciproco = 0;
        $rom = self::mapaRomance($partida);
        $antesRom = is_array($marcadores['romance'] ?? null) ? $marcadores['romance'] : [];
        $pares = [];
        foreach ($rom as $clave => $val) {
            if ($val === null || $val <= 0) {
                continue;
            }
            [$desde, $hacia] = explode('->', $clave, 2);
            $par = $desde < $hacia ? $desde . '|' . $hacia : $hacia . '|' . $desde;
            $pares[$par][$desde] = $val;
        }
        foreach ($pares as $dirs) {
            $vals = array_values($dirs);
            if (count($vals) >= 2 && $vals[0] > 0 && $vals[1] > 0) {
                $reciproco++;
            } elseif (count($vals) === 1 || (count($vals) >= 2 && ($vals[0] > 0) !== ($vals[1] > 0))) {
                $unilateral++;
            }
        }

        return [
            'nuevos_conocidos' => $nuevosConocidos,
            'vinculos_aumentaron' => $vinculosSuben,
            'interes_unilateral_nuevo' => $unilateral,
            'interes_reciproco_nuevo' => $reciproco,
            'parejas_nuevas' => $parejasNuevas,
            'encuentros' => [
                'autonomos' => $encAutonomos,
                'organizados_jugador' => $encOrganizados,
                'rechazados' => $encRechazados,
            ],
            'narrativa' => [
                'cotilleos' => $cotilleosN,
                'mensajitos' => $mensajitosN,
            ],
            'marchas' => $marchas,
            'contadores' => [
                'nuevos_conocidos' => $nuevosConocidos,
                'encuentros' => $encAutonomos + $encOrganizados,
                'flechazos' => $conteos[RelacionBitacora::FLECHAZO] ?? 0,
                'parejas_nuevas' => $parejasNuevas,
                'discusiones' => $conteos[RelacionBitacora::DISCUSION_FUERTE] ?? 0,
                'cotilleos' => $cotilleosN,
                'coincidencias' => max(0, count($partida['historial_coincidencias'] ?? []) - (int) ($marcadores['coincidencias'] ?? 0)),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $marcadores
     * @return array<string, mixed>
     */
    private static function deltaPoblacion(array $marcadores, array $partida): array
    {
        $antesRes = 0;
        foreach ($marcadores['conocidos'] ?? [] as $k => $v) {
            if ($v) {
                $antesRes++;
            }
        }
        $residentes = 0;
        foreach ($partida['residentes'] ?? [] as $res) {
            if (is_array($res) && ($res['presencia'] ?? '') === 'residente') {
                $residentes++;
            }
        }
        $incorporaciones = 0;
        foreach ($partida['llegadas']['historial'] ?? [] as $h) {
            if (is_array($h) && ($h['resultado'] ?? '') === 'incorporado') {
                $incorporaciones++;
            }
        }

        return [
            'residentes_final' => $residentes,
            'incorporaciones_periodo' => $incorporaciones,
            'candidato_activo' => $partida['llegadas']['candidato_activo'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     * @param array<string, mixed> $periodo
     * @return array{texto: string, json: array<string, mixed>}
     */
    public static function exportPeriodo(
        array $partida,
        array $antes,
        array $despues,
        array $periodo,
        int $dias,
        int $diaInicio,
        int $horaInicio,
        int $diaFin,
        int $horaFin
    ): array {
        $json = [
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'simulacion' => [
                'dias' => $dias,
                'dia_inicio' => $diaInicio,
                'hora_inicio' => $horaInicio,
                'dia_fin' => $diaFin,
                'hora_fin' => $horaFin,
            ],
            'antes' => $antes,
            'despues' => $despues,
            'periodo' => $periodo,
        ];

        $lineas = [];
        $lineas[] = '=== PLAY LAB · PERIODO SIMULADO ===';
        $lineas[] = 'Partida: ' . ($json['partida_id'] ?? '?');
        $lineas[] = 'Día ' . $diaInicio . ' ' . sprintf('%02d:00', $horaInicio) . ' → Día ' . $diaFin . ' ' . sprintf('%02d:00', $horaFin) . ' (' . $dias . ' días)';
        $lineas[] = '';
        $lineas[] = 'POBLACIÓN';
        $pobA = $antes['poblacion']['residentes'] ?? 0;
        $pobD = $despues['poblacion']['residentes'] ?? 0;
        $lineas[] = $pobA . ' → ' . $pobD . ' residentes';
        $lineas[] = '';
        $lineas[] = 'ACTIVIDAD DEL PERIODO';
        foreach ($periodo['actividad_periodo'] ?? [] as $k => $v) {
            $lineas[] = '  ' . $k . ': ' . $v;
        }
        $lineas[] = '';
        $lineas[] = 'CAMBIOS IMPORTANTES';
        foreach ($periodo['cambios_importantes'] ?? [] as $c) {
            $lineas[] = '  · ' . ($c['texto'] ?? '');
        }

        return ['texto' => implode("\n", $lineas), 'json' => $json];
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>>
     */
    private static function timelinePar(array $partida, string $a, string $b, Catalog $catalog): array
    {
        $items = [];
        $vistos = [];

        foreach (RelacionBitacora::entre($partida, $a, $b) as $h) {
            $fecha = is_array($h['fecha'] ?? null) ? $h['fecha'] : [];
            $clave = 'hito|' . ($h['tipo'] ?? '') . '|' . ($fecha['dia'] ?? 0);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $items[] = [
                'dia' => (int) ($fecha['dia'] ?? 0),
                'hora' => (int) ($fecha['hora'] ?? 0),
                'texto' => self::textoHito($partida, $h),
                'tipo' => $h['tipo'] ?? null,
                'fuente' => 'bitacora',
            ];
        }

        foreach (self::coincidenciasPar($partida, $a, $b) as $c) {
            $dia = (int) ($c['dia'] ?? 0);
            $clave = 'coin|' . $dia . '|' . ($c['lugar_id'] ?? '');
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $lug = self::nombreLugarId($partida, (string) ($c['lugar_id'] ?? ''));
            $items[] = [
                'dia' => $dia,
                'hora' => (int) ($c['hora'] ?? 0),
                'texto' => 'Coincidieron en ' . $lug,
                'tipo' => 'coincidencia',
                'fuente' => 'historial_coincidencias',
            ];
        }

        foreach (self::encuentrosPar($partida, $a, $b) as $enc) {
            $dia = (int) ($enc['dia'] ?? 0);
            $clave = 'enc|' . ($enc['id'] ?? '') . '|' . $dia;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $tipo = (string) ($enc['tipo'] ?? 'encuentro');
            $items[] = [
                'dia' => $dia,
                'hora' => (int) ($enc['hora'] ?? 0),
                'texto' => ucfirst($tipo) . ' (' . ($enc['estado'] ?? '') . ')',
                'tipo' => 'encuentro',
                'fuente' => 'encuentros',
            ];
        }

        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg) || (string) ($msg['tipo'] ?? '') !== 'senal_romantica') {
                continue;
            }
            $origen = is_array($msg['origen'] ?? null) ? $msg['origen'] : [];
            $rev = is_array($origen['informacion_revelada'] ?? null) ? $origen['informacion_revelada'] : [];
            $desde = (string) ($rev['desde'] ?? $msg['de_persona'] ?? '');
            $hacia = (string) ($rev['hacia'] ?? '');
            if (!in_array($a, [$desde, $hacia], true) || !in_array($b, [$desde, $hacia], true)) {
                continue;
            }
            $ts = is_array($msg['ts_juego'] ?? null) ? $msg['ts_juego'] : [];
            $dia = (int) ($ts['dia'] ?? $msg['dia'] ?? 0);
            $hora = (int) ($ts['hora'] ?? 0);
            $clave = 'senal|' . ($msg['id'] ?? '') . '|' . $desde . '>' . $hacia;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $items[] = [
                'dia' => $dia,
                'hora' => $hora,
                'texto' => CopySenalRomantica::textoDeMensaje($partida, $msg),
                'tipo' => 'senal_romantica',
                'fuente' => (string) ($origen['regla'] ?? 'buzon'),
                'motivo' => $rev['motivo'] ?? null,
                'direccion' => $desde . '>' . $hacia,
            ];
        }

        usort($items, static fn($x, $y) => (($x['dia'] ?? 0) * 24 + ($x['hora'] ?? 0)) <=> (($y['dia'] ?? 0) * 24 + ($y['hora'] ?? 0)));
        return $items;
    }

    /** @return array<string, bool> */
    private static function mapaConocidos(array $partida): array
    {
        $out = [];
        $ids = LabAudit::residentesActivos($partida);
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = $ids[$i];
                $b = $ids[$j];
                $clave = $a < $b ? $a . '|' . $b : $b . '|' . $a;
                $out[$clave] = RelacionEngine::seConocen($partida, $a, $b);
            }
        }
        return $out;
    }

    /** @return array<string, int> */
    private static function mapaSocial(array $partida): array
    {
        $out = [];
        foreach (LabAudit::residentesActivos($partida) as $desde) {
            foreach (LabAudit::residentesActivos($partida) as $hacia) {
                if ($desde === $hacia) {
                    continue;
                }
                $out[$desde . '->' . $hacia] = RelacionEngine::valorSocialHacia($partida, $desde, $hacia);
            }
        }
        return $out;
    }

    /** @return array<string, int|null> */
    private static function mapaRomance(array $partida): array
    {
        $out = [];
        foreach (LabAudit::residentesActivos($partida) as $desde) {
            foreach (LabAudit::residentesActivos($partida) as $hacia) {
                if ($desde === $hacia) {
                    continue;
                }
                $out[$desde . '->' . $hacia] = RelacionEngine::romanceHacia($partida, $desde, $hacia);
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $social
     * @param array<string, mixed> $romance
     * @return array<string, mixed>
     */
    private static function direccionPar(
        array $partida,
        string $desde,
        string $hacia,
        Catalog $catalog,
        array $social,
        array $romance
    ): array {
        $compat = CompatibilidadOculta::hacia($partida, $desde, $hacia);
        $romVal = RelacionEngine::romanceHacia($partida, $desde, $hacia);
        $soc = RelacionEngine::socialHacia($partida, $desde, $hacia);
        $flechazosDir = [];
        foreach ($romance['flechazos'] ?? [] as $f) {
            if (!is_array($f)) {
                continue;
            }
            if (($f['desde'] ?? '') === $desde && ($f['hacia'] ?? '') === $hacia) {
                $flechazosDir[] = $f;
            }
        }

        return [
            'desde' => $desde,
            'hacia' => $hacia,
            'desde_nombre' => IdentidadPublica::nombre($partida, $desde),
            'hacia_nombre' => IdentidadPublica::nombre($partida, $hacia),
            'social' => $soc,
            'confianza' => $soc['confianza'] ?? null,
            'afinidad' => $soc['afinidad'] ?? null,
            'romance' => $romVal,
            'compatibilidad_oculta' => $compat,
            'quimica' => QuimicaEngine::valorHacia($partida, $desde, $hacia),
            'flechazos' => $flechazosDir,
            'tiene_flechazo_hito' => RelacionBitacora::tienenHito($partida, $desde, $hacia, RelacionBitacora::FLECHAZO),
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>>
     */
    private static function listaVecinos(array $partida, Catalog $catalog): array
    {
        $out = [];
        foreach (LabAudit::residentesActivos($partida) as $id) {
            $res = $partida['residentes'][$id] ?? [];
            $out[] = [
                'id' => $id,
                'nombre' => IdentidadPublica::nombre($partida, $id),
                'retrato_url' => is_array($res) ? ($res['retrato_url'] ?? null) : null,
                'vivienda_id' => is_array($res) ? ($res['vivienda_id'] ?? null) : null,
            ];
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function parejasActuales(array $partida): array
    {
        $out = [];
        $vistos = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            if ((string) ($rel['estado_pareja'] ?? '') !== ParejaEngine::PAREJA) {
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a === '' || $b === '') {
                continue;
            }
            $clave = $a < $b ? $a . '|' . $b : $b . '|' . $a;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = [
                'ids' => [$a, $b],
                'nombres' => [
                    IdentidadPublica::nombre($partida, $a),
                    IdentidadPublica::nombre($partida, $b),
                ],
            ];
        }
        return $out;
    }

    private static function contarParesConocidos(array $partida): int
    {
        $n = 0;
        foreach (self::mapaConocidos($partida) as $v) {
            if ($v) {
                $n++;
            }
        }
        return $n;
    }

    /** @return array<string, mixed> */
    private static function metricasRomanceGlobales(array $partida): array
    {
        $intereses = 0;
        $unilaterales = 0;
        $reciprocos = 0;
        $flechazos = 0;
        $pares = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $flechazos += count($rel['flechazos'] ?? []);
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a === '' || $b === '') {
                continue;
            }
            $va = RelacionEngine::romanceHacia($partida, $a, $b) ?? 0;
            $vb = RelacionEngine::romanceHacia($partida, $b, $a) ?? 0;
            if ($va > 0 || $vb > 0) {
                $intereses++;
            }
            $clave = $a < $b ? $a . '|' . $b : $b . '|' . $a;
            $pares[$clave] = [$va, $vb];
        }
        foreach ($pares as $vals) {
            if ($vals[0] > 0 && $vals[1] > 0) {
                $reciprocos++;
            } elseif ($vals[0] > 0 || $vals[1] > 0) {
                $unilaterales++;
            }
        }
        return [
            'intereses_activos' => $intereses,
            'unilaterales' => $unilaterales,
            'reciprocos' => $reciprocos,
            'flechazos_total' => $flechazos,
        ];
    }

    private static function contarCrisisActivas(array $partida): int
    {
        $n = 0;
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (is_array($rel) && ($rel['estado_pareja'] ?? '') === ParejaEngine::CRISIS) {
                $n++;
            }
        }
        return $n;
    }

    /** @return array<string, mixed> */
    private static function intencionesMarcha(array $partida): array
    {
        $activas = [];
        foreach ($partida['marchas']['intenciones'] ?? [] as $int) {
            if (!is_array($int)) {
                continue;
            }
            $est = (string) ($int['estado'] ?? '');
            if (!in_array($est, ['activa', 'pendiente', 'abierta'], true)) {
                continue;
            }
            $rid = (string) ($int['residente_id'] ?? '');
            if ($rid === '') {
                continue;
            }
            $activas[] = [
                'residente_id' => $rid,
                'nombre' => IdentidadPublica::nombre($partida, $rid),
                'estado' => $est,
                'causa' => $int['causa'] ?? null,
            ];
        }
        return ['activas' => count($activas), 'lista' => $activas];
    }

    /** @return array<string, mixed> */
    private static function metricasBuzon(array $partida): array
    {
        $cotilleos = 0;
        $decision = 0;
        $lectura = 0;
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            $clas = (string) ($m['clasificacion'] ?? '');
            $canal = (string) ($m['canal'] ?? BuzonEngine::canalDe($clas));
            if ($clas === BuzonEngine::COTILLEO || $canal === BuzonEngine::CANAL_COTILLEO) {
                $cotilleos++;
            }
            if (BuzonEngine::tieneDecisionPendiente($m)) {
                $decision++;
            } elseif (($m['estado'] ?? '') === 'pendiente') {
                $lectura++;
            }
        }
        return [
            'cotilleos' => $cotilleos,
            'mensajitos_decision' => $decision,
            'mensajitos_lectura' => $lectura,
        ];
    }

    /** @return array<string, mixed> */
    private static function metricasLlegadas(array $partida): array
    {
        return [
            'candidato_activo' => $partida['llegadas']['candidato_activo'] ?? null,
            'en_camino' => $partida['llegadas']['candidato_en_camino'] ?? null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function cotilleosRecientes(array $partida, int $n): array
    {
        $out = [];
        foreach (array_reverse($partida['buzon'] ?? []) as $m) {
            if (!is_array($m)) {
                continue;
            }
            $clas = (string) ($m['clasificacion'] ?? '');
            $canal = (string) ($m['canal'] ?? BuzonEngine::canalDe($clas));
            if ($clas !== BuzonEngine::COTILLEO && $canal !== BuzonEngine::CANAL_COTILLEO) {
                continue;
            }
            $out[] = [
                'id' => $m['id'] ?? null,
                'texto' => substr((string) ($m['texto'] ?? $m['cuerpo'] ?? ''), 0, 120),
                'dia' => $m['dia'] ?? null,
            ];
            if (count($out) >= $n) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $perfil
     * @return array<string, mixed>
     */
    private static function perfilLegibleMotor(array $perfil, CatalogStore $store): array
    {
        $resolver = static function (array $ids, string $cat) use ($store): array {
            $out = [];
            foreach ($ids as $id) {
                if (!is_string($id) || $id === '') {
                    continue;
                }
                $item = $store->item($cat, $id);
                $out[] = ['id' => $id, 'nombre' => is_array($item) ? ($item['nombre'] ?? $id) : $id];
            }
            return $out;
        };
        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];
        return [
            'hobby_principal' => $perfil['hobby_principal'] ?? null,
            'hobbies' => $resolver($perfil['hobbies_secundarios'] ?? ($perfil['hobbies'] ?? []), 'hobbies'),
            'rasgos_publicos' => $resolver($perfil['rasgos'] ?? [], 'rasgos'),
            'rasgos_ocultos' => $resolver($perfil['rasgos_ocultos'] ?? [], 'rasgos'),
            'preferencias' => $prefs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function agendaResidente(array $partida, string $residenteId): array
    {
        $planes = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            if (!in_array($residenteId, $parts, true)) {
                continue;
            }
            $planes[] = self::vistaEncuentroBreve($partida, $enc);
        }
        return ['encuentros' => $planes];
    }

    /**
     * @return array<string, mixed>
     */
    private static function vidaSocialResidente(array $partida, string $rid, Catalog $catalog, bool $soloConocidos): array
    {
        $conocidos = [];
        $amistades = [];
        $romances = [];
        $pareja = null;
        $exs = [];
        $conflictos = [];

        foreach (LabAudit::residentesActivos($partida) as $otro) {
            if ($otro === $rid) {
                continue;
            }
            $seConocen = RelacionEngine::seConocen($partida, $rid, $otro);
            if ($soloConocidos && !$seConocen) {
                continue;
            }
            $nom = IdentidadPublica::nombre($partida, $otro);
            if ($seConocen) {
                $conocidos[] = ['id' => $otro, 'nombre' => $nom];
            }
            $soc = RelacionEngine::valorSocialHacia($partida, $rid, $otro);
            if ($soc >= 40) {
                $amistades[] = ['id' => $otro, 'nombre' => $nom, 'valor' => $soc];
            }
            $rom = RelacionEngine::romanceHacia($partida, $rid, $otro);
            if ($rom !== null && $rom > 0) {
                $romances[] = ['id' => $otro, 'nombre' => $nom, 'valor' => $rom];
            }
            $est = ParejaEngine::estado($partida, $rid, $otro);
            if ($est === ParejaEngine::PAREJA) {
                $pareja = ['id' => $otro, 'nombre' => $nom];
            } elseif ($est === ParejaEngine::EX) {
                $exs[] = ['id' => $otro, 'nombre' => $nom];
            }
        }

        foreach ($partida['relaciones_conflicto'] ?? [] as $c) {
            if (!is_array($c)) {
                continue;
            }
            $a = (string) ($c['persona_a'] ?? '');
            $b = (string) ($c['persona_b'] ?? '');
            if ($a !== $rid && $b !== $rid) {
                continue;
            }
            $otro = $a === $rid ? $b : $a;
            $conflictos[] = ['id' => $otro, 'nombre' => IdentidadPublica::nombre($partida, $otro), 'nivel' => $c['nivel'] ?? null];
        }

        return [
            'conocidos' => $conocidos,
            'amistades' => $amistades,
            'intereses_romanticos' => $romances,
            'pareja' => $pareja,
            'ex' => $exs,
            'conflictos' => $conflictos,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function descubrimientosJugador(array $partida, string $residenteId): array
    {
        $out = [];
        foreach ($partida['descubrimientos'] ?? [] as $d) {
            if (!is_array($d)) {
                continue;
            }
            if (($d['residente_id'] ?? '') !== $residenteId) {
                continue;
            }
            $out[] = $d;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $enc
     * @return array<string, mixed>
     */
    private static function vistaEncuentroBreve(array $partida, array $enc): array
    {
        $dia = (int) ($enc['dia'] ?? 0);
        $hora = (int) ($enc['hora'] ?? 0);
        $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        $nombres = [];
        foreach ($parts as $pid) {
            if (is_string($pid) && $pid !== '') {
                $nombres[] = IdentidadPublica::nombre($partida, $pid);
            }
        }
        return [
            'id' => $enc['id'] ?? null,
            'dia' => $dia,
            'hora' => $hora,
            'abs' => $dia * 24 + $hora,
            'estado' => $enc['estado'] ?? null,
            'tipo' => $enc['tipo'] ?? null,
            'origen' => $enc['origen'] ?? null,
            'participantes' => $nombres,
        ];
    }

    private static function absolutoReloj(array $partida): int
    {
        $r = $partida['reloj'] ?? [];
        return (int) ($r['dia_pueblo'] ?? 1) * 24 + (int) ($r['hora_actual'] ?? 0);
    }

    /**
     * @param array<string, mixed> $h
     */
    private static function textoHito(array $partida, array $h): string
    {
        $tipo = (string) ($h['tipo'] ?? '');
        $etq = self::HITO_ETIQUETAS[$tipo] ?? $tipo;
        $parts = is_array($h['participantes'] ?? null) ? $h['participantes'] : [];
        if (count($parts) >= 2) {
            $a = IdentidadPublica::nombre($partida, (string) $parts[0]);
            $b = IdentidadPublica::nombre($partida, (string) $parts[1]);
            $dir = (string) ($h['direccion'] ?? '');
            if ($dir !== '' && str_contains($dir, '>')) {
                [$d, $hacia] = explode('>', $dir, 2);
                return IdentidadPublica::nombre($partida, $d) . ' → ' . IdentidadPublica::nombre($partida, $hacia) . ': ' . strtolower($etq);
            }
            return $a . ' + ' . $b . ': ' . strtolower($etq);
        }
        return $etq;
    }

    /**
     * @param array<string, mixed> $e
     */
    private static function textoAudit(array $partida, array $e): string
    {
        $tipo = (string) ($e['tipo'] ?? '');
        $actores = is_array($e['actores'] ?? null) ? $e['actores'] : [];
        $nombres = [];
        foreach ($actores as $id) {
            if (is_string($id) && $id !== '') {
                $nombres[] = IdentidadPublica::nombre($partida, $id);
            }
        }
        $quien = $nombres !== [] ? implode(' + ', $nombres) : '';
        switch ($tipo) {
            case DomainEvents::ENCUENTRO_TERMINADO:
                return 'Encuentro ' . $quien;
            case DomainEvents::ENCUENTRO_INICIADO:
                return 'Comienza encuentro ' . $quien;
            case DomainEvents::COINCIDENCIA_RESIDENTES:
                return 'Coincidencia ' . $quien;
            case DomainEvents::RELACION_MODIFICADA:
                return 'Relación modificada ' . $quien;
            case DomainEvents::MARCHA_INTENCION:
                return 'Intención de marcha';
            case DomainEvents::MARCHA_EFECTIVA:
                return 'Marcha efectiva';
            default:
                return $tipo;
        }
    }

    private static function categoriaAudit(string $tipo): ?string
    {
        switch ($tipo) {
            case DomainEvents::ENCUENTRO_INICIADO:
            case DomainEvents::ENCUENTRO_TERMINADO:
            case DomainEvents::ENCUENTRO_CANCELADO:
                return 'ENCUENTROS';
            case DomainEvents::COINCIDENCIA_RESIDENTES:
                return 'SOCIAL';
            case DomainEvents::RELACION_MODIFICADA:
                return 'SOCIAL';
            case DomainEvents::MARCHA_INTENCION:
            case DomainEvents::MARCHA_EFECTIVA:
                return 'MARCHAS';
            default:
                return null;
        }
    }

    /** @return list<array<string, mixed>> */
    private static function coincidenciasPar(array $partida, string $a, string $b): array
    {
        $out = [];
        foreach ($partida['historial_coincidencias'] ?? [] as $c) {
            if (!is_array($c)) {
                continue;
            }
            $ids = is_array($c['residentes'] ?? null) ? $c['residentes'] : [];
            if (in_array($a, $ids, true) && in_array($b, $ids, true)) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function encuentrosPar(array $partida, string $a, string $b): array
    {
        $out = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            if (in_array($a, $parts, true) && in_array($b, $parts, true)) {
                $out[] = $enc;
            }
        }
        return $out;
    }

    private static function nombreLugarId(array $partida, string $lugarId): string
    {
        if ($lugarId === '') {
            return 'el pueblo';
        }
        foreach ($partida['mapa']['lugares'] ?? [] as $lug) {
            if (is_array($lug) && ($lug['id'] ?? '') === $lugarId) {
                return (string) ($lug['nombre'] ?? $lugarId);
            }
        }
        return $lugarId;
    }
}
