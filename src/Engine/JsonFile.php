<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class JsonFile
{
    public static function read(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Archivo no encontrado: {$path}");
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("No se pudo leer: {$path}");
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException("JSON inválido: {$path}");
        }
        return $data;
    }

    public static function write(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear directorio: {$dir}");
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json . "\n", LOCK_EX) === false) {
            throw new \RuntimeException("No se pudo escribir: {$path}");
        }
    }
}
