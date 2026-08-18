<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class Catalog
{
    private string $root;
    private ?array $lugaresCache = null;
    private ?array $lugarIdsCache = null;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
    }

    public function loadPersonaje(string $id): array
    {
        $path = "{$this->root}/data/personajes/{$id}.json";
        if (!is_file($path)) {
            throw new ContentValidationException([[
                'archivo' => $path,
                'campo' => 'id',
                'valor' => $id,
                'regla' => 'archivo_no_encontrado',
            ]]);
        }
        $data = JsonFile::read($path);
        $errores = PersonajeValidator::validar($data, $path, $this->lugarIds());
        if ($errores !== []) {
            throw new ContentValidationException($errores);
        }
        return $data;
    }

    /** Carga sin validación estricta — solo para auditoría de fichas reales. */
    public function loadPersonajeRaw(string $id): array
    {
        return JsonFile::read("{$this->root}/data/personajes/{$id}.json");
    }

    public function loadLugares(): array
    {
        if ($this->lugaresCache !== null) {
            return $this->lugaresCache;
        }
        $path = "{$this->root}/data/lugares/lugares.json";
        $data = JsonFile::read($path);
        $errores = LugarValidator::validar($data, $path);
        if ($errores !== []) {
            throw new ContentValidationException($errores);
        }
        $this->lugaresCache = $data;
        $this->lugarIdsCache = LugarValidator::extraerIds($data);
        return $data;
    }

    public function loadConfigPrevalidada(string $configId): array
    {
        $path = "{$this->root}/data/configs/prevalidadas/{$configId}.json";
        $data = JsonFile::read($path);
        $errores = ConfigPrevalidadaValidator::validar(
            $data,
            $path,
            $this->listPersonajeIds(),
            $this->lugarIds()
        );
        if ($errores !== []) {
            throw new ContentValidationException($errores);
        }
        return $data;
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

    /** @return list<string> */
    public function lugarIds(): array
    {
        if ($this->lugarIdsCache === null) {
            $this->loadLugares();
        }
        return $this->lugarIdsCache ?? [];
    }
}
