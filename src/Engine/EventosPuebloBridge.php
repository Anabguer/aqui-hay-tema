<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** B2 — Puente eventos del pueblo ↔ lifecycle de encuentros. */
final class EventosPuebloBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::ENCUENTRO_TERMINADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
            $enc = $payload['encuentro'] ?? [];
            if (!is_array($enc)) {
                return ['ok' => true, 'skipped' => 'sin_encuentro'];
            }
            $resultado = is_array($payload['resultado'] ?? null) ? $payload['resultado'] : (is_array($enc['resultado'] ?? null) ? $enc['resultado'] : []);
            $cal = CalibracionConfig::load(dirname(__DIR__, 2));

            return EventosPuebloCierreEngine::onEncuentroTerminado($partida, $enc, $resultado, $cal, null, $logger);
        });

        EventBus::on(DomainEvents::ENCUENTRO_CANCELADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
            $enc = $payload['encuentro'] ?? [];
            if (!is_array($enc)) {
                return ['ok' => true, 'skipped' => 'sin_encuentro'];
            }
            $cal = CalibracionConfig::load(dirname(__DIR__, 2));

            return EventosPuebloCierreEngine::onEncuentroCancelado($partida, $enc, $cal, null, $logger);
        });
    }
}
