<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PersonajeValidator
{
    private const CAMPOS_REQUERIDOS = ['id', 'identidad', 'vida'];

    /** @param list<string> $lugaresIds ids lug_* del catálogo */
    public static function validar(array $data, string $archivo = '', array $lugaresIds = []): array
    {
        if (!empty($data['_placeholder']) || !empty($data['_dev_only'])) {
            return self::validarPlaceholder($data, $archivo);
        }

        $errores = [];
        foreach (self::CAMPOS_REQUERIDOS as $campo) {
            if (!array_key_exists($campo, $data)) {
                $errores[] = self::err($archivo, $campo, null, 'campo_requerido');
            }
        }

        $id = $data['id'] ?? null;
        if ($id !== null) {
            if (!ContractEnums::idPersonajeValido((string) $id)) {
                $errores[] = self::err($archivo, 'id', $id, 'formato_id_invalido');
            }
            if ($archivo !== '' && !self::idCoincideArchivo((string) $id, $archivo)) {
                $errores[] = self::err($archivo, 'id', $id, 'id_no_coincide_con_archivo');
            }
        }

        if (isset($data['slot']) && !ContractEnums::slotValido((string) $data['slot'])) {
            $errores[] = self::err($archivo, 'slot', $data['slot'], 'enum_invalido');
        }

        $ident = $data['identidad'] ?? [];
        if (!isset($ident['nombre'])) {
            $errores[] = self::err($archivo, 'identidad.nombre', null, 'campo_requerido');
        } elseif (!is_string($ident['nombre']) || strlen($ident['nombre']) < 2 || strlen($ident['nombre']) > 40) {
            $errores[] = self::err($archivo, 'identidad.nombre', $ident['nombre'], 'longitud_invalida');
        }

        if (isset($ident['genero']) && !in_array($ident['genero'], ContractEnums::GENERO, true)) {
            $errores[] = self::err($archivo, 'identidad.genero', $ident['genero'], 'enum_invalido');
        }

        if (isset($ident['edad']) && (!is_int($ident['edad']) || $ident['edad'] < 22 || $ident['edad'] > 72)) {
            $errores[] = self::err($archivo, 'identidad.edad', $ident['edad'], 'rango_invalido_22_72');
        }

        if (isset($ident['atraido_por'])) {
            if (!is_array($ident['atraido_por'])) {
                $errores[] = self::err($archivo, 'identidad.atraido_por', $ident['atraido_por'], 'tipo_invalido_array');
            } else {
                foreach ($ident['atraido_por'] as $i => $v) {
                    if (!in_array($v, ContractEnums::ATRAIDO_POR, true)) {
                        $errores[] = self::err($archivo, "identidad.atraido_por[$i]", $v, 'enum_invalido');
                    }
                }
            }
        }

        if (isset($ident['apertura_descubrimiento']) && !in_array($ident['apertura_descubrimiento'], ContractEnums::APERTURA, true)) {
            $errores[] = self::err($archivo, 'identidad.apertura_descubrimiento', $ident['apertura_descubrimiento'], 'enum_invalido');
        }

        $vida = $data['vida'] ?? [];
        if (isset($vida['ocupacion']) && !in_array($vida['ocupacion'], ContractEnums::OCUPACION, true)) {
            $errores[] = self::err($archivo, 'vida.ocupacion', $vida['ocupacion'], 'enum_invalido');
        }
        if (isset($vida['franja_disponibilidad']) && !in_array($vida['franja_disponibilidad'], ContractEnums::FRANJA, true)) {
            $errores[] = self::err($archivo, 'vida.franja_disponibilidad', $vida['franja_disponibilidad'], 'enum_invalido');
        }
        if (isset($vida['hobby_principal']) && !in_array($vida['hobby_principal'], ContractEnums::HOBBY, true)) {
            $errores[] = self::err($archivo, 'vida.hobby_principal', $vida['hobby_principal'], 'enum_invalido');
        }
        if (isset($vida['hobbies_secundarios'])) {
            if (!is_array($vida['hobbies_secundarios'])) {
                $errores[] = self::err($archivo, 'vida.hobbies_secundarios', $vida['hobbies_secundarios'], 'tipo_invalido_array');
            } else {
                foreach ($vida['hobbies_secundarios'] as $i => $h) {
                    if (!in_array($h, ContractEnums::HOBBY, true)) {
                        $errores[] = self::err($archivo, "vida.hobbies_secundarios[$i]", $h, 'enum_invalido');
                    }
                }
            }
        }
        if (isset($vida['estilo_social']) && !in_array($vida['estilo_social'], ContractEnums::ESTILO_SOCIAL, true)) {
            $errores[] = self::err($archivo, 'vida.estilo_social', $vida['estilo_social'], 'enum_invalido');
        }

        if (isset($vida['rasgos_publicos'])) {
            if (!is_array($vida['rasgos_publicos'])) {
                $errores[] = self::err($archivo, 'vida.rasgos_publicos', $vida['rasgos_publicos'], 'tipo_invalido_array');
            } else {
                if (count($vida['rasgos_publicos']) !== 3) {
                    $errores[] = self::err($archivo, 'vida.rasgos_publicos', count($vida['rasgos_publicos']), 'cardinalidad_exacta_3');
                }
                foreach ($vida['rasgos_publicos'] as $i => $r) {
                    if (!in_array($r, ContractEnums::RASGO, true)) {
                        $errores[] = self::err($archivo, "vida.rasgos_publicos[$i]", $r, 'enum_invalido');
                    }
                }
            }
        }

        if (isset($vida['rasgos_ocultos']) && is_array($vida['rasgos_ocultos'])) {
            foreach ($vida['rasgos_ocultos'] as $i => $r) {
                if (!in_array($r, ContractEnums::RASGO, true)) {
                    $errores[] = self::err($archivo, "vida.rasgos_ocultos[$i]", $r, 'enum_invalido');
                }
            }
        }

        if (isset($vida['lugares_preferentes'])) {
            if (!is_array($vida['lugares_preferentes'])) {
                $errores[] = self::err($archivo, 'vida.lugares_preferentes', $vida['lugares_preferentes'], 'tipo_invalido_array');
            } else {
                if (count($vida['lugares_preferentes']) > 2) {
                    $errores[] = self::err($archivo, 'vida.lugares_preferentes', count($vida['lugares_preferentes']), 'maximo_2');
                }
                foreach ($vida['lugares_preferentes'] as $i => $lug) {
                    if (!is_string($lug) || !str_starts_with($lug, 'lug_')) {
                        $errores[] = self::err($archivo, "vida.lugares_preferentes[$i]", $lug, 'formato_lug_invalido');
                    } elseif ($lugaresIds !== [] && !in_array($lug, $lugaresIds, true)) {
                        $errores[] = self::err($archivo, "vida.lugares_preferentes[$i]", $lug, 'referencia_inexistente');
                    }
                }
            }
        }

        $romance = $data['romance'] ?? [];
        if (isset($romance['necesidad_contacto_base']) && !in_array($romance['necesidad_contacto_base'], ContractEnums::NECESIDAD_CONTACTO, true)) {
            $errores[] = self::err($archivo, 'romance.necesidad_contacto_base', $romance['necesidad_contacto_base'], 'enum_invalido');
        }

        return $errores;
    }

    /** Placeholder dev/sintético — reglas mínimas distintas del canon. */
    public static function validarPlaceholder(array $data, string $archivo = ''): array
    {
        $errores = [];
        if (empty($data['id'])) {
            $errores[] = self::err($archivo, 'id', null, 'campo_requerido');
        }
        if (empty($data['_placeholder']) && empty($data['_dev_only'])) {
            $errores[] = self::err($archivo, '_placeholder', null, 'placeholder_debe_estar_marcado');
        }
        return $errores;
    }

    public static function validarArchivo(string $path, array $lugaresIds = []): array
    {
        try {
            $data = JsonFile::read($path);
        } catch (\Throwable $e) {
            return [self::err($path, '_file', null, 'json_invalido: ' . $e->getMessage())];
        }
        return self::validar($data, $path, $lugaresIds);
    }

    /** Informe de fichas reales sin modificarlas. */
    public static function auditarDirectorio(string $personajesDir, array $lugaresIds): array
    {
        $informe = [];
        foreach (glob($personajesDir . '/per_*.json') ?: [] as $file) {
            $errores = self::validarArchivo($file, $lugaresIds);
            if ($errores !== []) {
                $informe[basename($file)] = $errores;
            }
        }
        return $informe;
    }

    private static function idCoincideArchivo(string $id, string $archivo): bool
    {
        $base = basename($archivo, '.json');
        return $base === $id;
    }

    private static function err(string $archivo, string $campo, mixed $valor, string $regla): array
    {
        return compact('archivo', 'campo', 'valor', 'regla');
    }
}
