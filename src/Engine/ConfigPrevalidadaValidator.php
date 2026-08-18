<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class ConfigPrevalidadaValidator
{
    public static function validar(array $data, string $archivo = '', array $personajeIds = [], array $lugarIds = []): array
    {
        $errores = [];
        if (empty($data['id'])) {
            $errores[] = self::err($archivo, 'id', null, 'campo_requerido');
        }

        foreach ($data['residentes_iniciales'] ?? [] as $i => $entry) {
            if (!is_array($entry)) {
                $errores[] = self::err($archivo, "residentes_iniciales[$i]", $entry, 'tipo_invalido');
                continue;
            }
            $cid = $entry['catalog_id'] ?? null;
            if ($cid === null) {
                $errores[] = self::err($archivo, "residentes_iniciales[$i].catalog_id", null, 'campo_requerido');
            } elseif ($personajeIds !== [] && !in_array($cid, $personajeIds, true)) {
                $errores[] = self::err($archivo, "residentes_iniciales[$i].catalog_id", $cid, 'referencia_inexistente');
            }
            $pres = $entry['presencia'] ?? 'residente';
            if (!in_array($pres, ['residente', 'nuevo', 'ausente'], true)) {
                $errores[] = self::err($archivo, "residentes_iniciales[$i].presencia", $pres, 'enum_invalido');
            }
        }

        foreach ($data['lugares_operativos_dia_1'] ?? [] as $i => $lug) {
            if (!is_string($lug) || !str_starts_with($lug, 'lug_')) {
                $errores[] = self::err($archivo, "lugares_operativos_dia_1[$i]", $lug, 'formato_lug_invalido');
            } elseif ($lugarIds !== [] && !in_array($lug, $lugarIds, true)) {
                $errores[] = self::err($archivo, "lugares_operativos_dia_1[$i]", $lug, 'referencia_inexistente');
            }
        }

        return $errores;
    }

    private static function err(string $archivo, string $campo, $valor, string $regla): array
    {
        return compact('archivo', 'campo', 'valor', 'regla');
    }
}
