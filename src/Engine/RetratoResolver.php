<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Resolución canónica de retrato/token de personaje.
 * Un personaje → un pack visual → un asset neutral. Sin fallback por posición ni lote CRC32.
 */
final class RetratoResolver
{
    /**
     * @param array<string, mixed> $residente
     * @return array{
     *   url: ?string,
     *   lote: bool,
     *   pack_id: ?string,
     *   sin_pack: bool,
     *   asset_faltante: bool,
     *   residente_id: string
     * }
     */
    public static function resolver(array $residente, string $residenteId, VisualPackStore $packs): array
    {
        $base = [
            'url' => null,
            'lote' => false,
            'pack_id' => null,
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

        $asset = $packs->asset($pack, ExpresionVisual::NEUTRAL);
        if (
            is_array($asset)
            && !empty($asset['existe'])
            && is_string($asset['url_relativa'] ?? null)
            && $asset['url_relativa'] !== ''
        ) {
            $base['url'] = (string) $asset['url_relativa'];
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
        $out = [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            $tok = self::resolver(is_array($res) ? $res : [], $rid, $packs);
            $out[$rid] = [
                'url' => $tok['url'],
                'lote' => $tok['lote'],
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, array<string, mixed>>
     */
    public static function mapaCompletoPartida(array $partida, string $root): array
    {
        $packs = new VisualPackStore($root);
        $out = [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            $out[$rid] = self::resolver(is_array($res) ? $res : [], $rid, $packs);
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
