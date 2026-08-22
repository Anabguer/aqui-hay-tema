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

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $this->catalog = new Catalog($this->root);
        $this->repo = new PartidaRepository($this->root);
        $this->logger = new GameLogger($this->root);
        $this->residentes = new ResidenteOperations($this->catalog, $this->logger);
        $this->lifecycle = new PartidaLifecycle($this->root, $this->catalog, $this->repo, $this->logger, $this->residentes);
        $this->encuentros = new EncuentroOperations($this->logger);
        $this->reloj = new RelojOperations($this->root, $this->logger, $this->emociones());
    }

    public function nuevaPartida(string $configId = 'debug_v0', ?string $seed = null): array
    {
        return $this->lifecycle->nueva($configId, $seed);
    }

    public function cargar(string $partidaId): array
    {
        return $this->lifecycle->cargar($partidaId);
    }

    public function guardar(array $partida): void
    {
        $this->lifecycle->guardar($partida);
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
        ?string $lugar = null
    ): array {
        $r = $this->encuentros->proponer($partida, $participantes, $dia, $hora, $tipo, $lugar);
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

    public function fichaResidente(array $partida, string $residenteId): array
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

        $dia = (int) $partida['reloj']['dia_pueblo'];
        $agenda = AgendaEngine::resolverDia($partida, $residenteId, $dia, $this->catalog);

        $relaciones = [];
        RelacionGrafo::asegurarTodos($partida);
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
                'etiqueta_vinculo' => $vista['etiqueta_vinculo'],
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

        $ultimosEncuentros = array_values(array_filter(
            $partida['encuentros'] ?? [],
            static fn($e) => in_array($residenteId, $e['participantes'] ?? [], true)
                && ($e['estado'] ?? '') === 'terminado'
        ));
        usort($ultimosEncuentros, static fn($a, $b) => ((int) ($b['dia'] ?? 0) * 24 + (int) ($b['hora'] ?? 0))
            <=> ((int) ($a['dia'] ?? 0) * 24 + (int) ($a['hora'] ?? 0)));
        $ultimo = $ultimosEncuentros[0] ?? null;

        $out = [
            '_ui' => 'provisional_v0',
            'id' => $residenteId,
            'identidad' => [
                'nombre' => $runtime['identidad_publica']['nombre'],
                'slot_catalogo' => $runtime['identidad_publica']['slot_catalogo'],
                'edad' => $catalogo['identidad']['edad'] ?? null,
                'genero' => $catalogo['identidad']['genero'] ?? null,
            ],
            'vivienda_id' => $runtime['vivienda_id'],
            'presencia' => $runtime['presencia'],
            'trabajo' => ['ocupacion' => $runtime['runtime']['ocupacion'] ?? null],
            'hobbies' => ['conocidos' => $hobbiesConocidos],
            'descubrimientos' => DiscoveryEngine::listarPorResidente($partida, $residenteId),
            'discovery' => [
                'campos' => $proyeccion,
                '_nota' => 'Reveal inicial: 1 hobby + 1 rasgo. Resto ???. Compatibilidad nunca en ficha.',
            ],
            'relaciones' => $relaciones,
            'agenda_hoy' => $agenda,
            'ultimo_encuentro' => $ultimo,
            'ultimo_encuentro_vista' => is_array($ultimo)
                ? EncuentroResultadoVista::de($partida, $ultimo, $this->catalog, $this->root)
                : null,
            'placeholder' => $runtime['_placeholder'] ?? false,
            'estado_emocional' => $runtime['runtime']['estado_emocional'] ?? null,
            'presentacion_visual' => $this->presentacionVisual($partida, $runtime),
        ];
        $out['perfil_partida'] = PerfilPartida::de($partida, $residenteId)
            ?? PerfilPartida::deOLegacy($partida, $residenteId, $this->catalog);
        $out['vista_play'] = FichaPlayVista::de($out, $this->catalog->store());
        unset($out['perfil_partida']);
        return $out;
    }

    public function presentacionVisual(array $partida, array $runtime): array
    {
        return $this->emociones()->resolverResidente($partida, $runtime);
    }

    public function emociones(): EmotionalStateService
    {
        return new EmotionalStateService(
            new VisualPackStore($this->root),
            $this->catalog->store(),
            $this->logger
        );
    }

    public function visualPacks(): VisualPackStore
    {
        return new VisualPackStore($this->root);
    }

    public function estadoResumido(array $partida): array
    {
        $cal = CalibracionConfig::load($this->root);
        CapacidadViviendas::ensure($partida);
        $activos = CapacidadViviendas::residentesActivos($partida);
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
            'encuentros_activos' => count(EncuentroEngine::listarActivos($partida)),
            'encuentros_activos_label' => self::labelEncuentrosActivos(count(EncuentroEngine::listarActivos($partida))),
            'encuentros_hoy' => ResumenDia::encuentrosHoy($partida),
            'proximo_encuentro' => ResumenDia::proximoEncuentro($partida, $this->catalog),
            'encuentro_en_curso' => ResumenDia::encuentroEnCurso($partida, $this->catalog),
            'relaciones_sociales' => count($partida['relaciones_sociales']),
            'relaciones_romanticas' => count($partida['relaciones_romanticas']),
            'buzon_pendientes' => count(BuzonEngine::listar($partida, 'pendiente')),
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

    public function getCatalog(): Catalog
    {
        return $this->catalog;
    }

    public function getRoot(): string
    {
        return $this->root;
    }
}
