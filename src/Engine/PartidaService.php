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
        $this->reloj = new RelojOperations($this->logger);
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

    public function programarEncuentro(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugar = null
    ): array {
        return $this->encuentros->programar($partida, $participantes, $dia, $hora, $tipo, $lugar);
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

        $hobbiesConocidos = [];
        if ($catalogo !== null) {
            foreach (['vida.hobby_principal', 'vida.hobbies_secundarios'] as $campo) {
                if (DiscoveryEngine::estado($partida, $residenteId, $campo) === DiscoveryEngine::DESCONOCIDO) {
                    continue;
                }
            }
            if ($catalogo['vida']['hobby_principal'] ?? null) {
                $hobbiesConocidos[] = $catalogo['vida']['hobby_principal'];
            }
            foreach ($catalogo['vida']['hobbies_secundarios'] ?? [] as $h) {
                $hobbiesConocidos[] = $h;
            }
        }

        $ultimosEncuentros = array_values(array_filter(
            $partida['encuentros'] ?? [],
            static fn($e) => in_array($residenteId, $e['participantes'] ?? [], true)
                && ($e['estado'] ?? '') === 'terminado'
        ));
        usort($ultimosEncuentros, static fn($a, $b) => ((int) ($b['dia'] ?? 0) * 24 + (int) ($b['hora'] ?? 0))
            <=> ((int) ($a['dia'] ?? 0) * 24 + (int) ($a['hora'] ?? 0)));

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
            'relaciones' => $relaciones,
            'agenda_hoy' => $agenda,
            'ultimo_encuentro' => $ultimosEncuentros[0] ?? null,
            'placeholder' => $runtime['_placeholder'] ?? false,
        ];
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
