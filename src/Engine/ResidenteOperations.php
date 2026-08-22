<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class ResidenteOperations
{
    private Catalog $catalog;
    private ?GameLogger $logger;

    public function __construct(Catalog $catalog, ?GameLogger $logger = null)
    {
        $this->catalog = $catalog;
        $this->logger = $logger;
    }

    public function incorporarCatalogo(array &$partida, string $catalogId, string $presencia = 'residente'): array
    {
        if (isset($partida['residentes'][$catalogId])) {
            return ['ok' => false, 'error' => 'ya_presente'];
        }
        try {
            $catalogo = $this->catalog->loadPersonaje($catalogId);
        } catch (ContentValidationException $e) {
            return [
                'ok' => false,
                'error' => 'content_validation_failed',
                'errores' => $e->errores,
                'mensaje_ui' => 'El personaje del catálogo no es válido.',
            ];
        }

        $runtime = ResidenteRuntime::crearDesdeCatalogo($catalogo, $presencia);
        $partida['residentes'][$catalogId] = $runtime;

        $asig = BloqueA::asignarAutomatico($partida, $catalogId);
        if ($asig['error'] !== null) {
            return ['ok' => false, 'error' => $asig['error'], 'residente' => $runtime];
        }

        GeneradorResidente::aplicar($partida, $catalogId, $this->catalog, $this->logger);
        HistorialPersonajesPartida::marcar($partida, $catalogId);
        QuimicaEngine::alIncorporar($partida, $catalogId, $this->catalog, $this->logger);
        $cal = CalibracionConfig::load($this->catalog->getRoot());
        RelacionGrafo::asegurarTodos($partida, $cal);
        DiscoveryReveal::alIncorporar($partida, $catalogId, $cal);

        DomainEventDispatcher::emit($partida, DomainEvents::RESIDENTE_INCORPORADO, [
            'residente_id' => $catalogId,
            'vivienda_id' => $asig['vivienda_id'],
            'presencia' => $presencia,
            'actores' => [$catalogId],
        ], $this->logger, 'ResidenteOperations::incorporar');

        return ['ok' => true, 'residente' => $runtime, 'vivienda_id' => $asig['vivienda_id']];
    }

    public function crearPlaceholderDev(array &$partida): array
    {
        $num = 1;
        while (isset($partida['residentes']['per_placeholder_dev_' . str_pad((string) $num, 2, '0', STR_PAD_LEFT)])) {
            $num++;
        }
        $runtime = ResidenteRuntime::crearPlaceholderDev($num);
        $id = $runtime['catalog_id'];
        $partida['residentes'][$id] = $runtime;
        $asig = BloqueA::asignarAutomatico($partida, $id);
        if (($asig['error'] ?? null) !== null) {
            unset($partida['residentes'][$id]);
            return ['ok' => false, 'error' => $asig['error'], 'residente' => $runtime];
        }

        GeneradorResidente::aplicar($partida, $id, $this->catalog, $this->logger);
        QuimicaEngine::alIncorporar($partida, $id, $this->catalog, $this->logger);
        $calPh = CalibracionConfig::load($this->catalog->getRoot());
        RelacionGrafo::asegurarTodos($partida, $calPh);
        DiscoveryReveal::alIncorporar($partida, $id, $calPh);

        DomainEventDispatcher::emit($partida, DomainEvents::RESIDENTE_INCORPORADO, [
            'residente_id' => $id,
            'placeholder' => true,
            'actores' => [$id],
        ], $this->logger, 'ResidenteOperations::placeholder');

        return ['ok' => true, 'residente' => $runtime, 'vivienda_id' => $asig['vivienda_id']];
    }

    public function liberarVivienda(array &$partida, string $viviendaId): array
    {
        return ['ok' => BloqueA::liberar($partida, $viviendaId)];
    }
}
