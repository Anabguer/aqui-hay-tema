<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Pesos y cifras de calibracion. Provisional: no canon. */
final class CalibracionConfig
{
    private static ?array $testOverrides = null;

    public static function setTestOverrides(?array $overrides): void
    {
        self::$testOverrides = $overrides;
    }

    /** @return array<string, mixed> */
    public static function load(string $projectRoot): array
    {
        $root = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/configs';
        $out = ['_provisional' => true];
        foreach (['calibracion_compatibilidad.json', 'calibracion_vida.json'] as $file) {
            $path = $root . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $chunk = JsonFile::read($path);
            if (is_array($chunk)) {
                $out = array_merge($out, $chunk);
                $out['_provisional'] = true;
            }
        }
        if (is_array(self::$testOverrides)) {
            $out = array_replace_recursive($out, self::$testOverrides);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $cal
     * @return mixed
     */
    public static function get(array $cal, string $ruta, $default = null)
    {
        $cur = $cal;
        foreach (explode('.', $ruta) as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) {
                return $default;
            }
            $cur = $cur[$k];
        }
        return $cur;
    }
}