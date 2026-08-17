<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaService
{
    private string $root;
    private Catalog $catalog;
    private PartidaRepository $repo;
    private GameLogger $logger;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $this->catalog = new Catalog($this->root);
        $this->repo = new PartidaRepository($this->root);
        $this->logger = new GameLogger($this->root);
    }

    public function nuevaPartida(string $configId = 'debug_v0', ?string $seed = null): array
    {
        $partida = PartidaSchema::nueva($this->root, $configId, $seed);
        $config = $this->catalog->loadConfigPrevalidada($configId);

        foreach ($config['residentes_iniciales'] ?? [] as $entry) {
            $this->incorporarResidenteCatalogo($partida, $entry['catalog_id'], $entry['presencia'] ?? 'residente');
        }

        FeatureConfig::mergeIntoPartida($partida, $this->root);
        DomainBootstrap::boot();
        $this->logger->log($partida, 'partida_nueva', ['config_id' => $configId]);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function cargar(string $partidaId): array
    {
        $partida = $this->repo->cargar($partidaId);
        Reloj::calcularCatchUpPendiente($partida);
        EncuentroLifecycle::sincronizarConReloj($partida, $this->logger);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function guardar(array $partida): void
    {
        RngService::fromPartida($partida)->persistToPartida($partida);
        $this->repo->guardar($partida);
    }

    /**
     * NUEVA PARTIDA: nuevo partida_id y archivo.
     * REINICIAR PARTIDA: conserva partida_id, resetea estado (ver docs PLAN_MAESTRO).
     */
    public function reiniciarPartida(string $partidaId, string $configId = 'debug_v0', ?string $seed = null): array
    {
        $partida = PartidaSchema::nueva($this->root, $configId, $seed);
        $partida['meta']['partida_id'] = $partidaId;
        $config = $this->catalog->loadConfigPrevalidada($configId);
        foreach ($config['residentes_iniciales'] ?? [] as $entry) {
            $this->incorporarResidenteCatalogo($partida, $entry['catalog_id'], $entry['presencia'] ?? 'residente');
        }
        $this->logger->log($partida, 'partida_reiniciada', ['partida_id' => $partidaId]);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function listarPartidas(): array
    {
        return $this->repo->listar();
    }

    public function incorporarResidenteCatalogo(array &$partida, string $catalogId, string $presencia = 'residente'): array
    {
        if (isset($partida['residentes'][$catalogId])) {
            return ['ok' => false, 'error' => 'ya_presente'];
        }
        $catalogo = $this->catalog->loadPersonaje($catalogId);
        $runtime = ResidenteRuntime::crearDesdeCatalogo($catalogo, $presencia);
        $partida['residentes'][$catalogId] = $runtime;

        $asig = BloqueA::asignarAutomatico($partida, $catalogId);
        if ($asig['error'] !== null) {
            return ['ok' => false, 'error' => $asig['error'], 'residente' => $runtime];
        }
        return ['ok' => true, 'residente' => $runtime, 'vivienda_id' => $asig['vivienda_id']];
    }

    public function crearResidentePlaceholderDev(array &$partida): array
    {
        $num = 1;
        while (isset($partida['residentes']['per_placeholder_dev_' . str_pad((string) $num, 2, '0', STR_PAD_LEFT)])) {
            $num++;
        }
        $runtime = ResidenteRuntime::crearPlaceholderDev($num);
        $id = $runtime['catalog_id'];
        $partida['residentes'][$id] = $runtime;
        $asig = BloqueA::asignarAutomatico($partida, $id);
        return ['ok' => true, 'residente' => $runtime, 'vivienda_id' => $asig['vivienda_id']];
    }

    public function liberarVivienda(array &$partida, string $viviendaId): array
    {
        return ['ok' => BloqueA::liberar($partida, $viviendaId)];
    }

    public function avanzarReloj(array &$partida, int $horas): array
    {
        Reloj::avanzarHoras($partida, $horas);
        $sync = EncuentroLifecycle::sincronizarConReloj($partida, $this->logger);
        return [
            'reloj' => $partida['reloj'],
            'texto' => Reloj::formatear($partida['reloj']),
            'encuentros_resueltos' => $sync['resueltos'],
        ];
    }

    public function programarEncuentro(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugar = null
    ): array {
        return EncuentroEngine::programar(
            $partida,
            $participantes,
            $dia,
            $hora,
            $tipo,
            $lugar,
            null,
            $this->logger
        );
    }

    public function fichaResidente(array $partida, string $residenteId): array
    {
        $runtime = $partida['residentes'][$residenteId] ?? null;
        if ($runtime === null) {
            throw new \InvalidArgumentException('residente no encontrado');
        }

        $catalogo = ResidenteRuntime::catalogoParaRuntime($runtime, $this->catalog);
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
