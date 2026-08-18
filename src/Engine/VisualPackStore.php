<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Consume packs gráficos registrados o descubiertos en disco. Nunca genera PNG.
 * Un pack puede declarar 1, 4, 9 o N expresiones; no hay cantidad fija.
 */
final class VisualPackStore
{
    /** @var array<string, mixed>|null */
    private ?array $registro = null;
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    public function registro(): array
    {
        if ($this->registro !== null) {
            return $this->registro;
        }
        $packs = [];
        $path = $this->root . '/data/personajes/_visual_packs/registro.json';
        if (is_file($path)) {
            $data = JsonFile::read($path);
            $packs = is_array($data['packs'] ?? null) ? $data['packs'] : [];
        }
        foreach ($this->descubrirEnDisco() as $id => $pack) {
            if (!isset($packs[$id])) {
                $packs[$id] = $pack;
            } else {
                $packs[$id] = array_merge($pack, $packs[$id]);
                $packs[$id]['pack_id'] = $id;
            }
        }
        foreach ($packs as $id => &$pack) {
            if (is_array($pack)) {
                $pack['pack_id'] = $id;
            }
        }
        unset($pack);
        $this->registro = ['packs' => $packs];
        return $this->registro;
    }

    /** @return array<string, array> */
    public function packs(): array
    {
        $packs = $this->registro()['packs'] ?? [];
        return is_array($packs) ? $packs : [];
    }

    public function pack(string $packId): ?array
    {
        $pack = $this->packs()[$packId] ?? null;
        return is_array($pack) ? $pack : null;
    }

    public function packIdParaCatalogo(string $catalogId): ?string
    {
        foreach ($this->packs() as $id => $pack) {
            if (($pack['catalog_id'] ?? null) === $catalogId) {
                return (string) $id;
            }
        }
        return null;
    }

    /** IDs declarados en ESTE pack (cantidad variable). */
    public function idsDeclarados(array $pack): array
    {
        $exp = is_array($pack['expresiones'] ?? null) ? $pack['expresiones'] : [];
        return array_values(array_filter(array_keys($exp), static fn($k) => is_string($k) && $k !== ''));
    }

    public function disponible(array $pack, string $expressionId): bool
    {
        $meta = $pack['expresiones'][$expressionId] ?? null;
        if (!is_array($meta) || empty($meta['archivo'])) {
            return false;
        }
        $packVersion = (int) ($pack['visual_identity_version'] ?? 0);
        $fileVersion = (int) ($meta['identidad_version'] ?? $packVersion);
        if ($packVersion > 0 && $fileVersion !== $packVersion) {
            return false;
        }
        return is_file($this->rutaAbsoluta($pack, (string) $meta['archivo']));
    }

    public function rutaEsperada(array $pack, string $expressionId): ?string
    {
        $meta = $pack['expresiones'][$expressionId] ?? null;
        if (!is_array($meta) || empty($meta['archivo'])) {
            return null;
        }
        $rel = rtrim((string) ($pack['carpeta'] ?? ''), '/\\') . '/' . $meta['archivo'];
        return str_replace('\\', '/', $rel);
    }

    public function asset(array $pack, string $expressionId): ?array
    {
        $rel = $this->rutaEsperada($pack, $expressionId);
        $existe = $this->disponible($pack, $expressionId);
        if ($rel === null) {
            return null;
        }
        $meta = $pack['expresiones'][$expressionId];
        return [
            'expression_id' => $expressionId,
            'archivo' => $meta['archivo'],
            'url_relativa' => $rel,
            'identidad_version' => (int) ($meta['identidad_version'] ?? $pack['visual_identity_version'] ?? 0),
            'existe' => $existe,
            'placeholder' => !$existe,
        ];
    }

    /** Caja técnica. No es una cara. */
    public function placeholderTecnico(string $expressionId): array
    {
        return [
            'expression_id' => $expressionId,
            'archivo' => null,
            'url_relativa' => null,
            'identidad_version' => 0,
            'existe' => false,
            'placeholder' => true,
            'tecnico' => true,
        ];
    }

    /**
     * Inventario QA: expresiones DECLARADAS en el pack (N variable)
     * + ids provisionales del catálogo no presentes (faltantes de cobertura, no error).
     */
    public function tira(array $pack, ?CatalogStore $catalog = null): array
    {
        $out = [];
        $vistos = [];
        foreach ($this->idsDeclarados($pack) as $id) {
            $asset = $this->asset($pack, $id);
            $out[] = [
                'expression_id' => $id,
                'existe' => $asset !== null && !empty($asset['existe']),
                'declarada' => true,
                'obligatoria' => $id === ExpresionVisual::NEUTRAL,
                'ruta_esperada' => $this->rutaEsperada($pack, $id),
                'asset' => $asset,
            ];
            $vistos[$id] = true;
        }
        foreach (ExpresionVisual::ids($catalog) as $id) {
            if (isset($vistos[$id])) {
                continue;
            }
            $out[] = [
                'expression_id' => $id,
                'existe' => false,
                'declarada' => false,
                'obligatoria' => $id === ExpresionVisual::NEUTRAL,
                'ruta_esperada' => $this->rutaEsperada($pack, $id),
                'asset' => null,
            ];
        }
        return $out;
    }

    public function resumenPack(string $packId, ?CatalogStore $catalog = null): ?array
    {
        $pack = $this->pack($packId);
        if ($pack === null) {
            return null;
        }
        $tira = $this->tira($pack, $catalog);
        $disponibles = [];
        $faltantes = [];
        foreach ($tira as $row) {
            if (!empty($row['declarada']) && !empty($row['existe'])) {
                $disponibles[] = $row['expression_id'];
            } elseif (!empty($row['declarada']) && empty($row['existe'])) {
                $faltantes[] = [
                    'expression_id' => $row['expression_id'],
                    'ruta_esperada' => $row['ruta_esperada'],
                ];
            }
        }
        return [
            'pack_id' => $packId,
            'canon' => (bool) ($pack['canon'] ?? false),
            'laboratorio' => (bool) ($pack['laboratorio'] ?? false),
            'catalog_id' => $pack['catalog_id'] ?? null,
            'nombre_visible' => $pack['nombre_visible'] ?? $packId,
            'visual_identity_version' => (int) ($pack['visual_identity_version'] ?? 0),
            'neutral_ok' => in_array(ExpresionVisual::NEUTRAL, $disponibles, true),
            'expresiones_declaradas' => $this->idsDeclarados($pack),
            'expresiones_disponibles' => $disponibles,
            'assets_faltantes' => $faltantes,
            'tira' => $tira,
        ];
    }

    public function listarResumenes(?CatalogStore $catalog = null): array
    {
        $out = [];
        foreach (array_keys($this->packs()) as $id) {
            $r = $this->resumenPack((string) $id, $catalog);
            if ($r !== null) {
                $out[] = $r;
            }
        }
        return $out;
    }

    /** @return array<string, array> */
    private function descubrirEnDisco(): array
    {
        $found = [];
        $dirs = [
            ['rel' => 'assets/personajes/aprobados', 'laboratorio' => false, 'canon' => true],
            ['rel' => 'assets/personajes/_laboratorio', 'laboratorio' => true, 'canon' => false],
        ];
        foreach ($dirs as $spec) {
            $abs = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $spec['rel']);
            if (!is_dir($abs)) {
                continue;
            }
            $entries = @scandir($abs) ?: [];
            foreach ($entries as $name) {
                if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                    continue;
                }
                $folder = $abs . DIRECTORY_SEPARATOR . $name;
                if (!is_dir($folder)) {
                    continue;
                }
                $metaPath = $folder . DIRECTORY_SEPARATOR . 'meta.json';
                if (!is_file($metaPath)) {
                    continue;
                }
                $meta = JsonFile::read($metaPath);
                $id = (string) ($meta['personaje_id'] ?? strtolower($name));
                $found[$id] = $this->packDesdeMeta($spec['rel'] . '/' . $name, $meta, $spec['laboratorio'], !$spec['canon']);
            }
        }
        return $found;
    }

    private function packDesdeMeta(string $carpetaRel, array $meta, bool $laboratorio, bool $noCanon): array
    {
        $exps = [];
        foreach ($meta['expresiones'] ?? [] as $id => $row) {
            if (!is_string($id)) {
                continue;
            }
            $archivo = is_array($row) ? ($row['archivo'] ?? null) : null;
            if (!is_string($archivo) || $archivo === '') {
                continue;
            }
            $exps[$id] = [
                'archivo' => $archivo,
                'identidad_version' => (int) ($row['identidad_version'] ?? $meta['visual_identity_version'] ?? 1),
            ];
        }
        return [
            'canon' => $noCanon ? false : (bool) ($meta['canon'] ?? false),
            'laboratorio' => $laboratorio || (bool) ($meta['laboratorio'] ?? false),
            'catalog_id' => $meta['catalog_id'] ?? null,
            'nombre_visible' => $meta['nombre_carpeta'] ?? $meta['personaje_id'] ?? $carpetaRel,
            'visual_identity_version' => (int) ($meta['visual_identity_version'] ?? 1),
            'carpeta' => $carpetaRel,
            'mapeo_estado_a_expresion' => is_array($meta['mapeo_estado_a_expresion'] ?? null)
                ? $meta['mapeo_estado_a_expresion']
                : [],
            'expresiones' => $exps,
            'estado_visual' => $meta['estado'] ?? null,
        ];
    }

    private function rutaAbsoluta(array $pack, string $archivo): string
    {
        $carpeta = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) ($pack['carpeta'] ?? ''));
        return $this->root . DIRECTORY_SEPARATOR . $carpeta . DIRECTORY_SEPARATOR . $archivo;
    }
}
