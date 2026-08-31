<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaService
{
    private string $root;
    private Catalog $catalog;
    private PartidaRepository $repo;
    private GameLogger $logger;
    private PartidaLifecycle $lifecycle;
    private ResidenteOperations $residentes;
    private EncuentroOperations $encuentros;
    private RelojOperations $reloj;
    private ?VisualPackStore $visualPackStore = null;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $this->catalog = new Catalog($this->root);
        $this->repo = new PartidaRepository($this->root);
        $this->repo->setUserContext(IntocablesSession::currentUserId($this->root));
        $this->logger = new GameLogger($this->root);
        $this->residentes = new ResidenteOperations($this->catalog, $this->logger);
        $this->lifecycle = new PartidaLifecycle($this->root, $this->catalog, $this->repo, $this->logger, $this->residentes);
        $this->encuentros = new EncuentroOperations($this->logger);
        $this->reloj = new RelojOperations($this->root, $this->logger, $this->emociones());
    }

    public function nuevaPartida(string $configId = 'debug_v0', ?string $seed = null, ?array $horaLocalCliente = null): array
    {
        return $this->lifecycle->nueva($configId, $seed, $horaLocalCliente);
    }

    public function cargar(string $partidaId): array
    {
        return $this->lifecycle->cargar($partidaId);
    }

    /**
     * Carga para partida.refresh: reconcilia gameplay para la UI sin marcar sesión/catch-up.
     */
    public function cargarParaRefresh(string $partidaId): array
    {
        return $this->lifecycle->cargarParaRefresh($partidaId);
    }

    public function cargarLigero(string $partidaId): array
    {
        return $this->lifecycle->cargarLigero($partidaId);
    }

    public function guardar(array $partida): void
    {
        $this->lifecycle->guardar($partida);
    }

    public function guardarRapido(array $partida): void
    {
        $this->lifecycle->guardarRapido($partida);
    }

    public function reiniciarPartida(string $partidaId, string $configId = 'debug_v0', ?string $seed = null): array
    {
        return $this->lifecycle->reiniciar($partidaId, $configId, $seed);
    }

    public function listarPartidas(): array
    {
        return $this->lifecycle->listar();
    }

    public function incorporarResidenteCatalogo(array &$partida, string $catalogId, string $presencia = 'residente'): array
    {
        return $this->residentes->incorporarCatalogo($partida, $catalogId, $presencia);
    }

    public function crearResidentePlaceholderDev(array &$partida): array
    {
        return $this->residentes->crearPlaceholderDev($partida);
    }

    public function liberarVivienda(array &$partida, string $viviendaId): array
    {
        return $this->residentes->liberarVivienda($partida, $viviendaId);
    }

    public function avanzarReloj(array &$partida, int $horas): array
    {
        return $this->reloj->avanzar($partida, $horas);
    }

    public function avanzarRelojPasoAPaso(array &$partida, int $horas): array
    {
        return $this->reloj->avanzarPasoAPaso($partida, $horas);
    }

    public function irAlProximoEncuentro(array &$partida): array
    {
        return $this->reloj->irAlProximoEncuentro($partida);
    }

    public function cancelarEncuentro(array &$partida, string $encuentroId): array
    {
        return $this->encuentros->cancelar($partida, $encuentroId);
    }

    public function programarEncuentro(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugar = null
    ): array {
        $r = $this->encuentros->programar($partida, $participantes, $dia, $hora, $tipo, $lugar);
        if (($r['ok'] ?? false) && isset($r['encuentro']) && is_array($r['encuentro'])) {
            $r['vista'] = ResumenDia::vistaEncuentro($partida, $r['encuentro'], $this->catalog);
        }
        return $r;
    }

    public function proponerEncuentro(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugar = null,
        ?string $peticionId = null
    ): array {
        $r = $this->encuentros->proponer($partida, $participantes, $dia, $hora, $tipo, $lugar, $peticionId);
        TutorialBucle::flushIncorporacionesPendientes($partida, $this->root, $this->logger);
        if (($r['ok'] ?? false) && isset($r['encuentro']) && is_array($r['encuentro'])) {
            $r['vista'] = ResumenDia::vistaEncuentro($partida, $r['encuentro'], $this->catalog);
        }
        if (!empty($r['rechazada']) && is_array($r['rechazado_por'] ?? null)) {
            $rid = (string) ($r['rechazado_por']['residente_id'] ?? '');
            if ($rid !== '' && isset($partida['residentes'][$rid])) {
                $vis = $this->presentacionVisual($partida, $partida['residentes'][$rid]);
                $asset = $vis['asset'] ?? [];
                $r['rechazado_por']['retrato_url'] = (!empty($asset['existe']) && is_string($asset['url_relativa'] ?? null))
                    ? $asset['url_relativa']
                    : null;
            }
        }
        return $r;
    }

    public function decidirPropuestaEncuentro(array &$partida, string $propuestaId, string $residenteId, bool $acepta): array
    {
        $r = PropuestaEncuentroEngine::registrarDecision($partida, $propuestaId, $residenteId, $acepta, $this->logger);
        if (($r['ok'] ?? false) && isset($r['encuentro']) && is_array($r['encuentro'])) {
            $r['vista'] = ResumenDia::vistaEncuentro($partida, $r['encuentro'], $this->catalog);
        }
        return $r;
    }

    public function vistaRelacionesPueblo(array $partida): array
    {
        $cal = CalibracionConfig::load($this->root);

        $pares = [];
        foreach (['relaciones_sociales', 'relaciones_romanticas', 'relaciones_conflicto'] as $bag) {
            foreach ($partida[$bag] ?? [] as $rel) {
                if (!is_array($rel)) {
                    continue;
                }
                $a = (string) ($rel['persona_a'] ?? '');
                $b = (string) ($rel['persona_b'] ?? '');
                if ($a !== '' && $b !== '') {
                    $pares[$a . '|' . $b] = true;
                }
            }
        }

        $conflictos = [];
        foreach ($partida['relaciones_conflicto'] ?? [] as $c) {
            if (is_array($c)) {
                $conflictos[(string) ($c['id'] ?? '')] = $c;
            }
        }

        $filas = [];
        foreach (array_keys($pares) as $key) {
            [$a, $b] = explode('|', (string) $key);
            if (!isset($partida['residentes'][$a]) || !isset($partida['residentes'][$b])) {
                continue;
            }

            $ab = RelacionVistaJugador::de($partida, $a, $b, $cal);
            $ba = RelacionVistaJugador::de($partida, $b, $a, $cal);

            $conflicto = null;
            $confId = "conf_{$a}_{$b}";
            if (isset($conflictos[$confId])) {
                $conflicto = ['tipo' => $conflictos[$confId]['tipo'] ?? null];
            }

            $relevante = $ab['conocidos'] || $ba['conocidos']
                || (int) $ab['social_valor'] !== 0 || (int) $ba['social_valor'] !== 0
                || $ab['etiqueta_vinculo'] !== null || $ba['etiqueta_vinculo'] !== null
                || $ab['romance_visible'] || $ba['romance_visible']
                || $conflicto !== null;
            if (!$relevante) {
                continue;
            }

            $filas[] = [
                'persona_a' => ['id' => $a, 'nombre' => IdentidadPublica::nombre($partida, $a)],
                'persona_b' => ['id' => $b, 'nombre' => IdentidadPublica::nombre($partida, $b)],
                'a_hacia_b' => self::dirVistaRelacion($ab),
                'b_hacia_a' => self::dirVistaRelacion($ba),
                'conflicto' => $conflicto,
                '_relevancia' => max(
                    self::relevanciaDirRelacion($ab),
                    self::relevanciaDirRelacion($ba),
                    $conflicto !== null ? 850 : 0
                ),
            ];
        }

        usort($filas, static function (array $x, array $y): int {
            if ($y['_relevancia'] !== $x['_relevancia']) {
                return $y['_relevancia'] <=> $x['_relevancia'];
            }
            $nx = $x['persona_a']['nombre'] . "\u{0}" . $x['persona_b']['nombre'];
            $ny = $y['persona_a']['nombre'] . "\u{0}" . $y['persona_b']['nombre'];
            return strcasecmp($nx, $ny);
        });
        $filas = array_map(static function (array $f): array {
            unset($f['_relevancia']);
            return $f;
        }, $filas);

        return ['ok' => true, 'relaciones' => $filas];
    }

    /**
     * Proyeccion de una direccion A→B sin valores numericos internos.
     *
     * @param array<string, mixed> $v
     * @return array<string, mixed>
     */
    private static function dirVistaRelacion(array $v): array
    {
        return [
            'conocidos' => $v['conocidos'],
            'etiqueta_social' => $v['etiqueta_social'],
            'etiqueta_social_ui' => $v['etiqueta_social_ui'],
            'emoji_social' => $v['emoji_social'],
            'social_bar_pct' => $v['social_bar_pct'],
            'social_negativo' => $v['social_negativo'],
            'etiqueta_vinculo' => $v['etiqueta_vinculo'],
            'romance_visible' => $v['romance_visible'],
            'etiqueta_romance' => $v['etiqueta_romance'],
            'emoji_romance' => $v['emoji_romance'],
            'romance_banda' => $v['romance_banda'],
            'pista_romantica' => $v['pista_romantica'] ?? '',
            'senal_narrativa' => $v['senal_narrativa'] ?? 'ninguna',
        ];
    }

    /**
     * Puntuacion de relevancia narrativa para el orden de la vista global.
     *
     * @param array<string, mixed> $v
     */
    private static function relevanciaDirRelacion(array $v): int
    {
        switch ($v['etiqueta_vinculo']) {
            case 'pareja':
                return 1000;
            case 'crisis':
                return 950;
            case 'ex_pareja':
                return 700;
        }

        $s = 0;
        switch ($v['romance_banda']) {
            case 'flechazo':
                $s = 900;
                break;
            case 'enamorado':
                $s = 880;
                break;
            case 'pillado':
                $s = 860;
                break;
            case 'interes':
            case 'tilin':
                $s = 620;
                break;
        }

        $banda = (string) $v['etiqueta_social'];
        if ($v['social_negativo']) {
            $s = max($s, 500);
        } elseif (in_array($banda, ['amigo', 'buen_amigo', 'mejor_amigo'], true)) {
            $s = max($s, 200);
        } elseif ($banda === 'cae_bien') {
            $s = max($s, 150);
        } elseif ($banda === 'conocido') {
            $s = max($s, 50);
        }
        return $s;
    }

    public function fichaResidente(array $partida, string $residenteId, bool $respuestaLigera = false): array
    {
        $runtime = $partida['residentes'][$residenteId] ?? null;
        if ($runtime === null) {
            throw new \InvalidArgumentException('residente no encontrado');
        }

        $catalogo = null;
        try {
            $catalogo = ResidenteRuntime::catalogoParaRuntime($runtime, $this->catalog);
        } catch (ContentValidationException $e) {
            $catalogo = null;
        }

        $agenda = [];
        if (!$respuestaLigera) {
            $dia = (int) $partida['reloj']['dia_pueblo'];
            $agenda = AgendaEngine::resolverDia($partida, $residenteId, $dia, $this->catalog);
        }

        $relaciones = [];
        $calFicha = CalibracionConfig::load($this->root);
        foreach ($partida['residentes'] as $otroId => $_) {
            if ($otroId === $residenteId) {
                continue;
            }
            $vista = RelacionVistaJugador::de($partida, $residenteId, (string) $otroId, $calFicha);
            $relaciones[$otroId] = [
                'id' => $otroId,
                'nombre' => IdentidadPublica::nombre($partida, (string) $otroId),
                'conocidos' => $vista['conocidos'],
                'etiqueta_social' => $vista['etiqueta_social'],
                'etiqueta_social_ui' => $vista['etiqueta_social_ui'],
                'emoji_social' => $vista['emoji_social'],
                'social_valor' => $vista['social_valor'],
                'social_bar_pct' => $vista['social_bar_pct'],
                'social_negativo' => $vista['social_negativo'],
                'etiqueta_vinculo' => $vista['etiqueta_vinculo'],
                'romance_visible' => $vista['romance_visible'],
                'etiqueta_romance' => $vista['etiqueta_romance'],
                'emoji_romance' => $vista['emoji_romance'],
                'romance_banda' => $vista['romance_banda'],
                'pista_romantica' => $vista['pista_romantica'] ?? '',
                'senal_narrativa' => $vista['senal_narrativa'] ?? 'ninguna',
            ];
        }

        $proyeccion = [];
        $hobbiesConocidos = [];
        if ($catalogo !== null) {
            $visConfig = DiscoveryVisibilityPolicy::load($this->root);
            $campos = DiscoveryProjection::deCatalogo($catalogo, $runtime);
            $campos = DiscoveryProjection::conPerfilPartida($campos, $runtime);
            $campos = DiscoveryReveal::ocultarNoDescubierto($partida, $residenteId, $campos);
            $proyeccion = DiscoveryProjection::proyectar(
                $partida,
                $residenteId,
                $campos,
                $visConfig
            );
            $hp = $campos['vida.hobby_principal'] ?? null;
            if (is_string($hp) && $hp !== '') {
                $hobbiesConocidos[] = $hp;
            }
            $hs = $campos['vida.hobbies_secundarios'] ?? [];
            if (is_array($hs)) {
                foreach ($hs as $h) {
                    if (is_string($h) && $h !== '') {
                        $hobbiesConocidos[] = $h;
                    }
                }
            }
        }

        $ultimo = null;
        if (!$respuestaLigera) {
            $ultimosEncuentros = array_values(array_filter(
                $partida['encuentros'] ?? [],
                static fn($e) => is_array($e) && in_array($residenteId, $e['participantes'] ?? [], true)
                    && ($e['estado'] ?? '') === 'terminado'
            ));
            usort($ultimosEncuentros, static fn($a, $b) => ((int) ($b['dia'] ?? 0) * 24 + (int) ($b['hora'] ?? 0))
                <=> ((int) ($a['dia'] ?? 0) * 24 + (int) ($a['hora'] ?? 0)));
            $ultimo = $ultimosEncuentros[0] ?? null;
        }

        $presentacion = $this->presentacionVisual($partida, $runtime);
        TrabajoHorario::asegurarHorario($partida, $residenteId);
        $rt = $partida['residentes'][$residenteId]['runtime'] ?? [];
        $genero = $catalogo['identidad']['genero'] ?? null;
        $trabajoVista = TrabajoHorario::paraFicha($rt, is_string($genero) ? $genero : null, $this->catalog->store());
        $out = [
            '_ui' => 'provisional_v0',
            'id' => $residenteId,
            'identidad' => [
                'nombre' => IdentidadPublica::nombre($partida, $residenteId),
                'slot_catalogo' => $runtime['identidad_publica']['slot_catalogo'] ?? null,
                'edad' => PerfilPartida::edadResuelta($partida, $residenteId, $this->catalog)
                    ?? ($catalogo['identidad']['edad'] ?? null),
                'genero' => $catalogo['identidad']['genero'] ?? null,
            ],
            'vivienda_id' => $runtime['vivienda_id'],
            'presencia' => $runtime['presencia'],
            'trabajo' => [
                'ocupacion' => $rt['ocupacion'] ?? null,
                'dias' => $rt['trabajo_dias'] ?? null,
                'hora_inicio' => $rt['trabajo_hora_inicio'] ?? null,
                'hora_fin' => $rt['trabajo_hora_fin'] ?? null,
                'vista' => $trabajoVista,
            ],
            'hobbies' => ['conocidos' => $hobbiesConocidos],
            'descubrimientos' => DiscoveryEngine::listarPorResidente($partida, $residenteId),
            'discovery' => [
                'campos' => $proyeccion,
                '_nota' => 'Reveal inicial: 1 hobby + 1 rasgo. Resto ???. Compatibilidad nunca en ficha.',
            ],
            'relaciones' => $relaciones,
            'agenda_hoy' => $agenda,
            'ultimo_encuentro' => $ultimo,
            'ultimo_encuentro_vista' => (!$respuestaLigera && is_array($ultimo))
                ? EncuentroResultadoVista::de($partida, $ultimo, $this->catalog, $this->root)
                : null,
            'placeholder' => $runtime['_placeholder'] ?? false,
            'estado_emocional' => $runtime['runtime']['estado_emocional'] ?? null,
            'presentacion_visual' => $presentacion,
            'aprecio_celeste' => AprecioCelesteVista::vista((int) ($rt['aprecio_celeste'] ?? 0), $calFicha),
        ];
        $out['perfil_partida'] = PerfilPartida::de($partida, $residenteId)
            ?? PerfilPartida::deOLegacy($partida, $residenteId, $this->catalog);
        $vistaPlay = FichaPlayVista::de($out, $this->catalog->store());
        $estadoEmo = is_array($out['estado_emocional'] ?? null) ? $out['estado_emocional'] : [];
        $animoModal = EmocionalNarrativa::vistaModalAnimo(
            $partida,
            $residenteId,
            $estadoEmo,
            CalibracionConfig::load($this->root)
        );
        if ($animoModal !== null) {
            $vistaPlay['animo_explicacion'] = $animoModal;
        }
        $out['vista_play'] = $vistaPlay;
        unset($out['perfil_partida']);

        if ($respuestaLigera) {
            $asset = is_array($presentacion['asset'] ?? null) ? $presentacion['asset'] : null;
            return [
                'vista_play' => $out['vista_play'],
                'relaciones' => $relaciones,
                'presentacion_visual' => ['asset' => $asset],
                'identidad' => ['nombre' => $out['identidad']['nombre']],
            ];
        }

        return $out;
    }

    public function presentacionVisual(array $partida, array $runtime): array
    {
        return $this->emociones()->resolverResidente($partida, $runtime);
    }

    public function emociones(): EmotionalStateService
    {
        if ($this->visualPackStore === null) {
            $this->visualPackStore = new VisualPackStore($this->root);
        }
        return new EmotionalStateService(
            $this->visualPackStore,
            $this->catalog->store(),
            $this->logger
        );
    }

    public function visualPacks(): VisualPackStore
    {
        if ($this->visualPackStore === null) {
            $this->visualPackStore = new VisualPackStore($this->root);
        }
        return $this->visualPackStore;
    }

    public function estadoResumido(array $partida): array
    {
        $cal = CalibracionConfig::load($this->root);
        CapacidadViviendas::ensure($partida);
        $activos = CapacidadViviendas::residentesActivos($partida);
        $encuentrosActivos = EncuentroEngine::listarActivos($partida);
        $out = [
            'meta' => $partida['meta'],
            'reloj' => $partida['reloj'],
            'reloj_texto' => Reloj::formatear($partida['reloj']),
            'reloj_vista' => Reloj::vista($partida['reloj']),
            'celeste' => $partida['celeste'],
            'bloque_a' => BloqueA::resumen($partida),
            'residentes_count' => count($partida['residentes']),
            'pueblo_residentes_activos' => count($activos),
            'pueblo_capacidad_max' => CapacidadViviendas::CAP_PRODUCTO,
            'encuentros_activos' => count($encuentrosActivos),
            'encuentros_activos_label' => self::labelEncuentrosActivos(count($encuentrosActivos)),
            'encuentros_hoy' => ResumenDia::encuentrosHoy($partida),
            'proximo_encuentro' => ResumenDia::proximoEncuentro($partida, $this->catalog),
            'encuentro_en_curso' => ResumenDia::encuentroEnCurso($partida, $this->catalog),
            'encuentros_en_curso' => ResumenDia::encuentrosEnCurso($partida, $this->catalog),
            'relaciones_sociales' => count($partida['relaciones_sociales']),
            'relaciones_romanticas' => count($partida['relaciones_romanticas']),
            'buzon_pendientes' => BuzonEngine::contarNoLeidos($partida, null),
            'peticiones_abiertas' => count(PeticionEngine::listar($partida, 'abierta')),
            'propuestas_pendientes' => count(array_filter(
                $partida['propuestas_encuentro'] ?? [],
                static function ($p) {
                    return ($p['estado'] ?? '') === 'propuesta';
                }
            )),
            'features' => [
                VidaPuebloEngine::FLAG => FeatureConfig::isEnabled($partida, VidaPuebloEngine::FLAG),
                MisionDiariaEngine::FLAG => FeatureConfig::isEnabled($partida, MisionDiariaEngine::FLAG),
                PeticionPuebloEngine::FLAG => FeatureConfig::isEnabled($partida, PeticionPuebloEngine::FLAG),
                'debug_tools_enabled' => FeatureConfig::isEnabled($partida, 'debug_tools_enabled'),
            ],
            'planes_organizar' => PropuestaNivel::contratoOrganizar(),
            'tutorial' => self::vistaTutorial($partida),
            'taller' => [
                'disponible' => true,
                'activo_en_partida' => FeatureConfig::isEnabled($partida, 'debug_tools_enabled'),
            ],
        ];
        if (PlaytestGuia::activa($partida)) {
            PlaytestGuia::ensure($partida);
            $out['playtest_guia'] = PlaytestGuia::vista($partida, $this->root);
            $out['playtest_diag'] = PlaytestDiag::vista($partida);
        }
        if (FeatureConfig::isEnabled($partida, VidaPuebloEngine::FLAG)) {
            $vista = VidaPuebloEngine::vista($partida, $cal);
            unset($vista['latidos']);
            $out['vida_pueblo'] = $vista;
            if (FeatureConfig::isEnabled($partida, 'debug_tools_enabled')) {
                $vp = $partida['vida_pueblo'] ?? [];
                $out['vida_debug'] = [
                    'valor' => VidaPuebloEngine::valor($partida),
                    'positivos_desde_latido' => (int) ($vp['positivos_desde_latido'] ?? 0),
                    'umbral_positivos_latido' => (int) ($vp['umbral_positivos_latido'] ?? 25),
                    'latidos' => (int) ($vp['latidos'] ?? 0),
                    'primer_latido_dia' => $vp['primer_latido_dia'] ?? null,
                    'positivo_valido_latido' => 'ver ledger',
                    'ledger_tail' => array_slice(is_array($vp['ledger'] ?? null) ? $vp['ledger'] : [], -8),
                ];
            }
        }
        if (MisionDiariaEngine::activa($partida)) {
            $out['misiones_hoy'] = MisionDiariaEngine::vistaHoy($partida, $cal);
        }
        if (EventosPuebloEngine::activa($partida, $cal)) {
            $proxEvt = EventosPuebloEngine::vistaProximoEvento($partida, $this->catalog);
            if ($proxEvt !== null) {
                $out['proximo_evento_pueblo'] = $proxEvt;
            }
        }
        $out['buzon_no_leidos'] = BuzonEngine::contarNoLeidos($partida);
        if (PeticionPuebloEngine::activa($partida)) {
            $out['peticiones_pueblo'] = [
                'abiertas' => PeticionPuebloEngine::vistaAbiertas($partida),
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function vistaTutorial(array $partida): array
    {
        if (($partida['tutorial']['id'] ?? '') === TutorialPrimerosPasos::ID) {
            $v = TutorialPrimerosPasos::vistaPublica($partida);
            return $v !== [] ? $v : TutorialBucle::vista($partida);
        }
        return TutorialBucle::vista($partida);
    }

    private static function labelEncuentrosActivos(int $n): string
    {
        if ($n <= 0) {
            return 'Ningún encuentro en curso o citado';
        }
        if ($n === 1) {
            return '1 encuentro citado o en curso';
        }
        return $n . ' encuentros citados o en curso';
    }

    public function getLogger(): GameLogger
    {
        return $this->logger;
    }

    public function getRepository(): PartidaRepository
    {
        return $this->repo;
    }

    public function setUserContext(?int $userId): void
    {
        $this->repo->setUserContext($userId);
    }

    public function getCatalog(): Catalog
    {
        return $this->catalog;
    }

    public function getRoot(): string
    {
        return $this->root;
    }
}
