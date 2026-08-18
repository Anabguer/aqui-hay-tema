<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class RelojOperations
{
    public function __construct(
        private string $projectRoot,
        private ?GameLogger $logger = null,
        private ?EmotionalStateService $emociones = null
    ) {
    }

    public function avanzar(array &$partida, int $horas): array
    {
        $antes = $partida['reloj'];
        Reloj::avanzarHoras($partida, $horas);
        // Coincidencias ANTES de sincronizar: los encuentros siguen programado/en_curso
        // y aún ocupan lugar. Coincidir ≠ interactuar.
        $coins = CoincidenciasEngine::detectarEnIntervalo(
            $partida,
            $this->projectRoot,
            $antes,
            $horas,
            $this->logger
        );
        $sync = EncuentroLifecycle::sincronizarConReloj($partida, $this->logger);
        $expirados = $this->emociones?->expirarVencidos($partida) ?? 0;

        DomainEventDispatcher::emit($partida, DomainEvents::TIEMPO_AVANZADO, [
            'horas' => $horas,
            'antes' => $antes,
            'despues' => $partida['reloj'],
            'encuentros_resueltos' => $sync['resueltos'],
            'estados_expirados' => $expirados,
            'coincidencias_detectadas' => count($coins),
        ], $this->logger, 'RelojOperations::avanzar');

        return [
            'reloj' => $partida['reloj'],
            'texto' => Reloj::formatear($partida['reloj']),
            'encuentros_resueltos' => $sync['resueltos'],
            'estados_expirados' => $expirados,
            'coincidencias_detectadas' => count($coins),
        ];
    }
}
