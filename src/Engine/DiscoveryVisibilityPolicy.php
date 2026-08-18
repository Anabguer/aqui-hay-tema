<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Políticas de visibilidad por campo/categoría.
 * Sin asignar a fichas piloto: default = sin_politica.
 */
final class DiscoveryVisibilityPolicy
{
    public const PUBLICO = 'publico';
    public const OCULTO = 'oculto';
    public const PARCIAL = 'parcial';
    public const POR_EVENTO = 'por_evento';
    public const SIN_POLITICA = 'sin_politica';

    public static function load(string $projectRoot): array
    {
        $path = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/configs/discovery_visibility.json';
        if (!is_file($path)) {
            return ['politicas_disponibles' => [self::PUBLICO, self::OCULTO, self::PARCIAL, self::POR_EVENTO], 'default' => self::SIN_POLITICA, 'por_categoria' => []];
        }
        return JsonFile::read($path);
    }

    public static function politicasDisponibles(): array
    {
        return [self::PUBLICO, self::OCULTO, self::PARCIAL, self::POR_EVENTO, self::SIN_POLITICA];
    }

    public static function politicaParaCampo(array $config, string $campo): string
    {
        $mapa = is_array($config['por_categoria'] ?? null) ? $config['por_categoria'] : [];
        if (isset($mapa[$campo])) {
            return (string) $mapa[$campo];
        }
        foreach ($mapa as $prefijo => $pol) {
            if (str_starts_with($campo, (string) $prefijo . '.') || $campo === $prefijo) {
                return (string) $pol;
            }
        }
        return (string) ($config['default'] ?? self::SIN_POLITICA);
    }

    /**
     * Combina política + registros de descubrimiento.
     * BLOQUEADO_DECISION: no aplica a Rocío hasta asignar categorías.
     */
    public static function visibilidad(
        array $partida,
        string $residenteId,
        string $campo,
        array $config
    ): array {
        $politica = self::politicaParaCampo($config, $campo);
        $descubrimiento = DiscoveryEngine::estado($partida, $residenteId, $campo);

        $visible = match ($politica) {
            self::PUBLICO => true,
            self::OCULTO, self::PARCIAL, self::POR_EVENTO => $descubrimiento === DiscoveryEngine::DESCUBIERTO,
            default => null,
        };

        return [
            'campo' => $campo,
            'politica' => $politica,
            'descubrimiento' => $descubrimiento,
            'visible_jugador' => $visible,
            '_nota' => $politica === self::SIN_POLITICA
                ? 'Sin política asignada; no se oculta ni se revela por este motor.'
                : null,
        ];
    }
}
