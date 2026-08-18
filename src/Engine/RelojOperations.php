<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class RelojOperations
{
    public function __construct(private ?GameLogger $logger = null)
    {
    }

    public function avanzar(array &$partida, int $horas): array
    {
        $antes = $partida['reloj'];
        Reloj::avanzarHoras($partida, $horas);
        $sync = EncuentroLifecycle::sincronizarConReloj($partida, $this->logger);

        DomainEventDispatcher::emit($partida, DomainEvents::TIEMPO_AVANZADO, [
            'horas' => $horas,
            'antes' => $antes,
            'despues' => $partida['reloj'],
            'encuentros_resueltos' => $sync['resueltos'],
        ], $this->logger, 'RelojOperations::avanzar');

        return [
            'reloj' => $partida['reloj'],
            'texto' => Reloj::formatear($partida['reloj']),
            'encuentros_resueltos' => $sync['resueltos'],
        ];
    }
}
