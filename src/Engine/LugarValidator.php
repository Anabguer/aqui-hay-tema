<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class LugarValidator
{
    public static function validar(array $data, string $archivo = ''): array
    {
        $errores = [];
        if (!isset($data['items']) || !is_array($data['items'])) {
            $errores[] = self::err($archivo, 'items', null, 'campo_requerido_array');
            return $errores;
        }

        $ids = [];
        foreach ($data['items'] as $i => $item) {
            if (!is_array($item)) {
                $errores[] = self::err($archivo, "items[$i]", $item, 'tipo_invalido');
                continue;
            }
            $id = $item['id'] ?? null;
            if ($id === null) {
                $errores[] = self::err($archivo, "items[$i].id", null, 'campo_requerido');
                continue;
            }
            if (!is_string($id) || !str_starts_with($id, 'lug_')) {
                $errores[] = self::err($archivo, "items[$i].id", $id, 'formato_lug_invalido');
            }
            if (isset($ids[$id])) {
                $errores[] = self::err($archivo, "items[$i].id", $id, 'id_duplicado');
            }
            $ids[$id] = true;
            if (!isset($item['nombre']) || !is_string($item['nombre']) || $item['nombre'] === '') {
                $errores[] = self::err($archivo, "items[$i].nombre", $item['nombre'] ?? null, 'campo_requerido');
            }
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

    /** @return list<string> */
    public static function extraerIds(array $data): array
    {
        $ids = [];
        foreach ($data['items'] ?? [] as $item) {
            if (isset($item['id'])) {
                $ids[] = (string) $item['id'];
            }
        }
        return $ids;
    }

    private static function err(string $archivo, string $campo, mixed $valor, string $regla): array
    {
        return compact('archivo', 'campo', 'valor', 'regla');
    }
}
