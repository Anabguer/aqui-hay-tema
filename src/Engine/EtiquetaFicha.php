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

    /** Etiqueta de rasgo adaptada al género del residente (p. ej. Tímido/a → Tímida). */
    public static function rasgoParaGenero(string $id, ?string $genero, CatalogStore $store): string
    {
        $raw = self::rasgo($id, $store);
        if (!preg_match('/^(.+)\/a$/u', $raw, $m)) {
            return $raw;
        }
        $masc = $m[1];
        $fem = preg_replace('/o$/u', 'a', $masc);
        if ($fem === $masc) {
            $fem = $masc . 'a';
        }
        switch ($genero) {
            case 'mujer':
                return $fem;
            case 'hombre':
                return $masc;
            default:
                return $masc;
        }
    }

    public static function lugar(string $id, CatalogStore $store): string
    {
        $item = $store->item('lugares', $id);
        if (!is_array($item)) {
            foreach ($store->items('lugares') as $lug) {
                if (is_array($lug) && ($lug['id'] ?? '') === $id) {
                    $item = $lug;
                    break;
                }
            }
        }
        $nombre = is_array($item) ? (string) ($item['nombre'] ?? '') : '';
        if ($nombre === '') {
            $nombre = ucfirst(str_replace(['lug_', '_'], ['', ' '], $id));
        }
        return self::lugarDisplay($id, $nombre);
    }

    private static function lugarDisplay(string $id, string $nombre): string
    {
        static $map = [
            'lug_cafeteria' => 'Cafetería',
            'Cafeteria' => 'Cafetería',
            'lug_cine' => 'Cine',
            'cine' => 'Cine',
        ];
        return $map[$id] ?? $map[$nombre] ?? $nombre;
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

    public static function visual(string $id, CatalogStore $store): string
    {
        $item = $store->item('indicadores_visuales', $id);
        if ($item === null) {
            $item = $store->item('etiquetas_look', $id);
        }
        $nombre = is_array($item) ? ($item['nombre'] ?? null) : null;
        if (is_string($nombre) && $nombre !== '') {
            return $nombre;
        }
        return str_replace('_', ' ', $id);
    }

    public static function estilo(string $id, CatalogStore $store): string
    {
        $item = $store->item('estilos_sociales', $id);
        $nombre = is_array($item) ? ($item['nombre'] ?? null) : null;
        return is_string($nombre) && $nombre !== '' ? $nombre : $id;
    }
}
