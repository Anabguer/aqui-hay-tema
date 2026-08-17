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

        EventBus::on('encounter_finished', static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            AuditTrail::record(
                $partida,
                'encounter_finished',
                $envelope['payload']['encuentro']['participantes'] ?? [],
                'EventBus',
                'encounter_finished',
                null,
                $envelope['payload']['resultado'] ?? null,
                null,
                $envelope['correlacion_id'] ?? null
            );
            return ['ok' => true];
        });
    }
}
