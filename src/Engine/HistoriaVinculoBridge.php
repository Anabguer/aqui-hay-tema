<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: vínculo fuerte entre residentes → Historia del Pueblo.
 * Registra hito_30 ("AHORA SÍ SOMOS UÑA Y CARNE") cuando la banda social
 * alcanza "buen_amigo" o superior, y hito_28 ("DE AQUÍ NO ME MUEVE NADIE")
 * cuando alcanza "mejor_amigo".
 */
final class HistoriaVinculoBridge
{
    private const HITO_UMBRAL = [
        'mejor_amigo' => 'hito_28',
        'buen_amigo'  => 'hito_30',
    ];

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

        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $bandaA = RelacionBandas::social($social['a_hacia_b']['valor'] ?? null, true, $cal);
        $bandaB = RelacionBandas::social($social['b_hacia_a']['valor'] ?? null, true, $cal);

        $registrados = [];
        foreach (self::HITO_UMBRAL as $umbral => $hitoId) {
            if ($bandaA === $umbral || $bandaB === $umbral) {
                $reg = HistoriaPuebloEngine::registrar(
                    $partida,
                    $hitoId,
                    [$a, $b],
                    ['origen' => 'vinculo_social', 'banda_a' => $bandaA, 'banda_b' => $bandaB]
                );
                if ($reg !== null && !($reg['ya_existia'] ?? false)) {
                    $registrados[] = $hitoId;
                }
            }
        }

        return ['ok' => true, 'registrados' => $registrados];
    }
}
