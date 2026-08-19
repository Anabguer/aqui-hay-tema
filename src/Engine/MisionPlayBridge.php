<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Un encuentro de Celestine puede completar como máximo una misión del día. */
final class MisionPlayBridge
{
    public static function register(): void
    {
        foreach ([DomainEvents::ENCUENTRO_INICIADO, DomainEvents::ENCUENTRO_TERMINADO] as $evento) {
            EventBus::on($evento, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
                if (!MisionDiariaEngine::activa($partida)) {
                    return ['ok' => true, 'skipped' => 'misiones_off'];
                }
                $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
                $enc = $payload['encuentro'] ?? [];
                if (!is_array($enc)) {
                    return ['ok' => true, 'skipped' => 'sin_encuentro'];
                }
                $cal = [];
                $n = MisionDiariaEngine::onEncuentroCelestine($partida, $enc, $cal, $logger);
                return ['ok' => true, 'misiones_completadas' => $n];
            });
        }
    }
}
