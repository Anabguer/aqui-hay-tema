<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PersonajeValidator
{
    private const CAMPOS_REQUERIDOS = ['id', 'identidad', 'vida'];

    public static function validar(array $data, string $archivo = ''): array
    {
        $errores = [];
        foreach (self::CAMPOS_REQUERIDOS as $campo) {
            if (!isset($data[$campo])) {
                $errores[] = self::err($archivo, $campo, null, 'campo_requerido');
            }
        }
        if (isset($data['identidad']) && !isset($data['identidad']['nombre'])) {
            $errores[] = self::err($archivo, 'identidad.nombre', null, 'campo_requerido');
        }
        return $errores;
    }

    public static function validarArchivo(string $path): array
    {
        try {
            $data = JsonFile::read($path);
        } catch (\Throwable $e) {
            return [self::err($path, '_file', null, 'json_invalido: ' . $e->getMessage())];
        }
        return self::validar($data, $path);
    }

    private static function err(string $archivo, string $campo, mixed $valor, string $regla): array
    {
        return compact('archivo', 'campo', 'valor', 'regla');
    }
}
