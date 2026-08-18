<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Emite evento de dominio + audit trail con correlación. */
final class DomainEventDispatcher
{
    public static function emit(
        array &$partida,
        string $evento,
        array $payload,
        ?GameLogger $logger = null,
        ?string $regla = null,
        array $actores = []
    ): string {
        DomainBootstrap::boot();
        $dispatch = EventBus::dispatch($partida, $evento, $payload, $logger);
        $correlacionId = $dispatch['correlacion_id'] ?? ('evt_' . bin2hex(random_bytes(4)));

        AuditTrail::record(
            $partida,
            $evento,
            $actores !== [] ? $actores : ($payload['actores'] ?? []),
            'DomainEventDispatcher',
            $regla ?? $evento,
            $payload['antes'] ?? null,
            $payload['despues'] ?? $payload,
            $payload['rng_roll'] ?? null,
            $correlacionId
        );

        return $correlacionId;
    }
}
