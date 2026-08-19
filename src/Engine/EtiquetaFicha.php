<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Etiquetas de ficha para el jugador. IDs de catálogo no se muestran crudos si hay nombre. */
final class EtiquetaFicha
{
    public static function rasgo(string $id, CatalogStore $store): string
    {
        if ($id === 'directo') {
            return 'Va al grano al hablar';
        }
        $item = $store->item('rasgos', $id);
        $nombre = is_array($item) ? ($item['nombre'] ?? null) : null;
        return is_string($nombre) && $nombre !== '' ? $nombre : $id;
    }

    public static function hobby(string $id, CatalogStore $store): string
    {
        $item = $store->hobby($id) ?? $store->item('hobbies', $id);
        $nombre = is_array($item) ? ($item['nombre'] ?? null) : null;
        return is_string($nombre) && $nombre !== '' ? $nombre : $id;
    }

    public static function ocupacion(string $id, CatalogStore $store): string
    {
        $item = $store->item('ocupaciones', $id);
        $nombre = is_array($item) ? ($item['nombre'] ?? null) : null;
        return is_string($nombre) && $nombre !== '' ? $nombre : $id;
    }
}
