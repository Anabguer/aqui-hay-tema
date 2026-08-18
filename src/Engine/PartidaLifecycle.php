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

        FeatureConfig::mergeIntoPartida($partida, $this->root);
        PersistenciaCaps::mergeIntoPartida($partida, $this->root);
        SchemaFields::ensure($partida);
        DomainBootstrap::boot();

        DomainEventDispatcher::emit($partida, DomainEvents::PARTIDA_CREADA, [
            'config_id' => $configId,
            'partida_id' => $partida['meta']['partida_id'],
            'actores' => array_keys($partida['residentes']),
        ], $this->logger, 'PartidaLifecycle::nueva');

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
        SchemaFields::ensure($partida);
        $this->logger->log($partida, 'partida_reiniciada', ['partida_id' => $partidaId]);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function listar(): array
    {
        return $this->repo->listar();
    }
}
