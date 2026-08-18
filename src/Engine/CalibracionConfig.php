<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Pesos y cifras de calibración. Provisional: no canon. */
final class CalibracionConfig
{
    /** @return array<string, mixed> */
    public static function load(string $projectRoot): array
    {
        $path = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/configs/calibracion_compatibilidad.json';
        if (!is_file($path)) {
            return ['_provisional' => true, 'compatibilidad' => ['base' => 50, 'min' => 0, 'max' => 100]];
        }
        return JsonFile::read($path);
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
