<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PersonajeValidator
{
    private const CAMPOS_REQUERIDOS = ['id', 'identidad', 'vida'];

    public static function validar(
        array $data,
        string $archivo = '',
        array $lugaresIds = [],
        ?CatalogStore $store = null
    ): array {
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

        if ($store !== null) {
            $errores = array_merge($errores, self::validarContraCatalogos($data, $archivo, $lugaresIds, $store));
        } else {
            if (isset($ident['genero']) && !in_array($ident['genero'], ['mujer', 'hombre', 'no_binarie'], true)) {
                $errores[] = self::err($archivo, 'identidad.genero', $ident['genero'], 'enum_invalido');
            }
        }

        if (isset($ident['edad']) && (!is_int($ident['edad']) || $ident['edad'] < 22 || $ident['edad'] > 72)) {
            $errores[] = self::err($archivo, 'identidad.edad', $ident['edad'], 'rango_invalido_22_72');
        }

        if (isset($ident['atraido_por']) && !is_array($ident['atraido_por'])) {
            $errores[] = self::err($archivo, 'identidad.atraido_por', $ident['atraido_por'], 'tipo_invalido_array');
        }

        $vida = $data['vida'] ?? [];
        if (isset($vida['hobbies_secundarios']) && !is_array($vida['hobbies_secundarios'])) {
            $errores[] = self::err($archivo, 'vida.hobbies_secundarios', $vida['hobbies_secundarios'], 'tipo_invalido_array');
        }
        if (isset($vida['rasgos_publicos'])) {
            if (!is_array($vida['rasgos_publicos'])) {
                $errores[] = self::err($archivo, 'vida.rasgos_publicos', $vida['rasgos_publicos'], 'tipo_invalido_array');
            } elseif (count($vida['rasgos_publicos']) !== 3) {
                $errores[] = self::err($archivo, 'vida.rasgos_publicos', count($vida['rasgos_publicos']), 'cardinalidad_exacta_3');
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

        return $errores;
    }

    private static function validarContraCatalogos(array $data, string $archivo, array $lugaresIds, CatalogStore $store): array
    {
        $errores = [];
        $ident = $data['identidad'] ?? [];
        $vida = $data['vida'] ?? [];
        $romance = $data['romance'] ?? [];

        $check = static function (string $campo, mixed $valor, string $catalogo) use ($store, $archivo, &$errores): void {
            if ($valor === null || $valor === '') {
                return;
            }
            if (!$store->accepts($catalogo, (string) $valor) && !in_array((string) $valor, $store->tecnico($catalogo), true)) {
                $errores[] = self::err($archivo, $campo, $valor, 'catalogo_id_desconocido');
            }
        };

        $checkT = static function (string $campo, mixed $valor, string $claveTecnica) use ($store, $archivo, &$errores): void {
            if ($valor === null || $valor === '') {
                return;
            }
            if (!in_array((string) $valor, $store->tecnico($claveTecnica), true)) {
                $errores[] = self::err($archivo, $campo, $valor, 'enum_invalido');
            }
        };

        $checkT('identidad.genero', $ident['genero'] ?? null, 'genero');
        $checkT('identidad.apertura_descubrimiento', $ident['apertura_descubrimiento'] ?? null, 'apertura_descubrimiento');
        foreach ($ident['atraido_por'] ?? [] as $i => $v) {
            $checkT("identidad.atraido_por[$i]", $v, 'atraido_por');
        }

        $check('vida.ocupacion', $vida['ocupacion'] ?? null, 'ocupaciones');
        $check('vida.franja_disponibilidad', $vida['franja_disponibilidad'] ?? null, 'franjas');
        $check('vida.hobby_principal', $vida['hobby_principal'] ?? null, 'hobbies');
        foreach ($vida['hobbies_secundarios'] ?? [] as $i => $h) {
            $check("vida.hobbies_secundarios[$i]", $h, 'hobbies');
        }
        $check('vida.estilo_social', $vida['estilo_social'] ?? null, 'estilos_sociales');

        if (isset($vida['estilo_social_ejes']) && is_array($vida['estilo_social_ejes'])) {
            $checkT('vida.estilo_social_ejes.energia_social', $vida['estilo_social_ejes']['energia_social'] ?? null, 'energia_social');
            $checkT('vida.estilo_social_ejes.selectividad', $vida['estilo_social_ejes']['selectividad'] ?? null, 'selectividad');
            $checkT('vida.estilo_social_ejes.ritmo', $vida['estilo_social_ejes']['ritmo'] ?? null, 'ritmo_social');
        }

        foreach ($vida['rasgos_publicos'] ?? [] as $i => $r) {
            $check("vida.rasgos_publicos[$i]", $r, 'rasgos');
        }
        foreach ($vida['rasgos_ocultos'] ?? [] as $i => $r) {
            $check("vida.rasgos_ocultos[$i]", $r, 'rasgos');
        }
        foreach ($vida['etiquetas_look_base'] ?? ($data['visual']['etiquetas_look_base'] ?? []) as $i => $t) {
            $check("visual.etiquetas_look_base[$i]", $t, 'etiquetas_look');
        }

        $checkT('romance.necesidad_contacto_base', $romance['necesidad_contacto_base'] ?? null, 'necesidad_contacto');
        foreach ($romance['dealbreakers'] ?? [] as $i => $db) {
            if (isset($db['severidad'])) {
                $checkT("romance.dealbreakers[$i].severidad", $db['severidad'], 'dealbreaker_severidad');
            }
        }
        foreach ($romance['preferencias_romanticas'] ?? [] as $i => $p) {
            if (isset($p['tipo'])) {
                $checkT("romance.preferencias_romanticas[$i].tipo", $p['tipo'], 'pref_rom_tipo');
            }
        }

        $voz = VozPerfil::desdeFicha($data);
        if ($voz['registro'] !== null) {
            $check('narrativa.voz', $voz['registro'], 'voces');
        }
        if ($voz['verbosidad'] !== null) {
            $checkT('narrativa.voz.verbosidad', $voz['verbosidad'], 'voz_verbosidad');
        }
        if ($voz['frontalidad'] !== null) {
            $checkT('narrativa.voz.frontalidad', $voz['frontalidad'], 'voz_frontalidad');
        }
        if ($voz['calidez'] !== null) {
            $checkT('narrativa.voz.calidez', $voz['calidez'], 'voz_calidez');
        }

        unset($lugaresIds);
        return $errores;
    }

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

    public static function validarArchivo(string $path, array $lugaresIds = [], ?CatalogStore $store = null): array
    {
        try {
            $data = JsonFile::read($path);
        } catch (\Throwable $e) {
            return [self::err($path, '_file', null, 'json_invalido: ' . $e->getMessage())];
        }
        return self::validar($data, $path, $lugaresIds, $store);
    }

    public static function auditarDirectorio(string $personajesDir, array $lugaresIds, ?CatalogStore $store = null): array
    {
        $informe = [];
        foreach (glob($personajesDir . '/per_*.json') ?: [] as $file) {
            $errores = self::validarArchivo($file, $lugaresIds, $store);
            if ($errores !== []) {
                $informe[basename($file)] = $errores;
            }
        }
        return $informe;
    }

    private static function idCoincideArchivo(string $id, string $archivo): bool
    {
        return basename($archivo, '.json') === $id;
    }

    private static function err(string $archivo, string $campo, mixed $valor, string $regla): array
    {
        return compact('archivo', 'campo', 'valor', 'regla');
    }
}
