<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: confianza entre residentes → Historia del Pueblo.
 * Registra hito_22 ("AQUÍ YA HAY CONFIANZA") la primera vez que un par
 * alcanza la banda social "amigo" o superior.
 */
final class HistoriaConfianzaBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::RELACION_MODIFICADA, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
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
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b);
        $social = $rel['social'] ?? [];

        // La confianza se alcanza cuando la banda social es "amigo" o superior
        // en al menos una dirección
        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $bandaA = RelacionBandas::social($social['a_hacia_b']['valor'] ?? null, true, $cal);
        $bandaB = RelacionBandas::social($social['b_hacia_a']['valor'] ?? null, true, $cal);

        $umbrales = ['amigo', 'buen_amigo', 'mejor_amigo'];
        $esConfianza = in_array($bandaA, $umbrales, true) || in_array($bandaB, $umbrales, true);

        if (!$esConfianza) {
            return ['ok' => true, 'skipped' => 'no_alcanza_confianza'];
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            'hito_22',
            [$a, $b],
            ['origen' => 'confianza_alcanzada', 'banda_a' => $bandaA, 'banda_b' => $bandaB]
        ) ?? ['ok' => true, 'skipped' => 'no_registrado'];
    }
}
