<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Enums cerrados del contrato de personaje (V0). Referencia única para validadores. */
final class ContractEnums
{
    public const GENERO = ['mujer', 'hombre', 'no_binarie'];
    public const ATRAIDO_POR = ['mujer', 'hombre', 'no_binarie'];
    public const APERTURA = ['cerrada', 'permeable', 'abierta'];
    public const OCUPACION = ['sanitario', 'oficina', 'camarero', 'comercio', 'docente', 'estudiante_adulto', 'autonomo', 'jubilado'];
    public const FRANJA = ['manana', 'tarde', 'noche', 'flexible'];
    public const HOBBY = ['leer', 'escribir', 'pasear', 'correr', 'cafe_social', 'manualidades', 'cocina', 'musica', 'cine', 'videojuegos', 'copas', 'baile'];
    public const ESTILO_SOCIAL = ['casero', 'fiestero', 'tranquilo', 'intenso', 'espontaneo', 'planificador'];
    public const RASGO = ['directo', 'timido', 'ironico', 'leal', 'cabezota', 'empatico', 'vanidoso', 'ansioso', 'bromista', 'reservado'];
    public const NECESIDAD_CONTACTO = ['baja', 'media', 'alta'];
    public const DEALBREAKER_SEVERIDAD = ['leve', 'fuerte', 'absoluto'];
    public const PREF_ROM_TIPO = ['ritmo', 'estilo_cita', 'intensidad', 'otro'];

    public static function slotValido(string $slot): bool
    {
        if (preg_match('/^I(0[1-9]|10)$/', $slot)) {
            return true;
        }
        if (preg_match('/^L0[1-6]$/', $slot)) {
            return true;
        }
        return false;
    }

    public static function idPersonajeValido(string $id): bool
    {
        return (bool) preg_match('/^per_[a-z0-9_]{2,8}$/', $id);
    }
}
