<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Suscriptor preparado para buzón/diario — sin textos ni cupos de producción. */
final class ContentReactionSubscriber
{
    public static function register(): void
    {
        foreach ([
            DomainEvents::ENCUENTRO_TERMINADO,
            DomainEvents::RELACION_MODIFICADA,
            DomainEvents::RESIDENTE_INCORPORADO,
        ] as $evento) {
            EventBus::on($evento, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
                return self::handle($partida, $envelope, $logger);
            });
        }
    }

    private static function handle(array &$partida, array $envelope, ?GameLogger $logger): array
    {
        if (!FeatureConfig::isEnabled($partida, 'narrative_reactions_enabled')) {
            return ['ok' => true, 'skipped' => 'feature_disabled'];
        }

        $evento = $envelope['evento'] ?? '';
        $triggers = NarrativeTriggerRegistry::triggersFor($evento);
        $activos = array_filter($triggers, static fn($t) => $t['enabled'] ?? false);
        if ($activos === []) {
            return ['ok' => true, 'skipped' => 'sin_triggers_activos'];
        }

        // BLOQUEADO_DECISION: evaluación real de triggers y generación de texto
        \aht_log_optional($logger, $partida, 'narrative_trigger_eval', [
            'evento' => $evento,
            'triggers' => count($activos),
            '_placeholder' => true,
        ]);

        return ['ok' => true, 'evaluados' => count($activos)];
    }
}
