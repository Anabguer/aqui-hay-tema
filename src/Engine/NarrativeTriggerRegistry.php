<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Registro de triggers narrativos — desactivado hasta contenido aprobado. */
final class NarrativeTriggerRegistry
{
    /** @var array<string, list<array{id: string, evento: string, enabled: bool}>> */
    private static array $triggers = [];

    public static function register(string $evento, string $triggerId, bool $enabled = false): void
    {
        self::$triggers[$evento][] = ['id' => $triggerId, 'evento' => $evento, 'enabled' => $enabled];
    }

    public static function triggersFor(string $evento): array
    {
        return self::$triggers[$evento] ?? [];
    }

    public static function reset(): void
    {
        self::$triggers = [];
    }
}
