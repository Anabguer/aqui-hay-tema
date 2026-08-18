<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class DomainBootstrap
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        NarrativeTriggerRegistry::register(DomainEvents::ENCUENTRO_TERMINADO, 'dev_encuentro_diario', false);
        NarrativeTriggerRegistry::register(DomainEvents::RELACION_MODIFICADA, 'dev_relacion_buzon', false);
        ContentReactionSubscriber::register();
    }

    public static function resetForTests(): void
    {
        self::$booted = false;
        EventBus::reset();
        NarrativeTriggerRegistry::reset();
    }
}
