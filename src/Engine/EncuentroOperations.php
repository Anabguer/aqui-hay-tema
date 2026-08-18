<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class EncuentroOperations
{
    public function __construct(private ?GameLogger $logger = null)
    {
    }

    public function programar(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugar = null
    ): array {
        $r = EncuentroEngine::programar(
            $partida,
            $participantes,
            $dia,
            $hora,
            $tipo,
            $lugar,
            null,
            $this->logger
        );
        if ($r['ok'] ?? false) {
            DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_PROGRAMADO, [
                'encuentro' => $r['encuentro'],
                'actores' => $participantes,
            ], $this->logger, 'EncuentroOperations::programar', $participantes);
        }
        return $r;
    }
}
