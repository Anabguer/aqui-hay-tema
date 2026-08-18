<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Enchufes a sistemas que más adelante podrán cambiar estado emocional.
 * Con emotional_state_from_events_enabled=false NO aplica estados ni fórmulas.
 */
final class EmotionalEventBridge
{
    public static function register(): void
    {
        foreach (self::eventos() as $evento) {
            EventBus::on($evento, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
                return self::handle($partida, $envelope, $logger);
            });
        }
    }

    /** @return list<string> */
    public static function eventos(): array
    {
        return [
            DomainEvents::ENCUENTRO_TERMINADO,
            DomainEvents::ENCUENTRO_CANCELADO,
            DomainEvents::RELACION_MODIFICADA,
            DomainEvents::RESIDENTE_INCORPORADO,
            DomainEvents::TIEMPO_AVANZADO,
            DomainEvents::DESCUBRIMIENTO_REGISTRADO,
            DomainEvents::BUZON_MENSAJE,
            DomainEvents::DIARIO_ENTRADA,
            DomainEvents::EVENTO_EDIFICIO,
            DomainEvents::NPC_AUTONOMO_PLAN,
            DomainEvents::DISCUSION,
        ];
    }

    public static function origenSugerido(string $evento): string
    {
        switch ($evento) {
            case DomainEvents::ENCUENTRO_TERMINADO:
                return 'encuentro';
            case DomainEvents::ENCUENTRO_CANCELADO:
                return 'rechazo';
            case DomainEvents::RELACION_MODIFICADA:
                return 'romance';
            case DomainEvents::RESIDENTE_INCORPORADO:
                return 'inicial';
            case DomainEvents::DESCUBRIMIENTO_REGISTRADO:
                return 'descubrimiento';
            case DomainEvents::BUZON_MENSAJE:
            case DomainEvents::DIARIO_ENTRADA:
                return 'mensaje';
            case DomainEvents::EVENTO_EDIFICIO:
                return 'evento_edificio';
            case DomainEvents::NPC_AUTONOMO_PLAN:
                return 'npc_autonomo';
            case DomainEvents::DISCUSION:
                return 'discusion';
            case DomainEvents::TIEMPO_AVANZADO:
                return 'expiracion';
            default:
                return 'npc_autonomo';
        }
    }

    private static function handle(array &$partida, array $envelope, ?GameLogger $logger): array
    {
        $evento = (string) ($envelope['evento'] ?? '');
        $origen = self::origenSugerido($evento);

        if ($evento === DomainEvents::TIEMPO_AVANZADO) {
            return ['ok' => true, 'skipped' => 'expiracion_la_hace_RelojOperations', 'origen_sugerido' => $origen];
        }

        if (!FeatureConfig::isEnabled($partida, 'emotional_state_from_events_enabled')) {
            return ['ok' => true, 'skipped' => 'feature_disabled', 'origen_sugerido' => $origen];
        }

        \aht_log_optional($logger, $partida, 'emotional_bridge_placeholder', [
            'evento' => $evento,
            'origen_sugerido' => $origen,
            '_placeholder' => true,
            'nota' => 'No se aplica estado. Falta diseño de reglas.',
        ]);

        return ['ok' => true, 'evaluados' => 0, '_placeholder' => true, 'origen_sugerido' => $origen];
    }
}
