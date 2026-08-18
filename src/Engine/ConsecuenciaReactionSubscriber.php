<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Suscriptor técnico para “consecuencias posteriores”.
 * Ahora solo prepara el contrato: si el flag está apagado, no hace nada.
 */
final class ConsecuenciaReactionSubscriber
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
            DomainEvents::RELACION_MODIFICADA,
            DomainEvents::DESCUBRIMIENTO_REGISTRADO,
            DomainEvents::ESTADO_EMOCIONAL_CAMBIADO,
            DomainEvents::RESIDENTE_INCORPORADO,
            DomainEvents::NPC_AUTONOMO_PLAN,
            DomainEvents::COINCIDENCIA_RESIDENTES,
        ];
    }

    private static function handle(array &$partida, array $envelope, ?GameLogger $logger): array
    {
        if (!FeatureConfig::isEnabled($partida, 'consequences_enabled')) {
            return ['ok' => true, 'skipped' => 'feature_disabled'];
        }

        $evento = (string) ($envelope['evento'] ?? '');
        $triggers = ConsecuenciaTriggerRegistry::triggersFor($evento);
        if ($triggers === [] || array_filter($triggers, static fn($t) => $t['enabled'] ?? false) === []) {
            return ['ok' => true, 'skipped' => 'sin_triggers_activos'];
        }

        // BLOQUEADO_DECISION: evaluación real de consecuencias (sin contenido).
        $logger?->log($partida, 'consequence_trigger_eval', [
            'evento' => $evento,
            'triggers' => count($triggers),
            '_placeholder' => true,
        ]);

        return ['ok' => true, 'evaluados' => count($triggers), '_placeholder' => true];
    }
}

