<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Única fuente de catálogos creativos y enums técnicos (JSON). */
final class CatalogStore
{
    /** @var array<string, array> */
    private array $fileCache = [];
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    public function hydrate(string $filename, array $data): void
    {
        $this->fileCache[$this->dir() . '/' . $filename] = $data;
    }

    public function dir(): string
    {
        return $this->root . '/data/catalogos';
    }

    public function read(string $filename): array
    {
        $path = $this->dir() . '/' . $filename;
        if (!isset($this->fileCache[$path])) {
            if (!is_file($path)) {
                $this->fileCache[$path] = [];
            } else {
                $this->fileCache[$path] = JsonFile::read($path);
            }
        }
        return $this->fileCache[$path];
    }

    /** @return list<array<string, mixed>> */
    public function items(string $catalogo): array
    {
        switch ($catalogo) {
            case 'hobbies':
            case 'aficiones':
                $file = 'aficiones.json';
                break;
            case 'rasgos':
                $file = 'rasgos.json';
                break;
            case 'estilos_sociales':
                $file = 'estilos_sociales.json';
                break;
            case 'profesiones':
            case 'ocupaciones':
                $file = 'profesiones.json';
                break;
            case 'etiquetas_look':
                $file = 'etiquetas_look.json';
                break;
            case 'indicadores_visuales':
                $file = 'indicadores_visuales.json';
                break;
            case 'franjas':
                $file = 'franjas_disponibilidad.json';
                break;
            case 'voces':
                $file = 'voces.json';
                break;
            default:
                $file = null;
        }
        if ($file === null) {
            return [];
        }
        return $this->read($file)['items'] ?? [];
    }

    /** IDs canónicos + alias (cualquier id aceptable en ficha). */
    public function ids(string $catalogo): array
    {
        $ids = [];
        foreach ($this->items($catalogo) as $item) {
            if (isset($item['id'])) {
                $ids[] = (string) $item['id'];
            }
            foreach ($item['alias'] ?? [] as $alias) {
                $ids[] = (string) $alias;
            }
        }
        return array_values(array_unique($ids));
    }

    public function accepts(string $catalogo, string $id): bool
    {
        return in_array($id, $this->ids($catalogo), true);
    }

    public function item(string $catalogo, string $id): ?array
    {
        foreach ($this->items($catalogo) as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
            if (in_array($id, $item['alias'] ?? [], true)) {
                return $item;
            }
        }
        return null;
    }

    public function canonId(string $catalogo, string $id): string
    {
        $item = $this->item($catalogo, $id);
        if ($item === null) {
            return $id;
        }
        if (!empty($item['alias_de'])) {
            return (string) $item['alias_de'];
        }
        return (string) ($item['id'] ?? $id);
    }

    /** @return list<string> */
    public function tecnico(string $clave): array
    {
        $data = $this->read('enums_tecnicos.json');
        $vals = $data[$clave] ?? [];
        return is_array($vals) ? array_values($vals) : [];
    }

    public function hobby(string $id): ?array
    {
        $item = $this->item('hobbies', $id);
        if ($item === null) {
            return null;
        }
        if (empty($item['lugar_ids'])) {
            foreach ($this->read('hobbies_lugares.json')['items'] ?? [] as $puente) {
                if (($puente['hobby_id'] ?? '') === $id || ($puente['hobby_id'] ?? '') === ($item['id'] ?? '')) {
                    $item['lugar_ids'] = $puente['lugar_ids'] ?? [];
                    break;
                }
            }
        }
        return $item;
    }

    public function ejesEstilo(string $etiqueta): ?array
    {
        $item = $this->item('estilos_sociales', $etiqueta);
        $ejes = $item['ejes'] ?? null;
        return is_array($ejes) ? $ejes : null;
    }
}
