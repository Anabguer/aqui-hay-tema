<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: autonomía del pueblo → Historia del Pueblo.
 * Registra hito_24 ("ESTE PUEBLO YA TIENE VIDA") la primera vez que
 * un NPC autónomo ejecuta un plan sin intervención del jugador.
 */
final class HistoriaAutonomiaBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::NPC_AUTONOMO_PLAN, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            return self::handle($partida, $envelope);
        });
    }

    public static function handle(array &$partida, array $envelope): array
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $actores = is_array($payload['actores'] ?? null) ? $payload['actores'] : ($envelope['actores'] ?? []);

        if ($actores === []) {
            return ['ok' => true, 'skipped' => 'sin_actores'];
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            'hito_24',
            array_slice($actores, 0, 2),
            ['origen' => 'npc_autonomo']
        ) ?? ['ok' => true, 'skipped' => 'no_registrado'];
    }
}
