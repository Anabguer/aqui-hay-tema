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

    public function fichaResidente(array $partida, string $residenteId): array
    {
        $runtime = $partida['residentes'][$residenteId] ?? null;
        if ($runtime === null) {
            throw new \InvalidArgumentException('residente no encontrado');
        }

        $catalogo = null;
        try {
            $catalogo = ResidenteRuntime::catalogoParaRuntime($runtime, $this->catalog);
        } catch (ContentValidationException) {
            $catalogo = null;
        }

        $dia = (int) $partida['reloj']['dia_pueblo'];
        $agenda = AgendaEngine::resolverDia($partida, $residenteId, $dia, $this->catalog);

        $relaciones = [];
        foreach ($partida['residentes'] as $otroId => $_) {
            if ($otroId === $residenteId) {
                continue;
            }
            $rel = RelacionEngine::obtenerEntre($partida, $residenteId, $otroId);
            if ($rel['social'] !== null || $rel['romance'] !== null) {
                $relaciones[$otroId] = $rel;
            }
        }

        $proyeccion = [];
        $hobbiesConocidos = [];
        if ($catalogo !== null) {
            $visConfig = DiscoveryVisibilityPolicy::load($this->root);
            $proyeccion = DiscoveryProjection::proyectar(
                $partida,
                $residenteId,
                DiscoveryProjection::deCatalogo($catalogo, $runtime),
                $visConfig
            );
            $hp = DiscoveryProjection::valorSiVisible($proyeccion, 'vida.hobby_principal');
            if (is_string($hp) && $hp !== '' && $hp !== DiscoveryVisibilityResolver::PARCIAL_PLACEHOLDER) {
                $hobbiesConocidos[] = $hp;
            }
            $hs = DiscoveryProjection::valorSiVisible($proyeccion, 'vida.hobbies_secundarios', []);
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

        return [
            '_ui' => 'provisional_v0',
            'id' => $residenteId,
            'identidad' => [
                'nombre' => $runtime['identidad_publica']['nombre'],
                'slot_catalogo' => $runtime['identidad_publica']['slot_catalogo'],
                'edad' => $catalogo['identidad']['edad'] ?? null,
            ],
            'vivienda_id' => $runtime['vivienda_id'],
            'presencia' => $runtime['presencia'],
            'trabajo' => ['ocupacion' => $runtime['runtime']['ocupacion'] ?? null],
            'hobbies' => ['conocidos' => $hobbiesConocidos],
            'descubrimientos' => DiscoveryEngine::listarPorResidente($partida, $residenteId),
            'discovery' => [
                'campos' => $proyeccion,
                '_nota' => 'Políticas configurables. Default sin_politica: no oculta. Ningún secreto asignado a fichas.',
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
        return [
            'meta' => $partida['meta'],
            'reloj' => $partida['reloj'],
            'reloj_texto' => Reloj::formatear($partida['reloj']),
            'celeste' => $partida['celeste'],
            'bloque_a' => BloqueA::resumen($partida),
            'residentes_count' => count($partida['residentes']),
            'encuentros_activos' => count(EncuentroEngine::listarActivos($partida)),
            'encuentros_hoy' => ResumenDia::encuentrosHoy($partida),
            'proximo_encuentro' => ResumenDia::proximoEncuentro($partida, $this->catalog),
            'encuentro_en_curso' => ResumenDia::encuentroEnCurso($partida, $this->catalog),
            'relaciones_sociales' => count($partida['relaciones_sociales']),
            'relaciones_romanticas' => count($partida['relaciones_romanticas']),
            'buzon_pendientes' => count(BuzonEngine::listar($partida, 'pendiente')),
        ];
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
