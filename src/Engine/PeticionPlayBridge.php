<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Un encuentro de Celestine puede completar como máximo una petición abierta. */
final class PeticionPlayBridge
{
    public static function register(): void
    {
        foreach ([DomainEvents::ENCUENTRO_INICIADO, DomainEvents::ENCUENTRO_TERMINADO] as $evento) {
            EventBus::on($evento, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
                if (!PeticionPuebloEngine::activa($partida)) {
                    return ['ok' => true, 'skipped' => 'peticiones_off'];
                }
                $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
                $enc = $payload['encuentro'] ?? [];
                if (!is_array($enc)) {
                    return ['ok' => true, 'skipped' => 'sin_encuentro'];
                }
                $cal = CalibracionConfig::load(dirname(__DIR__, 2));
                $n = PeticionPuebloEngine::onEncuentroCelestine($partida, $enc, $cal, $logger);
                return ['ok' => true, 'peticiones_completadas' => $n];
            });
        }
    }
}
