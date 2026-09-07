<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: primer cotilleo real del pueblo → Historia del Pueblo.
 * Registra hito_33 ("AQUÍ HUELE A COTILLEO") la primera vez que se publica
 * un cotilleo relevante (romance, drama, rechazo) en el buzón.
 * Excluye eventos benignos como SE_CONOCIERON o PRIMERA_CITA.
 */
final class HistoriaCotilleoBridge
{
    /** @var list<string> Tipos de hito que cuentan como "cotilleo real" */
    private const HITOS_GOSIP = [
        RelacionBitacora::FLECHAZO,
        RelacionBitacora::HITO_ROMANTICO,
        RelacionBitacora::DECLARACION,
        RelacionBitacora::INICIO_PAREJA,
        RelacionBitacora::VUELTA,
        RelacionBitacora::DISCUSION_FUERTE,
        RelacionBitacora::CRISIS,
        RelacionBitacora::RUPTURA,
        RelacionBitacora::RECHAZO_IMPORTANTE,
    ];

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

        $tipo = (string) ($mensaje['tipo'] ?? '');
        if ($tipo !== 'cotilleo_hito') {
            return ['ok' => true, 'skipped' => 'no_es_cotilleo_hito'];
        }

        $hitoTipo = (string) ($mensaje['hito_tipo'] ?? '');
        if (!in_array($hitoTipo, self::HITOS_GOSIP, true)) {
            return ['ok' => true, 'skipped' => 'hito_no_es_gossip'];
        }

        $actores = is_array($mensaje['actores'] ?? null) ? $mensaje['actores'] : [];
        if ($actores === []) {
            return ['ok' => true, 'skipped' => 'sin_actores'];
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            'hito_33',
            array_slice($actores, 0, 1),
            [
                'origen' => 'cotilleo_publicado',
                'hito_tipo' => $hitoTipo,
            ]
        ) ?? ['ok' => true, 'skipped' => 'no_registrado'];
    }
}
