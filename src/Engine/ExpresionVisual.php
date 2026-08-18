<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * IDs de assets de cara. Catálogo PROVISIONAL, no enum cerrado.
 * Un pack puede tener 1, 4, 9 o más expresiones; el código no asume N.
 */
final class ExpresionVisual
{
    public const NEUTRAL = 'neutral';

    /** Set provisional documentado. No es techo ni validación dura. */
    public const PROVISIONALES = [
        'neutral',
        'alegre',
        'entusiasmado',
        'pensativo',
        'enfadado',
        'triste',
        'sorprendido',
        'esceptico',
        'complice',
    ];

    public static function ids(?CatalogStore $store = null): array
    {
        if ($store !== null) {
            $fromCatalog = $store->ids('expresiones_visuales');
            if ($fromCatalog !== []) {
                return $fromCatalog;
            }
        }
        return self::PROVISIONALES;
    }

    public static function idFormatoValido(string $id): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $id);
    }

    public static function esValida(string $id, ?CatalogStore $store = null): bool
    {
        return self::idFormatoValido($id);
    }

    /** Neutral siempre; el resto solo si el pack lo declara o el catálogo lo conoce. */
    public static function conocida(string $id, ?CatalogStore $store = null, array $packIds = []): bool
    {
        if ($id === self::NEUTRAL) {
            return true;
        }
        if ($packIds !== [] && in_array($id, $packIds, true)) {
            return true;
        }
        return in_array($id, self::ids($store), true);
    }
}
