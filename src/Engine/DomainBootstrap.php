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

        // Contratos de consecuencias posteriores. enabled=false: sin contenido ni fórmulas.
        ConsecuenciaTriggerRegistry::register(DomainEvents::ENCUENTRO_TERMINADO, 'consecuencia_encuentro', false);
        ConsecuenciaTriggerRegistry::register(DomainEvents::RELACION_MODIFICADA, 'consecuencia_relacion', false);
        ConsecuenciaTriggerRegistry::register(DomainEvents::DESCUBRIMIENTO_REGISTRADO, 'consecuencia_descubrimiento', false);
        ConsecuenciaTriggerRegistry::register(DomainEvents::ESTADO_EMOCIONAL_CAMBIADO, 'consecuencia_cambio_emocional', false);
        ConsecuenciaTriggerRegistry::register(DomainEvents::RESIDENTE_INCORPORADO, 'consecuencia_llegada_residente', false);
        ConsecuenciaTriggerRegistry::register(DomainEvents::NPC_AUTONOMO_PLAN, 'consecuencia_evento_autonomo', false);
        ConsecuenciaTriggerRegistry::register(DomainEvents::COINCIDENCIA_RESIDENTES, 'consecuencia_coincidencia', false);

        ContentReactionSubscriber::register();
        EmotionalEventBridge::register();
        ConsecuenciaReactionSubscriber::register();
    }

    public static function resetForTests(): void
    {
        self::$booted = false;
        EventBus::reset();
        NarrativeTriggerRegistry::reset();
        ConsecuenciaTriggerRegistry::reset();
    }
}
