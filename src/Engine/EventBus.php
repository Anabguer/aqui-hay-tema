<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Bus de eventos de dominio simple — sin framework. */
final class EventBus
{
    /** @var array<string, list<callable>> */
    private static array $listeners = [];

    public static function on(string $evento, callable $handler): void
    {
        self::$listeners[$evento][] = $handler;
    }

    public static function dispatch(array &$partida, string $evento, array $payload, ?GameLogger $logger = null): array
    {
        $envelope = [
            'evento' => $evento,
            'payload' => $payload,
            'correlacion_id' => 'evt_' . bin2hex(random_bytes(4)),
        ];

        $logger?->log($partida, 'domain_event', $envelope);

        $results = [];
        foreach (self::$listeners[$evento] ?? [] as $handler) {
            $results[] = $handler($partida, $envelope, $logger);
        }
        return ['evento' => $evento, 'results' => $results];
    }

    public static function reset(): void
    {
        self::$listeners = [];
    }
}
