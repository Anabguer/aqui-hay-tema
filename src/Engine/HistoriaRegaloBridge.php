<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: regalo de Celestine → Historia del Pueblo.
 * Registra hito_20 ("TOMA, PORQUE SÍ") la primera vez que Celestine
 * entrega un regalo a un residente con reacción positiva (le_gusta/le_encanta).
 */
final class HistoriaRegaloBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::BUZON_MENSAJE, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            return self::handle($partida, $envelope);
        });
    }

    public static function handle(array &$partida, array $envelope): array
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $mensaje = is_array($payload['mensaje'] ?? null) ? $payload['mensaje'] : [];

        // Solo mensajes de tipo 'gracias_regalo' (generados por RegaloEngine)
        $tipo = (string) ($mensaje['tipo'] ?? '');
        if ($tipo !== 'gracias_regalo') {
            return ['ok' => true, 'skipped' => 'no_es_gracias_regalo'];
        }

        $actores = is_array($mensaje['actores'] ?? null) ? $mensaje['actores'] : [];
        if ($actores === []) {
            return ['ok' => true, 'skipped' => 'sin_actores'];
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            'hito_20',
            array_slice($actores, 0, 1),
            ['origen' => 'regalo_celestine']
        ) ?? ['ok' => true, 'skipped' => 'no_registrado'];
    }
}
