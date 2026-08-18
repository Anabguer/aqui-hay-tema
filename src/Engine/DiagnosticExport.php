<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class DiagnosticExport
{
    public static function export(array $partida, string $projectRoot): array
    {
        FeatureConfig::mergeIntoPartida($partida, $projectRoot);
        $erroresValidacion = PartidaValidator::validar($partida);

        return [
            'ok' => true,
            '_tipo' => 'diagnostico_dev',
            'generado_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'schema_version' => $partida['meta']['schema_version'] ?? null,
            'seed' => $partida['meta']['seed'] ?? null,
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'reloj' => $partida['reloj'] ?? null,
            'features' => $partida['features'] ?? [],
            'residentes' => array_map(static fn($r) => [
                'id' => $r['catalog_id'] ?? null,
                'nombre' => $r['identidad_publica']['nombre'] ?? null,
                'vivienda' => $r['vivienda_id'] ?? null,
                'placeholder' => $r['_placeholder'] ?? false,
            ], $partida['residentes'] ?? []),
            'encuentros_count' => count($partida['encuentros'] ?? []),
            'encuentros_activos' => EncuentroEngine::listarActivos($partida),
            'relaciones_sociales' => count($partida['relaciones_sociales'] ?? []),
            'relaciones_romanticas' => count($partida['relaciones_romanticas'] ?? []),
            'descubrimientos_count' => count($partida['descubrimientos'] ?? []),
            'historial_relaciones_count' => count($partida['historial_relaciones'] ?? []),
            'audit_trail_reciente' => array_slice($partida['audit_trail'] ?? [], -20),
            'domain_events_recientes' => array_slice($partida['domain_events'] ?? [], -20),
            'errores_validacion_partida' => $erroresValidacion,
            'rng_state' => $partida['rng']['state'] ?? null,
        ];
    }
}
