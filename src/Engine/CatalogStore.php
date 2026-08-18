<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Carga ids de catálogos JSON (contenido ampliable). No sustituye el contrato V0 hasta decisión. */
final class CatalogStore
{
    public function __construct(private string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    /** @return list<string> */
    public function ids(string $catalogo): array
    {
        $map = [
            'aficiones' => 'aficiones.json',
            'hobbies' => 'aficiones.json',
            'rasgos' => 'rasgos.json',
            'estilos_sociales' => 'estilos_sociales.json',
            'profesiones' => 'profesiones.json',
            'etiquetas_look' => 'etiquetas_look.json',
            'franjas' => 'franjas_disponibilidad.json',
        ];
        $file = $map[$catalogo] ?? null;
        if ($file === null) {
            return [];
        }
        $path = "{$this->root}/data/catalogos/{$file}";
        if (!is_file($path)) {
            return [];
        }
        $data = JsonFile::read($path);
        $ids = [];
        foreach ($data['items'] ?? [] as $item) {
            if (isset($item['id'])) {
                $ids[] = (string) $item['id'];
            } elseif (isset($item['hobby_id'])) {
                $ids[] = (string) $item['hobby_id'];
            }
        }
        return $ids;
    }
}
