<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaLifecycle
{
    private string $root;
    private Catalog $catalog;
    private PartidaRepository $repo;
    private GameLogger $logger;
    private ResidenteOperations $residentes;

    public function __construct(
        string $root,
        Catalog $catalog,
        PartidaRepository $repo,
        GameLogger $logger,
        ResidenteOperations $residentes
    ) {
        $this->root = $root;
        $this->catalog = $catalog;
        $this->repo = $repo;
        $this->logger = $logger;
        $this->residentes = $residentes;
    }

    public function nueva(string $configId = 'debug_v0', ?string $seed = null, ?array $horaLocalCliente = null): array
    {
        $partida = PartidaSchema::nueva($this->root, $configId, $seed, $horaLocalCliente);
        $config = $this->catalog->loadConfigPrevalidada($configId);

        foreach ($config['residentes_iniciales'] ?? [] as $entry) {
            $this->residentes->incorporarCatalogo($partida, $entry['catalog_id'], $entry['presencia'] ?? 'residente');
        }
        PoblacionV3::incorporarIniciales($partida, $config, $this->root, $this->residentes);
        self::aplicarParentescoConfig($partida, $config);

        $arrancaTutorialPrimeros = TutorialPrimerosPasos::debeArrancar($config);
        if (!$arrancaTutorialPrimeros) {
            HistoriaPuebloEngine::registrarEmpezoCotarroSiToca($partida);
        }

        FeatureConfig::mergeIntoPartida($partida, $this->root);
        if (!empty($config['features']) && is_array($config['features'])) {
            $partida['features'] = array_merge(
                is_array($partida['features'] ?? null) ? $partida['features'] : [],
                $config['features']
            );
        }
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        SchemaFields::ensure($partida);
        DomainBootstrap::boot();

        DomainEventDispatcher::emit($partida, DomainEvents::PARTIDA_CREADA, [
            'config_id' => $configId,
            'partida_id' => $partida['meta']['partida_id'],
            'actores' => array_keys($partida['residentes']),
        ], $this->logger, 'PartidaLifecycle::nueva');

        if (TutorialPrimerosPasos::debeArrancar($config)) {
            TutorialPrimerosPasos::arrancar($partida, $config, $this->catalog);
        } elseif (TutorialBucle::debeArrancar($config)) {
            TutorialBucle::arrancar($partida, $config);
        }
        TutorialIncorporaciones::ensureDesdeConfig($partida, $config);
        if (PlaytestGuia::activa($partida)) {
            PlaytestGuia::ensure($partida);
        }

        $partida = SchemaMigrator::migrate($partida);

        if (MisionDiariaEngine::activa($partida)) {
            $this->generarMisionesSiToca($partida);
        }
        $this->tickPeticiones($partida);

        $this->logger->log($partida, 'partida_nueva', ['config_id' => $configId]);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function cargar(string $partidaId): array
    {
        $partida = $this->repo->cargar($partidaId);
        $fingerprintAntes = self::fingerprintEstadoPersistible($partida);

        SchemaFields::ensure($partida);
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        $cal = CalibracionConfig::load($this->root);
        if (CatchUpEngine::activo($partida)) {
            CatchUpEngine::ejecutarAlCargar($partida, $this->root, $cal, $this->logger, $this->catalog);
        } else {
            Reloj::calcularCatchUpPendiente($partida);
        }
        CatchUpEngine::marcarSesion($partida);
        EncuentroLifecycle::sincronizarConReloj($partida, $this->logger, $this->catalog);
        $this->generarMisionesSiToca($partida);
        $this->tickPeticiones($partida);

        // Monotonicity guard: reconcile stale celebracion_estado with consumed file
        HistoriaPuebloEngine::reconcileConsumedState($partida, $this->root, $partidaId);
        HistoriaPuebloEngine::registrarEmpezoCotarroSiToca($partida);

        if (self::fingerprintEstadoPersistible($partida) !== $fingerprintAntes) {
            $this->guardar($partida);
        }
        return $partida;
    }

    /**
     * Bootstrap UI (partida.refresh): sync de gameplay visible, sin catch-up de sesión ni logs instrumentales.
     */
    public function cargarParaRefresh(string $partidaId): array
    {
        $partida = $this->repo->cargar($partidaId);
        $fingerprintAntes = self::fingerprintEstadoPersistible($partida);

        SchemaFields::ensure($partida);
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        EncuentroLifecycle::sincronizarConReloj($partida, $this->logger, $this->catalog);
        $this->generarMisionesSiToca($partida);
        $this->tickPeticiones($partida);

        // Monotonicity guard: reconcile stale celebracion_estado with consumed file
        HistoriaPuebloEngine::reconcileConsumedState($partida, $this->root, $partidaId);
        HistoriaPuebloEngine::registrarEmpezoCotarroSiToca($partida);

        if (self::fingerprintEstadoPersistible($partida) !== $fingerprintAntes) {
            $this->guardar($partida);
        }
        return $partida;
    }

    /**
     * Carga sin reconciliar reloj/encuentros/misiones/peticiones ni escribir el save.
     * Para mutaciones acotadas (marcar mensajito leído, etc.).
     */
    public function cargarLigero(string $partidaId): array
    {
        $partida = $this->repo->cargar($partidaId);
        SchemaFields::ensure($partida);
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        HistoriaPuebloEngine::reconcileConsumedState($partida, $this->root, $partidaId);
        return $partida;
    }

    /**
     * Persistencia sin recorte de caps (evita barrer listas enormes en cada micro-cambio).
     */
    public function guardarRapido(array $partida): void
    {
        $this->fusionarCelebracionesDesdeDisco($partida);
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        RngService::fromPartida($partida)->persistToPartida($partida);
        $this->repo->guardarRapido($partida);
    }

    /**
     * Huella del estado persistible: ignora marca de sesión, updated_at y buffers de auditoría
     * (no son mutación de gameplay inmediata).
     *
     * @param array<string, mixed> $partida
     */
    private static function fingerprintEstadoPersistible(array $partida): string
    {
        $reloj = is_array($partida['reloj'] ?? null) ? $partida['reloj'] : [];
        unset($reloj['ultima_sesion_iso'], $reloj['catch_up_pendiente']);
        // ultimo_catch_up_iso sí es estado de gameplay (idempotencia offline)
        $slice = [
            'encuentros' => $partida['encuentros'] ?? [],
            'buzon' => $partida['buzon'] ?? [],
            'misiones_diarias' => $partida['misiones_diarias'] ?? null,
            'peticiones' => $partida['peticiones'] ?? [],
            'residentes' => $partida['residentes'] ?? [],
            'relaciones_sociales' => $partida['relaciones_sociales'] ?? [],
            'relaciones_romanticas' => $partida['relaciones_romanticas'] ?? [],
            'relaciones_conflicto' => $partida['relaciones_conflicto'] ?? [],
            'propuestas_encuentro' => $partida['propuestas_encuentro'] ?? [],
            'tutorial' => $partida['tutorial'] ?? null,
            'reloj' => $reloj,
            'rng' => $partida['rng'] ?? null,
            'vida_pueblo' => $partida['vida_pueblo'] ?? null,
            'npc_autonomo' => $partida['npc_autonomo'] ?? null,
            'propuestas_cooldown' => $partida['propuestas_cooldown'] ?? null,
            'rechazos_propuesta' => $partida['rechazos_propuesta'] ?? [],
            'historial_coincidencias' => $partida['historial_coincidencias'] ?? [],
            'descubrimientos' => $partida['descubrimientos'] ?? [],
            'historia_pueblo' => $partida['historia_pueblo'] ?? [],
            'celebraciones_consumidas' => $partida['celebraciones_consumidas'] ?? [],
        ];
        $json = json_encode($slice, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return hash('sha256', $json);
    }

    public function guardar(array $partida): void
    {
        $this->fusionarCelebracionesDesdeDisco($partida);
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        PersistenciaCaps::aplicar($partida);
        RngService::fromPartida($partida)->persistToPartida($partida);
        $this->repo->guardar($partida);
    }

    public function reiniciar(string $partidaId, string $configId = 'debug_v0', ?string $seed = null): array
    {
        $partida = PartidaSchema::nueva($this->root, $configId, $seed);
        $partida['meta']['partida_id'] = $partidaId;
        $config = $this->catalog->loadConfigPrevalidada($configId);

        foreach ($config['residentes_iniciales'] ?? [] as $entry) {
            $this->residentes->incorporarCatalogo($partida, $entry['catalog_id'], $entry['presencia'] ?? 'residente');
        }
        PoblacionV3::incorporarIniciales($partida, $config, $this->root, $this->residentes);
        self::aplicarParentescoConfig($partida, $config);

        $arrancaTutorialPrimeros = TutorialPrimerosPasos::debeArrancar($config);
        if (!$arrancaTutorialPrimeros) {
            HistoriaPuebloEngine::registrarEmpezoCotarroSiToca($partida);
        }

        FeatureConfig::mergeIntoPartida($partida, $this->root);
        if (!empty($config['features']) && is_array($config['features'])) {
            $partida['features'] = array_merge(
                is_array($partida['features'] ?? null) ? $partida['features'] : [],
                $config['features']
            );
        }
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        SchemaFields::ensure($partida);

        if (TutorialPrimerosPasos::debeArrancar($config)) {
            TutorialPrimerosPasos::arrancar($partida, $config, $this->catalog);
        } elseif (TutorialBucle::debeArrancar($config)) {
            TutorialBucle::arrancar($partida, $config);
        }
        TutorialIncorporaciones::ensureDesdeConfig($partida, $config);

        $partida = SchemaMigrator::migrate($partida);

        if (MisionDiariaEngine::activa($partida)) {
            $this->generarMisionesSiToca($partida);
        }
        $this->tickPeticiones($partida);

        $this->logger->log($partida, 'partida_reiniciada', ['partida_id' => $partidaId]);
        HistoriaPuebloEngine::clearConsumedFile($this->root, $partidaId);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function listar(): array
    {
        return $this->repo->listar();
    }

    /**
     * @param array<string, mixed> $partida
     */
    private function fusionarCelebracionesDesdeDisco(array &$partida): void
    {
        $partidaId = (string) ($partida['meta']['partida_id'] ?? '');
        if ($partidaId === '' || !$this->repo->existe($partidaId) || $this->repo->persistenceBackend() === 'sql') {
            return;
        }
        try {
            $data = JsonFile::read($this->repo->pathFor($partidaId));
            HistoriaPuebloEngine::preservarCelebracionesConsumidas($partida, $data['historia_pueblo'] ?? []);
            HistoriaPuebloEngine::ensure($partida);
            foreach ($data['celebraciones_consumidas'] ?? [] as $hitoId) {
                if (!is_string($hitoId) || $hitoId === '') {
                    continue;
                }
                if (!in_array($hitoId, $partida['celebraciones_consumidas'], true)) {
                    $partida['celebraciones_consumidas'][] = $hitoId;
                }
            }
        } catch (\Throwable $ignored) {
        }
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $config
     */
    private static function aplicarParentescoConfig(array &$partida, array $config): void
    {
        if (empty($config['parentesco']) || !is_array($config['parentesco'])) {
            return;
        }
        $partida['parentesco'] = array_values($config['parentesco']);
    }

    private function tickPeticiones(array &$partida): void
    {
        if (!PeticionPuebloEngine::activa($partida)) {
            PeticionEngine::caducarVencidas($partida, $this->logger);
            return;
        }
        $cal = CalibracionConfig::load($this->root);
        PeticionPuebloEngine::tick(
            $partida,
            $cal,
            RngService::fromPartida($partida),
            $this->logger,
            1
        );
    }

    private function generarMisionesSiToca(array &$partida): void
    {
        if (!MisionDiariaEngine::activa($partida)) {
            return;
        }
        if (TutorialPrimerosPasos::sembrarSiToca($partida, $this->root)) {
            return;
        }
        $cal = CalibracionConfig::load($this->root);
        MisionDiariaEngine::alComenzarDia(
            $partida,
            $cal,
            RngService::fromPartida($partida),
            $this->logger
        );
    }
}
