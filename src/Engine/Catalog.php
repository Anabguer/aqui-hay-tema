<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class Catalog
{
    private string $root;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
    }

    public function loadPersonaje(string $id): array
    {
        $path = "{$this->root}/data/personajes/{$id}.json";
        return JsonFile::read($path);
    }

    public function loadLugares(): array
    {
        return JsonFile::read("{$this->root}/data/lugares/lugares.json");
    }

    public function loadConfigPrevalidada(string $configId): array
    {
        $path = "{$this->root}/data/configs/prevalidadas/{$configId}.json";
        return JsonFile::read($path);
    }

    public function listPersonajeIds(): array
    {
        $ids = [];
        foreach (glob("{$this->root}/data/personajes/per_*.json") ?: [] as $file) {
            $ids[] = basename($file, '.json');
        }
        sort($ids);
        return $ids;
    }
}
