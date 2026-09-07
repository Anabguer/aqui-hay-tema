<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: primer estado emocional de cada tipo → Historia del Pueblo.
 * Registra hito_17 (triste), hito_18 (enfadado), hito_19 (alegre)
 * la primera vez que CUALQUIER residente entra en ese estado.
 */
final class HistoriaEmocionesBridge
{
    /** @var array<string, string> estado emocional → hito_id */
    private const MAPA = [
        EstadoEmocional::TRISTE   => 'hito_17',
        EstadoEmocional::ENFADADO => 'hito_18',
        EstadoEmocional::ALEGRE   => 'hito_19',
    ];

    public static function register(): void
    {
        EventBus::on(DomainEvents::ESTADO_EMOCIONAL_CAMBIADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            return self::handle($partida, $envelope);
        });
    }

    public static function handle(array &$partida, array $envelope): array
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $antes = is_array($payload['antes'] ?? null) ? $payload['antes'] : [];
        $despues = is_array($payload['despues'] ?? null) ? $payload['despues'] : [];
        $antesId = (string) ($antes['id'] ?? EstadoEmocional::NEUTRO);
        $despuesId = (string) ($despues['id'] ?? EstadoEmocional::NEUTRO);

        if ($antesId === $despuesId) {
            return ['ok' => true, 'skipped' => 'sin_cambio'];
        }

        $hitoId = self::MAPA[$despuesId] ?? null;
        if ($hitoId === null) {
            return ['ok' => true, 'skipped' => 'emocion_no_registrable'];
        }

        $residenteId = (string) ($payload['residente_id'] ?? ($envelope['actores'][0] ?? ''));
        if ($residenteId === '' || !isset($partida['residentes'][$residenteId])) {
            return ['ok' => true, 'skipped' => 'residente_no_encontrado'];
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            $hitoId,
            [$residenteId],
            [
                'origen' => 'emocion_cambiada',
                'estado_anterior' => $antesId,
                'estado_nuevo' => $despuesId,
            ]
        ) ?? ['ok' => true, 'skipped' => 'no_registrado'];
    }
}
