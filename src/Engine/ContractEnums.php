<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Formato de IDs/slots. Los catálogos creativos NO viven aquí. */
final class ContractEnums
{
    public static function slotValido(string $slot): bool
    {
        return (bool) preg_match('/^I(0[1-9]|10)$/', $slot)
            || (bool) preg_match('/^L0[1-6]$/', $slot);
    }

    public static function idPersonajeValido(string $id): bool
    {
        return (bool) preg_match('/^per_[a-z0-9_]{2,8}$/', $id);
    }
}
