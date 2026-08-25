<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Instrumentación LAB: expone datos REALES del motor para auditoría manual.
 * Solo lectura / serialización; no altera gameplay ni fórmulas.
 */
final class LabAudit
{
    /** @var list<array<string, mixed>> */
    private static array $buffer = [];
    /** @var list<array<string, mixed>> Trazas DEV de tiradas/resoluciones REALES (solo request actual). */
    private static array $tiradas = [];
    private static ?string $ctxTirada = null;

    public static function reset(): void
    {
        self::$buffer = [];
        self::$tiradas = [];
        self::$ctxTirada = null;
    }

    /* ============================================================
     * TELEMETRÍA DEV OBSERVACIONAL (caja negra de jugabilidad).
     * Solo lectura de valores ya calculados: no consume RNG,
     * no altera pesos, cargas ni resultados, no persiste por sí misma.
     *============================================================ */

    /**
     * Fijar contexto de resolución para las trazas (p.ej. 'intervencion:enc_x').
     */
    public static function ctxAbrir(?string $ctx): void
    {
        self::$ctxTirada = $ctx;
    }

    public static function capturaActiva(): bool
    {
        return self::activaEnRequest();
    }

    /**
     * Registra una tirada REAL de AzarPonderado con todos sus componentes.
     */
    public static function obsTirada(
        int $rngStatePost,
        array $resultados,
        float $carga,
        array $pesos,
        float $sumPesos,
        float $pick,
        int $idx,
        string $resultado
    ): void {
        if (!self::capturaActiva()) {
            return;
        }
        self::$tiradas[] = [
            'familia' => 'tirada_azar_ponderado',
            'ctx' => self::$ctxTirada,
            'rng_state_post' => $rngStatePost,
            'resultados_posibles' => $resultados,
            'carga' => $carga,
            'pesos' => $pesos,
            'sum_pesos' => $sumPesos,
            'pick' => $pick,
            'idx_elegido' => $idx,
            'resultado' => $resultado,
        ];
    }

    /**
     * Registra componentes reales de una resolución (p.ej. desglose de cargaBase).
     *
     * @param array<string, mixed> $datos
     */
    public static function obsResolucion(string $familia, array $datos): void
    {
        if (!self::capturaActiva()) {
            return;
        }
        self::$tiradas[] = array_merge([
            'familia' => $familia,
            'ctx' => self::$ctxTirada,
        ], $datos);
    }

    /**
     * Trazas capturadas bajo un contexto ('intervencion:enc_x', 'encuentro_final:enc_y').
     *
     * @return list<array<string, mixed>>
     */
    public static function trazaCtx(string $ctxPrefix): array
    {
        $out = [];
        foreach (self::$tiradas as $t) {
            $ctx = (string) ($t['ctx'] ?? '');
            if ($ctx === '' || !str_starts_with($ctx, $ctxPrefix)) {
                continue;
            }
            unset($t['ctx']);
            $out[] = $t;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function activa(array $body): bool
    {
        if (isset($body['debug']) && (string) $body['debug'] !== '' && (string) $body['debug'] !== '0') {
            return true;
        }
        if (isset($body['lab']) && (string) $body['lab'] !== '' && (string) $body['lab'] !== '0') {
            return true;
        }
        if (isset($_GET['debug']) && (string) $_GET['debug'] !== '' && (string) $_GET['debug'] !== '0') {
            return true;
        }
        if (isset($_GET['lab']) && (string) $_GET['lab'] !== '' && (string) $_GET['lab'] !== '0') {
            return true;
        }
        return false;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public static function push(string $tag, string $prefijo, array $datos): array
    {
        $entry = [
            'tag' => $tag,
            'prefijo' => $prefijo,
            'ts' => date('c'),
            'datos' => $datos,
        ];
        self::$buffer[] = $entry;
        return $entry;
    }

    /**
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     * @param array<string, mixed> $extra
     */
    public static function eventoEmocion(
        array $partida,
        string $residenteId,
        array $antes,
        array $despues,
        string $regla,
        array $extra = []
    ): void {
        self::push('EMOCION', '[AHT DEBUG EMOCION]', array_merge([
            'npc' => IdentidadPublica::nombre($partida, $residenteId),
            'npc_id' => $residenteId,
            'antes' => EstadoEmocional::canonId((string) ($antes['id'] ?? EstadoEmocional::NEUTRO)),
            'despues' => EstadoEmocional::canonId((string) ($despues['id'] ?? EstadoEmocional::NEUTRO)),
            'origen' => $despues['origen'] ?? null,
            'regla' => $regla,
            'dia' => $despues['desde']['dia'] ?? ($partida['reloj']['dia_pueblo'] ?? null),
            'hora' => $despues['desde']['hora'] ?? ($partida['reloj']['hora_actual'] ?? null),
            'hasta' => $despues['hasta'] ?? null,
            'duracion_horas' => $despues['duracion_horas'] ?? null,
            'contexto' => $despues['contexto'] ?? [],
            'reloj' => $partida['reloj'] ?? null,
        ], $extra));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function flush(): array
    {
        $out = self::$buffer;
        self::$buffer = [];
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function attach(array $response): array
    {
        $eventos = self::flush();
        if ($eventos === []) {
            return $response;
        }
        $response['lab_audit'] = ['eventos' => $eventos];
        return $response;
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function eventoNuevaPartida(array $partida, Catalog $catalog): void
    {
        $npcs = [];
        foreach (self::residentesActivos($partida) as $id) {
            $npcs[] = self::perfilNpc($partida, $id, $catalog);
        }
        $matriz = self::matrizRelacional($partida, $catalog);
        self::push('PARTIDA', '[AHT DEBUG PARTIDA]', [
            'evento' => 'NUEVA_PARTIDA',
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'config_id' => $partida['meta']['config_id'] ?? null,
            'seed' => $partida['meta']['seed'] ?? null,
            'schema_version' => $partida['meta']['schema_version'] ?? null,
            'rng' => $partida['rng'] ?? null,
            'reloj' => $partida['reloj'] ?? null,
            'vecinos' => $npcs,
            'matriz_relacional' => $matriz,
            'nota_relaciones' => self::notaRelacionesMotor(),
        ]);
        foreach ($npcs as $npc) {
            self::push('NPC', '[AHT DEBUG NPC]', $npc);
        }
        foreach ($matriz as $par) {
            $dirs = $par['direcciones'] ?? [];
            if (is_array($dirs['a_hacia_b'] ?? null)) {
                self::push('REL', '[AHT DEBUG REL]', $dirs['a_hacia_b']);
            }
            if (is_array($dirs['b_hacia_a'] ?? null)) {
                self::push('REL', '[AHT DEBUG REL]', $dirs['b_hacia_a']);
            }
        }
    }

    /**
     * Snapshot completo al cargar partida existente en LAB.
     *
     * @param array<string, mixed> $partida
     */
    public static function eventoSnapshotCargada(array $partida, Catalog $catalog): void
    {
        self::eventoNuevaPartida($partida, $catalog);
        self::push('PARTIDA', '[AHT DEBUG PARTIDA]', [
            'evento' => 'PARTIDA_CARGADA',
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'encuentros_activos' => count(EncuentroEngine::listarActivos($partida)),
            'nota' => 'Snapshot al cargar partida existente (?lab=1).',
        ]);
    }

    /**
     * Snapshot LAB ligero en bootstrap (partida.refresh): sin matriz relacional.
     * Matriz completa: partida.inspeccionar o partida.debug_export.
     *
     * @param array<string, mixed> $partida
     */
    public static function eventoSnapshotRefreshBootstrap(array $partida): void
    {
        self::push('PARTIDA', '[AHT DEBUG PARTIDA]', [
            'evento' => 'REFRESH_UI',
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'config_id' => $partida['meta']['config_id'] ?? null,
            'reloj' => $partida['reloj'] ?? null,
            'vecinos_activos' => count(self::residentesActivos($partida)),
            'encuentros_activos' => count(EncuentroEngine::listarActivos($partida)),
            'nota' => 'Bootstrap partida.refresh sin matriz. Matriz: partida.inspeccionar o partida.debug_export.',
        ]);
    }

    /**
     * Snapshot LAB para partida.refresh con matriz (auditoría profunda bajo demanda).
     *
     * @param array<string, mixed> $partida
     */
    public static function eventoSnapshotRefreshUi(array $partida, Catalog $catalog): void
    {
        self::push('PARTIDA', '[AHT DEBUG PARTIDA]', [
            'evento' => 'REFRESH_UI',
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'config_id' => $partida['meta']['config_id'] ?? null,
            'reloj' => $partida['reloj'] ?? null,
            'vecinos_activos' => count(self::residentesActivos($partida)),
            'encuentros_activos' => count(EncuentroEngine::listarActivos($partida)),
            'matriz_relacional' => self::matrizRelacional($partida, $catalog),
            'nota_relaciones' => self::notaRelacionesMotor(),
            'nota' => 'Snapshot compacto en partida.refresh (un evento; sin explosión NPC/REL).',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function notaRelacionesMotor(): array
    {
        return [
            'quimica' => 'QuimicaEngine::asegurarPar al incorporar (RNG, no lazy).',
            'compatibilidad_oculta' => 'CompatibilidadOculta::asegurarDireccional al incorporar (CompatibilidadCalculator).',
            'social_romance' => 'RelacionGrafo::asegurarTodos al incorporar; valores iniciales en relaciones_*.',
            'presencia_mapa' => 'PresenciaEngine: encuentro programado > plan autónomo > rutina lugares_preferentes (solo visual).',
        ];
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function eventoNuevoVecino(array $partida, string $nuevoId, Catalog $catalog): void
    {
        $existentes = array_values(array_filter(
            self::residentesActivos($partida),
            static fn(string $id): bool => $id !== $nuevoId
        ));
        $rels = [];
        foreach ($existentes as $otro) {
            $dirAb = self::parDireccional($partida, $nuevoId, $otro, $catalog);
            $dirBa = self::parDireccional($partida, $otro, $nuevoId, $catalog);
            $rels[] = [
                'par' => [$nuevoId, $otro],
                'direcciones' => [
                    'a_hacia_b' => $dirAb,
                    'b_hacia_a' => $dirBa,
                ],
            ];
            self::push('REL', '[AHT DEBUG REL]', $dirAb);
            self::push('REL', '[AHT DEBUG REL]', $dirBa);
        }
        self::push('NPC', '[AHT DEBUG NPC]', [
            'evento' => 'NUEVO_VECINO',
            'vecino' => self::perfilNpc($partida, $nuevoId, $catalog),
            'residentes_previos' => $existentes,
            'relaciones_nuevas' => $rels,
            'rng' => $partida['rng'] ?? null,
        ]);
        self::push('LLEGADA', '[AHT DEBUG LLEGADA]', [
            'residente_id' => $nuevoId,
            'relaciones' => $rels,
        ]);
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $respuestaApi
     */
    public static function eventoPlan(array $partida, array $respuestaApi, Catalog $catalog): void
    {
        $prop = is_array($respuestaApi['propuesta'] ?? null) ? $respuestaApi['propuesta'] : [];
        $parts = is_array($prop['participantes'] ?? null) ? $prop['participantes'] : [];
        $a = (string) ($parts[0] ?? '');
        $b = (string) ($parts[1] ?? '');
        $cal = CalibracionConfig::load($catalog->getRoot());

        $desgloses = [];
        foreach ($parts as $pid) {
            if (!is_string($pid) || $pid === '') {
                continue;
            }
            $otro = $pid === $a ? $b : $a;
            if ($otro === '') {
                continue;
            }
            $desgloses[$pid] = Voluntad\VoluntadPonderadaEvaluator::desglose($partida, $prop, $pid, $otro, $cal);
        }

        $reacciones = [];
        foreach ($prop['reacciones'] ?? [] as $reac) {
            if (!is_array($reac)) {
                continue;
            }
            $reacciones[] = $reac;
        }

        $resultado = 'PENDIENTE';
        if (!empty($respuestaApi['programado'])) {
            $resultado = 'ACEPTADA_Y_PROGRAMADA';
        } elseif (!empty($respuestaApi['rechazada'])) {
            $resultado = 'RECHAZADA';
        } elseif (($prop['estado'] ?? '') === 'rechazada') {
            $resultado = 'RECHAZADA';
        } elseif (($prop['estado'] ?? '') === 'aceptada') {
            $resultado = 'ACEPTADA';
        }

        self::push('PLAN', '[AHT DEBUG PLAN]', [
            'participantes' => array_map(static function (string $id) use ($partida): array {
                return ['id' => $id, 'nombre' => IdentidadPublica::nombre($partida, $id)];
            }, array_filter($parts, 'is_string')),
            'tipo' => $prop['tipo'] ?? null,
            'lugar' => $prop['lugar'] ?? null,
            'dia' => $prop['dia'] ?? null,
            'hora' => $prop['hora'] ?? null,
            'hora_solicitada' => $prop['hora_solicitada'] ?? null,
            'hora_ajustada' => $prop['hora_ajustada'] ?? false,
            'relacion_a_b' => $a !== '' && $b !== '' ? self::parDireccional($partida, $a, $b, $catalog) : null,
            'relacion_b_a' => $a !== '' && $b !== '' ? self::parDireccional($partida, $b, $a, $catalog) : null,
            'organizar_motivo' => $a !== '' && $b !== ''
                ? OrganizarMotivo::de($partida, $a, $b, (string) ($prop['tipo'] ?? ''), $cal)
                : null,
            'estado_emocional' => self::emocionesParticipantes($partida, $parts),
            'voluntad_desglose' => $desgloses,
            'reacciones_motor' => $reacciones,
            'resultado' => $resultado,
            'error_api' => $respuestaApi['error'] ?? null,
            'rechazo_clase' => $respuestaApi['rechazo_clase'] ?? null,
            'rechazo_tipo' => $respuestaApi['rechazo_tipo'] ?? null,
            'contrapropuesta' => $respuestaApi['contrapropuesta'] ?? null,
            'mensaje_ui' => $respuestaApi['mensaje_ui'] ?? null,
            'causas_reales' => self::causasPlan($reacciones, $respuestaApi),
        ]);
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $enc
     */
    public static function eventoEncuentro(array $partida, array $enc, Catalog $catalog): void
    {
        $res = is_array($enc['resultado'] ?? null) ? $enc['resultado'] : [];
        $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        $hist = self::ultimoHistorialPar($partida, $parts);

        self::push('ENCUENTRO', '[AHT DEBUG ENCUENTRO]', [
            'encuentro_id' => $enc['id'] ?? null,
            'tipo' => $enc['tipo'] ?? null,
            'lugar' => $enc['lugar'] ?? null,
            'dia' => $enc['dia'] ?? null,
            'hora' => $enc['hora'] ?? null,
            'estado' => $enc['estado'] ?? null,
            'participantes' => $parts,
            'antes' => [
                'relaciones' => self::relacionesParticipantes($partida, $parts, $catalog),
                'emociones' => self::emocionesParticipantes($partida, $parts),
                'historial_par' => $hist['antes'] ?? null,
            ],
            'eventos_factores' => [
                'resultado_motor' => $res,
                'delta_social' => $res['delta_social'] ?? null,
                'delta_romance' => $res['delta_romance'] ?? null,
                'deltas_reales' => $res['_deltas_reales'] ?? null,
                'historial_par' => $hist['entrada'] ?? null,
            ],
            'despues' => [
                'relaciones' => self::relacionesParticipantes($partida, $parts, $catalog),
                'emociones' => self::emocionesParticipantes($partida, $parts),
            ],
            'vista_jugador' => EncuentroResultadoVista::de($partida, $enc, $catalog, $catalog->getRoot()),
        ]);
    }

    /**
     * @param list<string> $antes
     * @param array<string, mixed> $partida
     */
    public static function eventosNuevosResidentes(array $antes, array $partida, Catalog $catalog): void
    {
        $ahora = self::residentesActivos($partida);
        foreach (array_diff($ahora, $antes) as $id) {
            self::eventoNuevoVecino($partida, $id, $catalog);
        }
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function eventoVidaDesdeIndice(array $partida, int $desdeIndice): void
    {
        $ledger = is_array($partida['vida_pueblo']['ledger'] ?? null) ? $partida['vida_pueblo']['ledger'] : [];
        if ($desdeIndice >= count($ledger)) {
            return;
        }
        $slice = array_slice($ledger, $desdeIndice);
        foreach ($slice as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            self::eventoVidaCambio(
                $partida,
                (int) ($entry['valor_antes'] ?? 0),
                (int) ($entry['valor_despues'] ?? 0),
                (int) ($entry['delta'] ?? 0),
                (string) ($entry['causa'] ?? ''),
                (string) ($entry['origen'] ?? ''),
                $entry
            );
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function eventoVidaCambio(
        array $partida,
        int $antes,
        int $despues,
        int $delta,
        string $causa,
        string $origen,
        array $meta = []
    ): void {
        self::push('VIDA', '[AHT DEBUG VIDA PUEBLO]', [
            'antes' => $antes,
            'delta' => $delta,
            'despues' => $despues,
            'origen' => $causa,
            'origen_meta' => $origen,
            'id' => $meta['fuente_id'] ?? $meta['id'] ?? null,
            'contexto' => $meta['detalle'] ?? $meta['motivo'] ?? null,
            'dia' => $meta['dia'] ?? ($partida['reloj']['dia_pueblo'] ?? null),
            'hora' => $meta['hora'] ?? ($partida['reloj']['hora_actual'] ?? null),
            'positivo_valido_latido' => $meta['positivo_valido_latido'] ?? null,
            'latido' => $meta['latido'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function eventoLlegadaEnCamino(array $partida, array $enCamino): void
    {
        self::push('LLEGADA', '[AHT DEBUG LLEGADA]', [
            'evento' => 'ACEPTADO_EN_CAMINO',
            'en_camino' => $enCamino,
            'rng' => $partida['rng'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function eventoMisionesDia(array $partida, string $projectRoot): void
    {
        $cal = CalibracionConfig::load($projectRoot);
        $vista = MisionDiariaEngine::vistaHoy($partida, $cal);
        if (($vista['misiones'] ?? []) === []) {
            return;
        }
        self::push('MISION', '[AHT DEBUG MISION]', [
            'dia' => $vista['dia'] ?? null,
            'plazo_humano' => $vista['plazo_humano'] ?? null,
            'misiones' => $vista['misiones'] ?? [],
        ]);
    }

    /**
     * @param array<string, string> $antes id => estado
     * @param array<string, mixed> $partida
     */
    public static function eventosEncuentrosTerminados(array $antes, array $partida, Catalog $catalog): void
    {
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (!is_array($enc) || empty($enc['id'])) {
                continue;
            }
            $id = (string) $enc['id'];
            $est = (string) ($enc['estado'] ?? '');
            if ($est !== 'terminado') {
                continue;
            }
            if (($antes[$id] ?? '') === 'terminado') {
                continue;
            }
            self::eventoEncuentro($partida, $enc, $catalog);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function snapshotEstadosEncuentros(array $partida): array
    {
        $out = [];
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (!is_array($enc) || empty($enc['id'])) {
                continue;
            }
            $out[(string) $enc['id']] = (string) ($enc['estado'] ?? '');
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<string>
     */
    public static function residentesActivos(array $partida): array
    {
        $out = [];
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            if (!is_array($res)) {
                continue;
            }
            if (($res['presencia'] ?? 'residente') !== 'residente') {
                continue;
            }
            $out[] = $id;
        }
        sort($out);
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function perfilNpc(array $partida, string $id, Catalog $catalog): array
    {
        $res = is_array($partida['residentes'][$id] ?? null) ? $partida['residentes'][$id] : [];
        $cat = null;
        if (empty($res['_placeholder'])) {
            try {
                $cat = ResidenteRuntime::catalogoParaRuntime($res, $catalog);
            } catch (\Throwable $ignored) {
                $cat = null;
            }
        }
        $perfil = PerfilPartida::de($partida, $id) ?? PerfilPartida::deOLegacy($partida, $id, $catalog);
        $store = $catalog->store();

        return [
            'id' => $id,
            'catalog_id' => $res['catalog_id'] ?? $id,
            'nombre' => IdentidadPublica::nombre($partida, $id),
            'vivienda_id' => $res['vivienda_id'] ?? null,
            'presencia' => $res['presencia'] ?? null,
            'edad_resuelta' => PerfilPartida::edadResuelta($partida, $id, $catalog),
            'genero' => $res['identidad_publica']['genero']
                ?? (is_array($cat) ? ($cat['identidad']['genero'] ?? null) : null),
            'identidad_catalogo' => is_array($cat) ? IdentidadCanon::sanitizarIdentidad($cat['identidad'] ?? null) : null,
            'romance_catalogo' => is_array($cat) ? ($cat['romance'] ?? null) : null,
            'vida_catalogo' => is_array($cat) ? ($cat['vida'] ?? null) : null,
            'perfil_partida_generado' => $perfil,
            'perfil_partida_legible' => self::perfilLegible($perfil, $store),
            'estado_emocional' => $res['runtime']['estado_emocional'] ?? null,
            'expresion_visual' => $res['runtime']['expresion_visual'] ?? null,
            'runtime_social' => [
                'ultimo_contacto_social_dia' => $res['runtime']['ultimo_contacto_social_dia'] ?? null,
            ],
            'descubrimientos' => DiscoveryEngine::listarPorResidente($partida, $id),
            'pack_visual' => is_array($cat) ? ($cat['visual']['pack_id'] ?? null) : null,
        ];
    }

    /**
     * @param array<string, mixed> $perfil
     * @return array<string, mixed>
     */
    private static function perfilLegible(array $perfil, CatalogStore $store): array
    {
        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];
        return [
            'hobby_principal' => $perfil['hobby_principal'] ?? null,
            'hobbies_secundarios' => self::resolverLista($perfil['hobbies_secundarios'] ?? ($perfil['hobbies'] ?? []), 'hobbies', $store),
            'rasgos_publicos' => self::resolverLista($perfil['rasgos'] ?? [], 'rasgos', $store),
            'rasgos_ocultos' => self::resolverLista($perfil['rasgos_ocultos'] ?? [], 'rasgos', $store),
            'indicadores_visuales' => self::resolverLista($perfil['indicadores_visuales'] ?? [], 'indicadores_visuales', $store),
            'estilo_social' => self::resolverId((string) ($perfil['estilo_social'] ?? ''), 'estilos_sociales', $store),
            'lugares_preferentes' => $perfil['lugares_preferentes'] ?? [],
            'preferencias' => [
                'personalidad_pos' => self::resolverLista($prefs['personalidad_pos'] ?? [], 'rasgos', $store),
                'personalidad_neg' => self::resolverLista($prefs['personalidad_neg'] ?? [], 'rasgos', $store),
                'visual_pos' => self::resolverLista($prefs['visual_pos'] ?? [], 'indicadores_visuales', $store),
                'visual_neg' => self::resolverLista($prefs['visual_neg'] ?? [], 'indicadores_visuales', $store),
                'hobbies_pos' => self::resolverLista($prefs['hobbies_pos'] ?? [], 'hobbies', $store),
                'hobbies_neg' => self::resolverLista($prefs['hobbies_neg'] ?? [], 'hobbies', $store),
                'romanticas' => self::resolverLista($prefs['romanticas'] ?? [], 'prefs_romanticas', $store),
                'dealbreakers' => self::resolverLista($prefs['dealbreakers'] ?? [], 'dealbreakers', $store),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>>
     */
    public static function matrizRelacionalPublica(array $partida, Catalog $catalog): array
    {
        return self::matrizRelacional($partida, $catalog);
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>>
     */
    private static function matrizRelacional(array $partida, Catalog $catalog): array
    {
        $ids = self::residentesActivos($partida);
        $out = [];
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = $ids[$i];
                $b = $ids[$j];
                $out[] = [
                    'par' => [$a, $b],
                    'direcciones' => [
                        'a_hacia_b' => self::parDireccional($partida, $a, $b, $catalog),
                        'b_hacia_a' => self::parDireccional($partida, $b, $a, $catalog),
                    ],
                ];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function parDireccional(array &$partida, string $desde, string $hacia, Catalog $catalog): array
    {
        CompatibilidadOculta::asegurarDireccional($partida, $desde, $hacia, $catalog);
        $cal = CalibracionConfig::load($catalog->getRoot());
        $entre = RelacionEngine::obtenerEntre($partida, $desde, $hacia);
        $romRow = is_array($entre['romance'] ?? null) ? $entre['romance'] : null;
        $social = RelacionEngine::socialHacia($partida, $desde, $hacia);
        $rom = RelacionEngine::romanceHacia($partida, $desde, $hacia);
        $quim = QuimicaEngine::valorHacia($partida, $desde, $hacia);
        $quimRow = QuimicaEngine::obtener($partida, $desde, $hacia);
        $compat = CompatibilidadOculta::hacia($partida, $desde, $hacia);
        $parentesco = ParentescoVeto::bloqueaRomance($partida, $desde, $hacia, $cal)
            ? ParentescoVeto::motivo($partida, $desde, $hacia, $cal)
            : null;

        return [
            'desde' => $desde,
            'desde_nombre' => IdentidadPublica::nombre($partida, $desde),
            'hacia' => $hacia,
            'hacia_nombre' => IdentidadPublica::nombre($partida, $hacia),
            'se_conocen' => RelacionEngine::seConocen($partida, $desde, $hacia),
            'social_valor' => RelacionEngine::valorSocialHacia($partida, $desde, $hacia),
            'social_fila' => $social,
            'romance_valor' => $rom,
            'quimica_valor' => $quim,
            'quimica_par' => $quimRow,
            'compatibilidad_oculta' => $compat,
            'parentesco_veto' => $parentesco,
            'pareja_estado' => $romRow['estado_pareja'] ?? null,
            'estabilidad_pareja' => $romRow['estabilidad_pareja'] ?? null,
            'historial_parejas' => $romRow['historial_parejas'] ?? null,
            'relacion_entre' => $entre,
            'nota_orientacion' => 'RomanceElegibilidad V1: sin filtro género/orientación en motor.',
        ];
    }

    /**
     * @param list<string> $parts
     * @return array<string, mixed>
     */
    private static function relacionesParticipantes(array $partida, array $parts, Catalog $catalog): array
    {
        $out = [];
        if (count($parts) >= 2) {
            $a = (string) $parts[0];
            $b = (string) $parts[1];
            $out['a_hacia_b'] = self::parDireccional($partida, $a, $b, $catalog);
            $out['b_hacia_a'] = self::parDireccional($partida, $b, $a, $catalog);
        }
        return $out;
    }

    /**
     * @param list<string> $parts
     * @return array<string, mixed>
     */
    private static function emocionesParticipantes(array $partida, array $parts): array
    {
        $out = [];
        foreach ($parts as $pid) {
            if (!is_string($pid) || $pid === '') {
                continue;
            }
            $rt = $partida['residentes'][$pid]['runtime'] ?? [];
            $out[$pid] = is_array($rt) ? ($rt['estado_emocional'] ?? null) : null;
        }
        return $out;
    }

    /**
     * @param list<string> $parts
     * @return array<string, mixed>
     */
    private static function ultimoHistorialPar(array $partida, array $parts): array
    {
        if (count($parts) < 2) {
            return ['entrada' => null, 'antes' => null];
        }
        $a = (string) $parts[0];
        $b = (string) $parts[1];
        $hist = RelacionHistorial::listarEntre($partida, $a, $b);
        $ultima = $hist !== [] ? $hist[count($hist) - 1] : null;
        return [
            'entrada' => $ultima,
            'antes' => is_array($ultima) ? ($ultima['antes'] ?? null) : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $reacciones
     * @param array<string, mixed> $respuestaApi
     * @return list<array<string, mixed>>
     */
    private static function causasPlan(array $reacciones, array $respuestaApi): array
    {
        $causas = [];
        foreach ($reacciones as $reac) {
            if (($reac['decision'] ?? '') !== PropuestaEncuentro::DECISION_RECHAZA) {
                continue;
            }
            $causas[] = [
                'residente' => $reac['nombre'] ?? $reac['residente_id'] ?? '?',
                'clase' => $reac['clase'] ?? null,
                'motivo_tecnico' => $reac['motivo_tecnico'] ?? null,
                'rechazo_tipo' => $reac['rechazo_tipo'] ?? null,
                'rechazo_familia' => $reac['rechazo_familia'] ?? null,
                'score' => $reac['score'] ?? null,
                'p' => $reac['p'] ?? null,
                'factores' => $reac['factores'] ?? null,
            ];
        }
        if ($causas === [] && !empty($respuestaApi['error'])) {
            $causas[] = [
                'api_error' => $respuestaApi['error'],
                'causa' => $respuestaApi['causa'] ?? null,
            ];
        }
        return $causas;
    }

    /**
     * @param list<mixed> $ids
     * @return list<array{id: string, nombre: string}>
     */
    private static function resolverLista(array $ids, string $catalogo, CatalogStore $store): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            $out[] = ['id' => $id, 'nombre' => self::resolverId($id, $catalogo, $store)];
        }
        return $out;
    }

    private static function resolverId(string $id, string $catalogo, CatalogStore $store): string
    {
        if ($id === '') {
            return '';
        }
        $item = $store->item($catalogo, $id);
        if (is_array($item) && !empty($item['nombre'])) {
            return (string) $item['nombre'];
        }
        return $id;
    }

    public static function activaEnRequest(): bool
    {
        if (isset($_GET['debug']) && (string) $_GET['debug'] !== '' && (string) $_GET['debug'] !== '0') {
            return true;
        }
        if (isset($_GET['lab']) && (string) $_GET['lab'] !== '' && (string) $_GET['lab'] !== '0') {
            return true;
        }
        return false;
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function eventoTutorial(array $partida, string $evento, Catalog $catalog): void
    {
        $tut = is_array($partida['tutorial'] ?? null) ? $partida['tutorial'] : [];
        if (($tut['id'] ?? '') !== TutorialPrimerosPasos::ID) {
            return;
        }
        $ids = [];
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            if (is_string($id) && ($res['presencia'] ?? '') === 'residente') {
                $ids[] = $id;
            }
        }
        sort($ids);
        self::push('TUTORIAL', '[AHT DEBUG TUTORIAL]', [
            'evento' => $evento,
            'npcs_iniciales' => array_map(static function (string $id) use ($partida): array {
                return ['id' => $id, 'nombre' => IdentidadPublica::nombre($partida, $id)];
            }, $ids),
            'pareja_mision1' => $tut['pareja_mision1'] ?? null,
            'tercero' => $tut['tercero'] ?? null,
            'seleccion_pareja' => $tut['seleccion_pareja'] ?? null,
            'mensajito_id' => $tut['mensajito_id'] ?? null,
            'lugar_mision3' => $tut['lugar_mision3'] ?? null,
            'jugable_completado' => $tut['jugable_completado'] ?? false,
            'misiones' => MisionDiariaEngine::delDia($partida, (int) ($partida['reloj']['dia_pueblo'] ?? 1)),
        ]);
    }

    /**
     * @param array<string, mixed> $debugPayload
     */
    public static function eventoDebugParejas(string $accion, array $debugPayload): void
    {
        self::push('PAREJAS', '[AHT DEBUG PAREJAS]', array_merge(['accion' => $accion], $debugPayload));
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $respuestaApi
     */
    public static function eventoPlanSolo(array $partida, array $respuestaApi, Catalog $catalog): void
    {
        $prop = is_array($respuestaApi['propuesta'] ?? null) ? $respuestaApi['propuesta'] : [];
        if ((string) ($prop['tipo'] ?? '') !== 'individual') {
            return;
        }
        $parts = is_array($prop['participantes'] ?? null) ? $prop['participantes'] : [];
        self::push('PLAN_SOLO', '[AHT DEBUG PLAN SOLO]', [
            'npc' => $parts[0] ?? null,
            'lugar' => $prop['lugar'] ?? null,
            'dia_solicitado' => $prop['hora_solicitada']['dia'] ?? ($prop['dia'] ?? null),
            'hora_solicitada' => $prop['hora_solicitada']['hora'] ?? null,
            'dia_final' => $prop['dia'] ?? null,
            'hora_final' => $prop['hora'] ?? null,
            'hora_ajustada' => $prop['hora_ajustada'] ?? false,
            'estado' => $prop['estado'] ?? null,
            'programado' => !empty($respuestaApi['programado']),
            'rechazada' => !empty($respuestaApi['rechazada']),
            'resolucion_plan' => $prop['resolucion_plan'] ?? null,
            'reacciones' => $prop['reacciones'] ?? [],
            'encuentro_id' => $prop['encuentro_id'] ?? ($respuestaApi['encuentro']['id'] ?? null),
        ]);
    }

    /**
     * Exportación completa para copiar en DEBUG (caja negra de la partida).
     * Estado + config efectiva + NPCs completos + relaciones + mundo + timeline
     * persistida + resoluciones con valores REALES del motor.
     *
     * @param list<array<string, mixed>> $historialCliente
     * @return array{texto: string, json: array<string, mixed>}
     */
    public static function exportCompleto(array $partida, Catalog $catalog, array $historialCliente = []): array
    {
        $root = $catalog->getRoot();
        $npcs = [];
        foreach (self::residentesActivos($partida) as $id) {
            $npcs[] = self::perfilNpc($partida, $id, $catalog);
        }
        $matriz = self::matrizRelacional($partida, $catalog);
        $encuentros = EncuentroEngine::listarActivos($partida);
        $misiones = MisionDiariaEngine::delDia($partida, (int) ($partida['reloj']['dia_pueblo'] ?? 1));
        $tutorial = $partida['tutorial'] ?? null;
        $calEfectiva = CalibracionConfig::load($root);
        $mundo = self::estadoMundo($partida);
        $timeline = EventInspector::timeline($partida, ['limit' => 500]);
        $resoluciones = self::resolucionesRecientes($partida, $calEfectiva);

        $json = [
            'generado' => date('c'),
            'partida' => [
                'partida_id' => $partida['meta']['partida_id'] ?? null,
                'config_id' => $partida['meta']['config_id'] ?? null,
                'seed' => $partida['meta']['seed'] ?? null,
                'schema_version' => $partida['meta']['schema_version'] ?? null,
                'rng' => $partida['rng'] ?? null,
                'reloj' => $partida['reloj'] ?? null,
                'features' => $partida['features'] ?? [],
                'vida_pueblo_valor' => $partida['vida_pueblo']['valor'] ?? null,
            ],
            'config_efectiva' => [
                'features' => $partida['features'] ?? [],
                'calibracion' => $calEfectiva,
                'nota' => 'Pesos/umbrales reales usados por el motor (CalibracionConfig).',
            ],
            'npcs' => $npcs,
            'matriz_relacional' => $matriz,
            'mundo' => $mundo,
            'encuentros_activos' => $encuentros,
            'encuentros_todos' => self::encuentrosExport($partida),
            'misiones_hoy' => $misiones,
            'tutorial' => $tutorial,
            'timeline' => $timeline,
            'resoluciones' => $resoluciones,
            'historial_sesion' => $historialCliente,
            'nota_canon' => 'Romance V1: sin veto por género/orientación. Química, dealbreakers y parentesco sí aplican.',
            'nota_telemetria' => 'timeline/resoluciones provienen de datos persistidos del motor y telemetría DEV observacional; no alteran gameplay ni RNG.',
        ];

        $bloques = [];
        $bloques[] = '[AHT DEBUG PARTIDA]';
        $bloques[] = json_encode($json['partida'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $bloques[] = '';
        $bloques[] = '[AHT DEBUG CONFIG EFECTIVA]';
        $bloques[] = json_encode($json['config_efectiva'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $bloques[] = '';
        foreach ($npcs as $npc) {
            $bloques[] = '[AHT DEBUG NPC] ' . ($npc['nombre'] ?? $npc['id'] ?? '?');
            $bloques[] = json_encode($npc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $bloques[] = '';
        }
        foreach ($matriz as $par) {
            $bloques[] = '[AHT DEBUG REL] par ' . implode('↔', $par['par'] ?? []);
            $bloques[] = json_encode($par, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $bloques[] = '';
        }
        $bloques[] = '[AHT DEBUG MUNDO]';
        $bloques[] = json_encode($mundo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $bloques[] = '';
        $bloques[] = '[AHT DEBUG ENCUENTROS/PLANES]';
        $bloques[] = json_encode([
            'activos' => $encuentros,
            'todos' => self::encuentrosExport($partida),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $bloques[] = '';
        $bloques[] = '[AHT DEBUG TIMELINE] total=' . ($timeline['total'] ?? 0);
        $bloques[] = json_encode($timeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $bloques[] = '';
        if ($resoluciones !== []) {
            $bloques[] = '[AHT DEBUG RESOLUCIONES]';
            $bloques[] = json_encode($resoluciones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $bloques[] = '';
        }
        if ($misiones !== []) {
            $bloques[] = '[AHT DEBUG MISION]';
            $bloques[] = json_encode($misiones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $bloques[] = '';
        }
        if (is_array($tutorial) && $tutorial !== []) {
            $bloques[] = '[AHT DEBUG TUTORIAL]';
            $bloques[] = json_encode($tutorial, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $bloques[] = '';
        }
        if ($historialCliente !== []) {
            $bloques[] = '[AHT DEBUG HISTORIAL SESIÓN]';
            foreach ($historialCliente as $ev) {
                $pref = (string) ($ev['prefijo'] ?? '[AHT DEBUG]');
                $bloques[] = $pref . ' ' . json_encode($ev['datos'] ?? $ev, JSON_UNESCAPED_UNICODE);
            }
        }

        return [
            'texto' => implode("\n", $bloques),
            'json' => $json,
        ];
    }

    /**
     * Estado completo del mundo necesario para reconstruir qué ocurrió.
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function estadoMundo(array $partida): array
    {
        $presentes = [];
        $noActivos = [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid) || $rid === '' || !is_array($res)) {
                continue;
            }
            $row = [
                'id' => $rid,
                'nombre' => IdentidadPublica::nombre($partida, $rid),
                'vivienda_id' => $res['vivienda_id'] ?? null,
                'presencia' => $res['presencia'] ?? null,
            ];
            if (($res['presencia'] ?? 'residente') === 'residente') {
                $presentes[] = $row;
            } else {
                $noActivos[] = $row;
            }
        }

        return [
            'residentes_presentes' => $presentes,
            'residentes_no_activos' => $noActivos,
            'propuestas_encuentro' => $partida['propuestas_encuentro'] ?? [],
            'planes_autonomos_pendientes' => $partida['npc_autonomo']['planes_pendientes'] ?? [],
            'peticiones' => $partida['peticiones'] ?? [],
            'buzon_reciente' => array_slice(is_array($partida['buzon'] ?? null) ? $partida['buzon'] : [], -40),
            'diario_reciente' => array_slice(is_array($partida['diario'] ?? null) ? $partida['diario'] : [], -60),
            'memoria_eventos' => array_slice(is_array($partida['memoria_eventos'] ?? null) ? $partida['memoria_eventos'] : [], -80),
            'bitacora_relaciones' => array_slice(is_array($partida['bitacora_relaciones'] ?? null) ? $partida['bitacora_relaciones'] : [], -80),
            'relaciones_conflicto' => $partida['relaciones_conflicto'] ?? [],
            'cooldowns' => [
                'propuestas_cooldown' => $partida['propuestas_cooldown'] ?? [],
                'rechazos_propuesta' => $partida['rechazos_propuesta'] ?? [],
            ],
            'llegadas_en_camino' => $partida['llegadas']['en_camino'] ?? null,
            'celeste' => [
                'lugares_desbloqueados' => $partida['celeste']['lugares_desbloqueados'] ?? [],
                'bloques_abiertos' => $partida['celeste']['bloques_abiertos'] ?? null,
                'intervenciones_organizadas_usadas_hoy' => $partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0,
            ],
            'vida_pueblo' => [
                'valor' => $partida['vida_pueblo']['valor'] ?? null,
                'ledger_reciente' => array_slice(
                    is_array($partida['vida_pueblo']['ledger'] ?? null) ? $partida['vida_pueblo']['ledger'] : [],
                    -20
                ),
            ],
            'solapes_agenda' => self::solapesAgenda($partida),
            'nota_solapes' => 'Pares de encuentros que comparten participante con franja horaria solapada (dia*24+hora). Detecta interacciones paralelas incompatibles.',
        ];
    }

    /**
     * Encuentros que comparten participante con horarios solapados.
     * Lectura pura de las filas almacenadas; permite diagnosticar el caso
     * «X aparece con Y mientras seguía ocupado con Z» sin conjeturas.
     *
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>>
     */
    private static function solapesAgenda(array $partida): array
    {
        $encs = [];
        foreach (EncuentroEngine::list($partida) as $e) {
            if (!is_array($e)) {
                continue;
            }
            $est = (string) ($e['estado'] ?? '');
            if (!in_array($est, ['programado', 'en_curso', 'terminado'], true)) {
                continue;
            }
            $d0 = (int) ($e['dia'] ?? 0);
            $h0 = (int) ($e['hora'] ?? 0);
            $dh = max(1, (int) ($e['duracion_horas'] ?? 1));
            $encs[] = [
                'id' => $e['id'] ?? null,
                'estado' => $est,
                'tipo' => $e['tipo'] ?? null,
                'participantes' => is_array($e['participantes'] ?? null) ? $e['participantes'] : [],
                'inicio_abs' => $d0 * 24 + $h0,
                'fin_abs' => $d0 * 24 + $h0 + $dh,
                'franja' => ['dia' => $d0, 'hora' => $h0, 'duracion_horas' => $dh],
            ];
        }
        $out = [];
        $n = count($encs);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $encs[$i];
                $b = $encs[$j];
                $comun = array_values(array_intersect($a['participantes'], $b['participantes']));
                if ($comun === []) {
                    continue;
                }
                if ($a['inicio_abs'] < $b['fin_abs'] && $b['inicio_abs'] < $a['fin_abs']) {
                    unset($a['inicio_abs'], $a['fin_abs'], $b['inicio_abs'], $b['fin_abs']);
                    $out[] = ['participantes_compartidos' => $comun, 'encuentro_a' => $a, 'encuentro_b' => $b];
                }
            }
        }
        return $out;
    }

    /**
     * Todos los encuentros (programados/en curso/terminados/cancelados) recortando
     * duplicación estática (_cal repite calibración).
     *
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>>
     */
    private static function encuentrosExport(array $partida): array
    {
        $out = [];
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $row = $enc;
            if (is_array($row['resultado'] ?? null) && isset($row['resultado']['_cal'])) {
                unset($row['resultado']['_cal']);
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Resoluciones explicadas con los valores REALES almacenados por el motor:
     * factores snapshot, cargas, desglose determinista de contribuciones,
     * tirada DEV capturada (pesos/pick) cuando exista y efectos posteriores.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $calFallback
     * @return list<array<string, mixed>>
     */
    private static function resolucionesRecientes(array $partida, array $calFallback): array
    {
        $out = [];
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $res = is_array($enc['resultado'] ?? null) ? $enc['resultado'] : null;
            $interv = is_array($enc['intervencion_celeste'] ?? null) ? $enc['intervencion_celeste'] : null;
            if ($res === null && $interv === null) {
                continue;
            }
            $encId = (string) ($enc['id'] ?? '');
            $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            $bloque = [
                'encuentro_id' => $encId,
                'tipo' => $enc['tipo'] ?? null,
                'estado' => $enc['estado'] ?? null,
                'lugar' => $enc['lugar'] ?? null,
                'franja' => [
                    'dia' => $enc['dia'] ?? null,
                    'hora' => $enc['hora'] ?? null,
                    'duracion_horas' => $enc['duracion_horas'] ?? null,
                ],
                'participantes' => array_map(static function (string $pid) use ($partida): array {
                    return ['id' => $pid, 'nombre' => IdentidadPublica::nombre($partida, $pid)];
                }, array_map('strval', $parts)),
            ];

            if ($interv !== null) {
                $bloque['intervencion_celeste'] = $interv;
                $bloque['traza_dev_intervencion'] = self::trazaCtx('intervencion:' . $encId);
            }

            if ($res !== null) {
                $exp = is_array($res['experiencia'] ?? null) ? $res['experiencia'] : [];
                $cal = is_array($res['_cal'] ?? null) ? $res['_cal'] : $calFallback;
                $temaCargas = is_array(($interv['tema_cargas'] ?? null)) ? $interv['tema_cargas'] : [];
                $cargaInterv = isset($interv['carga']) && is_numeric($interv['carga']) ? (float) $interv['carga'] : null;
                $por = [];
                foreach (($exp['por_participante'] ?? []) as $pid => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $pidS = (string) $pid;
                    $desg = EncuentroExperiencia::desgloseCarga($exp, $pidS, $cal);
                    $cargaFinal = isset($row['carga']) && is_numeric($row['carga']) ? (float) $row['carga'] : null;
                    $por[$pidS] = [
                        'resultado_experiencia' => $row['resultado'] ?? null,
                        'carga_final' => $cargaFinal,
                        'aviso_racha' => $row['aviso_racha'] ?? null,
                        'compatibilidad_hacia_otro' => $row['compatibilidad_hacia_otro'] ?? null,
                        'contribuciones_por_factor' => $desg['contribuciones'],
                        'componentes_carga' => [
                            'base_factores_snapshot' => $cargaFinal !== null && $cargaInterv !== null
                                ? round($cargaFinal - $cargaInterv - (float) ($temaCargas[$pidS] ?? 0), 6)
                                : ($desg['carga'] ?? null),
                            'intervencion_celeste_carga' => $cargaInterv,
                            'tema_carga_individual' => isset($temaCargas[$pidS]) && is_numeric($temaCargas[$pidS])
                                ? (float) $temaCargas[$pidS]
                                : 0.0,
                            'nota' => 'base+intervencion+tema = carga_final almacenada; contribuciones descomponen el snapshot de factores almacenado.',
                        ],
                    ];
                }
                $bloque['resolucion_final'] = [
                    'factores_snapshot' => $exp['factores'] ?? null,
                    'por_participante' => $por,
                    'delta_social' => $res['delta_social'] ?? null,
                    'delta_romance' => $res['delta_romance'] ?? null,
                    'conflicto' => $res['conflicto'] ?? null,
                    'descubrimientos' => $res['descubrimientos'] ?? null,
                    'experiencia_narrativa' => $res['experiencia_narrativa'] ?? null,
                    'texto_resumen' => $res['texto_resumen'] ?? null,
                    '_placeholder_evaluator' => !empty($res['_placeholder']),
                    'dev_traza_persistida' => $res['_dev_traza'] ?? null,
                    'traza_dev_tiradas_finales' => self::trazaCtx('encuentro_final:' . $encId),
                ];
            }
            $out[] = $bloque;
        }
        usort($out, static function ($a, $b): int {
            $fa = $a['franja'] ?? [];
            $fb = $b['franja'] ?? [];
            return ((int) ($fa['dia'] ?? 0)) * 24 + ((int) ($fa['hora'] ?? 0))
                <=> ((int) ($fb['dia'] ?? 0)) * 24 + ((int) ($fb['hora'] ?? 0));
        });
        return $out;
    }
}
