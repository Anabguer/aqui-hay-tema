<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: interés romántico mutuo → Historia del Pueblo.
 * Registra hito_04 ("PUES PARECE QUE ES MUTUO") la primera vez que
 * ambos residentes tienen señal romántica el uno hacia el otro.
 */
final class HistoriaInteresMutuoBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::SENAL_ROMANTICA, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            return self::handle($partida, $envelope);
        });
    }

    public static function handle(array &$partida, array $envelope): array
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $actores = is_array($payload['actores'] ?? null) ? $payload['actores'] : [];

        if (count($actores) < 2) {
            return ['ok' => true, 'skipped' => 'pocos_actores'];
        }

        $a = (string) $actores[0];
        $b = (string) $actores[1];

        $cal = CalibracionConfig::load(dirname(__DIR__, 2));

        // Verificar si AMBOS tienen señal romántica el uno hacia el otro
        $ab = SenalRomantica::desdeHacia($partida, $a, $b, $cal);
        $ba = SenalRomantica::desdeHacia($partida, $b, $a, $cal);

        if (empty($ab['ok']) || empty($ba['ok'])) {
            return ['ok' => true, 'skipped' => 'no_es_mutuo'];
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            'hito_04',
            [$a, $b],
            ['origen' => 'interes_mutuo']
        ) ?? ['ok' => true, 'skipped' => 'no_registrado'];
    }
}
