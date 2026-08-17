<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class FeatureConfig
{
    public static function defaults(string $projectRoot): array
    {
        $path = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/configs/features.json';
        if (!is_file($path)) {
            return ['debug_tools_enabled' => false];
        }
        return JsonFile::read($path);
    }

    public static function mergeIntoPartida(array &$partida, string $projectRoot): void
    {
        $partida['features'] = array_merge(
            self::defaults($projectRoot),
            is_array($partida['features'] ?? null) ? $partida['features'] : []
        );
    }

    public static function isEnabled(array $partida, string $flag): bool
    {
        return (bool) ($partida['features'][$flag] ?? false);
    }
}
