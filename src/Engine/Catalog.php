<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class Catalog
{
    /** Fichas técnicas/QA: cargables para tests, nunca en pools jugables. */
    private const EXCLUIDOS_POOL_JUGABLE = ['per_qa_valid'];

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
        $data = IdentidadCanon::sanitizarPersonaje($data);
        $errores = PersonajeValidator::validar($data, $path, $this->lugarIds(), $this->store());
        if ($errores !== []) {
            throw new ContentValidationException($errores);
        }
        return $data;
    }

    public function store(): CatalogStore
    {
        return new CatalogStore($this->root);
    }

    public function getRoot(): string
    {
        return $this->root;
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
    public function listPersonajeIdsJugables(): array
    {
        $excl = self::EXCLUIDOS_POOL_JUGABLE;
        return array_values(array_filter(
            $this->listPersonajeIds(),
            static fn(string $id): bool => !in_array($id, $excl, true)
        ));
    }

    public function esPersonajeJugable(string $id): bool
    {
        return $id !== '' && !in_array($id, self::EXCLUIDOS_POOL_JUGABLE, true);
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
