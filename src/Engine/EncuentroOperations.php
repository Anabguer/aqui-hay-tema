<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class EncuentroOperations
{
    private ?GameLogger $logger;

    public function __construct(?GameLogger $logger = null)
    {
        $this->logger = $logger;
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

    public function proponer(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugar = null
    ): array {
        $r = PropuestaEncuentroEngine::proponer(
            $partida,
            $participantes,
            $dia,
            $hora,
            $tipo,
            $lugar,
            null,
            null,
            $this->logger
        );
        if (($r['ok'] ?? false) && isset($r['encuentro']) && is_array($r['encuentro'])) {
            DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_PROGRAMADO, [
                'encuentro' => $r['encuentro'],
                'actores' => $participantes,
                'via' => 'propuesta',
            ], $this->logger, 'EncuentroOperations::proponer', $participantes);
        }
        return $r;
    }

    public function cancelar(array &$partida, string $encuentroId): array
    {
        return EncuentroEngine::cancelar($partida, $encuentroId, $this->logger);
    }
}
