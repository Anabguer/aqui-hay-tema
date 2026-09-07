<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: marcha efectiva de un residente → Historia del Pueblo.
 * Registra hito_25 ("PUES YO ME VOY") cuando un residente abandona el pueblo.
 */
final class HistoriaMarchaBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::MARCHA_EFECTIVA, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            return self::handle($partida, $envelope);
        });
    }

    public static function handle(array &$partida, array $envelope): array
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $residenteId = (string) ($payload['residente_id'] ?? '');
        if ($residenteId === '' || !isset($partida['residentes'][$residenteId])) {
            return ['ok' => true, 'skipped' => 'residente_no_encontrado'];
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            'hito_25',
            [$residenteId],
            [
                'origen' => 'marcha_efectiva',
                'causa' => $payload['causa'] ?? null,
            ]
        ) ?? ['ok' => true, 'skipped' => 'no_registrado'];
    }
}
