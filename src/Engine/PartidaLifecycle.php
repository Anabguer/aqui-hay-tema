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

    public function nueva(string $configId = 'debug_v0', ?string $seed = null): array
    {
        $partida = PartidaSchema::nueva($this->root, $configId, $seed);
        $config = $this->catalog->loadConfigPrevalidada($configId);

        foreach ($config['residentes_iniciales'] ?? [] as $entry) {
            $this->residentes->incorporarCatalogo($partida, $entry['catalog_id'], $entry['presencia'] ?? 'residente');
        }
        PoblacionV3::incorporarIniciales($partida, $config, $this->root, $this->residentes);
        self::aplicarParentescoConfig($partida, $config);

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

        TutorialBucle::arrancar($partida, $config);
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
        SchemaFields::ensure($partida);
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        Reloj::calcularCatchUpPendiente($partida);
        EncuentroLifecycle::sincronizarConReloj($partida, $this->logger, $this->catalog);
        $this->generarMisionesSiToca($partida);
        $this->tickPeticiones($partida);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function guardar(array $partida): void
    {
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
        self::aplicarParentescoConfig($partida, $config);
        FeatureConfig::mergeIntoPartida($partida, $this->root);
        if (!empty($config['features']) && is_array($config['features'])) {
            $partida['features'] = array_merge(
                is_array($partida['features'] ?? null) ? $partida['features'] : [],
                $config['features']
            );
        }
        SchemaFields::ensure($partida);
        $this->generarMisionesSiToca($partida);
        $this->tickPeticiones($partida);
        TutorialBucle::arrancar($partida, $config);
        $this->logger->log($partida, 'partida_reiniciada', ['partida_id' => $partidaId]);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function listar(): array
    {
        return $this->repo->listar();
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
        $cal = CalibracionConfig::load($this->root);
        MisionDiariaEngine::alComenzarDia(
            $partida,
            $cal,
            RngService::fromPartida($partida),
            $this->logger
        );
    }
}
