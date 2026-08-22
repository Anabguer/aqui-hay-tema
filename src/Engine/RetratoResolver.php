<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Resolución canónica de retrato/token de personaje.
 * estado_emocional + pack → expresión visual (ExpressionResolver). Sin fallback por posición ni lote CRC32.
 */
final class RetratoResolver
{
    /**
     * @param array<string, mixed> $residente
     * @return array{
     *   url: ?string,
     *   lote: bool,
     *   pack_id: ?string,
     *   expression_id: ?string,
     *   sin_pack: bool,
     *   asset_faltante: bool,
     *   residente_id: string
     * }
     */
    public static function resolver(
        array $residente,
        string $residenteId,
        VisualPackStore $packs,
        ?string $root = null,
        ?CatalogStore $catalogStore = null
    ): array
    {
        $base = [
            'url' => null,
            'lote' => false,
            'pack_id' => null,
            'expression_id' => null,
            'sin_pack' => true,
            'asset_faltante' => false,
            'residente_id' => $residenteId,
        ];

        $packId = EmotionalStateService::packIdDe($residente, $packs);
        if (!is_string($packId) || $packId === '') {
            return $base;
        }

        $base['sin_pack'] = false;
        $base['pack_id'] = $packId;

        $pack = $packs->pack($packId);
        if (!is_array($pack)) {
            $base['sin_pack'] = true;
            $base['pack_id'] = null;
            return $base;
        }

        if ($root !== null && $root !== '') {
            $work = $residente;
            EstadoEmocional::ensureResidente($work);
            $est = $work['runtime']['estado_emocional'];
            $expRt = is_array($work['runtime']['expresion_visual'] ?? null)
                ? $work['runtime']['expresion_visual']
                : [];
            $catalog = $catalogStore ?? new CatalogStore($root);
            $resolved = ExpressionResolver::resolver([
                'estado_emocional_id' => (string) ($est['id'] ?? EstadoEmocional::NEUTRO),
                'intensidad' => $est['intensidad'] ?? null,
                'override_dev' => $expRt['override_dev'] ?? null,
                'pack' => $pack,
                'pack_id' => $packId,
            ], $packs, $catalog);
            $base['expression_id'] = is_string($resolved['expression_id'] ?? null)
                ? $resolved['expression_id']
                : null;
            $asset = is_array($resolved['asset'] ?? null) ? $resolved['asset'] : null;
            if (
                is_array($asset)
                && !empty($asset['existe'])
                && is_string($asset['url_relativa'] ?? null)
                && $asset['url_relativa'] !== ''
            ) {
                $base['url'] = (string) $asset['url_relativa'];
                $base['asset_faltante'] = (bool) ($resolved['fallback'] ?? false);
                return $base;
            }
        }

        $asset = $packs->asset($pack, ExpresionVisual::NEUTRAL);
        if (
            is_array($asset)
            && !empty($asset['existe'])
            && is_string($asset['url_relativa'] ?? null)
            && $asset['url_relativa'] !== ''
        ) {
            $base['url'] = (string) $asset['url_relativa'];
            $base['expression_id'] = ExpresionVisual::NEUTRAL;
            return $base;
        }

        $base['asset_faltante'] = true;
        return $base;
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, array{url: ?string, lote: bool}>
     */
    public static function mapaTokensPartida(array $partida, string $root): array
    {
        $packs = new VisualPackStore($root);
        $catalog = new CatalogStore($root);
        $out = [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            $tok = self::resolver(is_array($res) ? $res : [], $rid, $packs, $root, $catalog);
            $out[$rid] = [
                'url' => $tok['url'],
                'lote' => $tok['lote'],
                'expression_id' => $tok['expression_id'],
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, array<string, mixed>>
     */
    public static function mapaCompletoPartida(array $partida, string $root, ?VisualPackStore $packs = null): array
    {
        $packs ??= new VisualPackStore($root);
        $catalog = new CatalogStore($root);
        $out = [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            $out[$rid] = self::resolver(is_array($res) ? $res : [], $rid, $packs, $root, $catalog);
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    public static function catalogIdsJugablesSinRetrato(Catalog $catalog, VisualPackStore $packs): array
    {
        $sin = [];
        foreach ($catalog->listPersonajeIdsJugables() as $id) {
            try {
                $personaje = $catalog->loadPersonaje($id);
            } catch (\Throwable $e) {
                $sin[] = $id;
                continue;
            }
            $runtime = ResidenteRuntime::crearDesdeCatalogo($personaje);
            $tok = self::resolver($runtime, $id, $packs);
            if ($tok['url'] === null) {
                $sin[] = $id;
            }
        }
        return $sin;
    }
}
