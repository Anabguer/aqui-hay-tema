<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** F10.2 — Puente fiesta de cumpleaños ↔ Historia del Pueblo. */
final class CumpleanosFiestaBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::ENCUENTRO_TERMINADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
            $enc = $payload['encuentro'] ?? [];
            if (!is_array($enc)) {
                return ['ok' => true, 'skipped' => 'sin_encuentro'];
            }

            return MensajitoContextualEngine::registrarPrimerCumpleHistoria($partida, $enc) ?? ['ok' => true, 'skipped' => 'no_aplica'];
        });
    }
}
