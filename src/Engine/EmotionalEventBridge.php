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
        return match ($evento) {
            DomainEvents::ENCUENTRO_TERMINADO => 'encuentro',
            DomainEvents::ENCUENTRO_CANCELADO => 'rechazo',
            DomainEvents::RELACION_MODIFICADA => 'romance',
            DomainEvents::RESIDENTE_INCORPORADO => 'inicial',
            DomainEvents::DESCUBRIMIENTO_REGISTRADO => 'descubrimiento',
            DomainEvents::BUZON_MENSAJE => 'mensaje',
            DomainEvents::DIARIO_ENTRADA => 'mensaje',
            DomainEvents::EVENTO_EDIFICIO => 'evento_edificio',
            DomainEvents::NPC_AUTONOMO_PLAN => 'npc_autonomo',
            DomainEvents::DISCUSION => 'discusion',
            DomainEvents::TIEMPO_AVANZADO => 'expiracion',
            default => 'npc_autonomo',
        };
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

        $logger?->log($partida, 'emotional_bridge_placeholder', [
            'evento' => $evento,
            'origen_sugerido' => $origen,
            '_placeholder' => true,
            'nota' => 'No se aplica estado. Falta diseño de reglas.',
        ]);

        return ['ok' => true, 'evaluados' => 0, '_placeholder' => true, 'origen_sugerido' => $origen];
    }
}
