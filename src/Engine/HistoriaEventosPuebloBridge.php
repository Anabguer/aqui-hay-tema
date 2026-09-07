<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: eventos del pueblo → Historia del Pueblo.
 * Registra hito_23 ("SUJÉTAME EL CUBATA") cuando un evento del pueblo
 * tiene 4+ asistentes, e hito_32 ("TODO EL MUNDO PA' DENTRO") cuando
 * tiene 6+ asistentes.
 */
final class HistoriaEventosPuebloBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::ENCUENTRO_TERMINADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            return self::handle($partida, $envelope);
        });
    }

    public static function handle(array &$partida, array $envelope): array
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $encuentro = is_array($payload['encuentro'] ?? null) ? $payload['encuentro'] : [];

        // Solo eventos del pueblo (intencion=evento_pueblo)
        $intencion = (string) ($encuentro['intencion'] ?? '');
        if ($intencion !== EventosPuebloEngine::INTENCION) {
            return ['ok' => true, 'skipped' => 'no_es_evento_pueblo'];
        }

        $participantes = is_array($encuentro['participantes'] ?? null) ? $encuentro['participantes'] : [];
        $n = count($participantes);
        if ($n < 4) {
            return ['ok' => true, 'skipped' => 'pocos_asistentes'];
        }

        $registrados = [];

        // hito_32: 6+ asistentes (se registra ANTES porque es más exclusivo)
        if ($n >= 6) {
            $reg = HistoriaPuebloEngine::registrar(
                $partida,
                'hito_32',
                array_slice($participantes, 0, 3),
                ['origen' => 'evento_pueblo', 'asistentes' => $n, 'encuentro_id' => $encuentro['id'] ?? null]
            );
            if ($reg !== null && !($reg['ya_existia'] ?? false)) {
                $registrados[] = 'hito_32';
            }
        }

        // hito_23: 4+ asistentes
        if ($n >= 4) {
            $reg = HistoriaPuebloEngine::registrar(
                $partida,
                'hito_23',
                array_slice($participantes, 0, 3),
                ['origen' => 'evento_pueblo', 'asistentes' => $n, 'encuentro_id' => $encuentro['id'] ?? null]
            );
            if ($reg !== null && !($reg['ya_existia'] ?? false)) {
                $registrados[] = 'hito_23';
            }
        }

        return ['ok' => true, 'registrados' => $registrados];
    }
}
