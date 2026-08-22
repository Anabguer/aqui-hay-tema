<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class Catalog
{
    /** @see PoolJugableCanon — pool jugable = per_p001…per_p200 con ficha y retrato canónico. */

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

    /**
     * Pool canónico juego_v1: per_p* con ficha válida y pack visual P00x resoluble.
     *
     * @return list<string>
     */
    public function listPersonajeIdsJugables(): array
    {
        $packs = new VisualPackStore($this->root);
        $out = [];
        foreach (PoolJugableCanon::ids($this->root) as $id) {
            if (!$this->esIdCanonicoPool($id)) {
                continue;
            }
            try {
                $personaje = $this->loadPersonaje($id);
            } catch (\Throwable $e) {
                continue;
            }
            $runtime = ResidenteRuntime::crearDesdeCatalogo($personaje);
            $tok = RetratoResolver::resolver($runtime, $id, $packs, $this->root);
            if ($tok['url'] === null) {
                continue;
            }
            $out[] = $id;
        }
        return $out;
    }

    public function esPersonajeJugable(string $id): bool
    {
        if (!$this->esIdCanonicoPool($id)) {
            return false;
        }
        $path = "{$this->root}/data/personajes/{$id}.json";
        if (!is_file($path)) {
            return false;
        }
        try {
            $personaje = $this->loadPersonaje($id);
            $runtime = ResidenteRuntime::crearDesdeCatalogo($personaje);
            $packs = new VisualPackStore($this->root);
            return RetratoResolver::resolver($runtime, $id, $packs, $this->root)['url'] !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return list<string> */
    public function lugarIds(): array
    {
        if ($this->lugarIdsCache === null) {
            $this->loadLugares();
        }
        return $this->lugarIdsCache ?? [];
    }

    private function esIdCanonicoPool(string $id): bool
    {
        return PoolJugableCanon::esIdCanonico($id);
    }
}
