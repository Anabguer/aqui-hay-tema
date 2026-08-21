<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Vista de presentación V3. No decide presencia ni desbloqueos.
 * Agrupa PresenciaEngine + ComplejoCatalog + estado_emocional + HayTema.
 */
final class VistaPuebloV3
{
    public const MAX_VIS = 5;

    /** @var list<string> Lote técnico C3 Fase 1 (14 extremos). No son los 200. */
    private const LOTE = [
        'P001.png', 'P008.png', 'P009.png', 'P010.png', 'P016.png',
        'P018.png', 'P028.png', 'P031.png', 'P082.png', 'P109.png',
        'P117.png', 'P121.png', 'P138.png', 'P173.png',
    ];

    /** Complejos con PNG evolucionado en Fase 1. El resto se queda en temprano visual. */
    private const CRECE_VISUAL = ['cafe_libros', 'cine_game'];

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $mapa salida de PresenciaEngine::resolver
     * @return array<string, mixed>
     */
    public static function de(array $partida, array $mapa, string $root): array
    {
        $porLugar = [];
        foreach ($mapa['lugares'] ?? [] as $lug) {
            if (!is_array($lug) || empty($lug['id'])) {
                continue;
            }
            $porLugar[(string) $lug['id']] = $lug;
        }

        $abiertos = array_fill_keys(LugaresCanonicos::todos(), true);
        foreach ($partida['celeste']['lugares_desbloqueados'] ?? [] as $id) {
            if (is_string($id) && $id !== '') {
                $abiertos[$id] = true;
            }
        }

        $packs = new VisualPackStore($root);
        $tokens = [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            $tok = self::tokenDe(is_array($res) ? $res : [], $rid, $packs);
            $tokens[$rid] = [
                'url' => $tok['url'],
                'lote' => $tok['lote'],
            ];
        }

        $complejos = [];
        foreach (ComplejoCatalog::complejos() as $cid => $meta) {
            $destinosMeta = ComplejoCatalog::destinosDeComplejo($cid);
            $destinos = [];
            $core = $destinosMeta[0] ?? null;
            $tieneExpansion = false;
            foreach ($destinosMeta as $did) {
                $row = $porLugar[$did] ?? null;
                $operativo = LugaresCanonicos::operativoEnProducto($did) || isset($abiertos[$did]);
                if ($core !== null && $did !== $core && $operativo) {
                    $tieneExpansion = true;
                }
                $destinos[] = [
                    'id' => $did,
                    'nombre' => is_array($row) ? (string) ($row['nombre'] ?? $did) : $did,
                    'operativo' => $operativo,
                ];
            }

            $gente = [];
            foreach ($destinosMeta as $did) {
                $row = $porLugar[$did] ?? null;
                if (!is_array($row)) {
                    continue;
                }
                foreach ($row['residentes_presentes'] ?? [] as $p) {
                    if (!is_array($p) || empty($p['id'])) {
                        continue;
                    }
                    $rid = (string) $p['id'];
                    $res = $partida['residentes'][$rid] ?? [];
                    $emo = EstadoEmocional::canonId(
                        (string) ($res['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO)
                    );
                    if (!in_array($emo, EstadoEmocional::V1, true)) {
                        $emo = EstadoEmocional::NEUTRO;
                    }
                    $token = self::tokenDe($res, $rid, $packs);
                    $gente[] = [
                        'id' => $rid,
                        'nombre' => IdentidadPublica::nombre($partida, $rid),
                        'iniciales' => (string) ($p['iniciales'] ?? ''),
                        'destino_id' => $did,
                        'destino_nombre' => (string) ($row['nombre'] ?? $did),
                        'emocion' => $emo,
                        'hay_tema' => false,
                        'tema_id' => null,
                        'token_url' => $token['url'],
                        'token_lote' => $token['lote'],
                        'fase' => 'en_destino',
                    ];
                }
            }

            $gente = HayTema::aplicar($partida, $gente);
            $vis = self::pickVisible($gente);
            $faseMotor = $tieneExpansion ? 'pleno' : 'temprano';
            $faseVisual = (in_array($cid, self::CRECE_VISUAL, true) && $tieneExpansion)
                ? 'pleno'
                : 'temprano';
            $complejos[] = [
                'id' => $cid,
                'nombre' => (string) ($meta['nombre'] ?? $cid),
                'fase' => $faseVisual,
                'fase_motor' => $faseMotor,
                'destinos' => $destinos,
                'destinos_operativos' => array_values(array_filter($destinos, static function ($d) {
                    return !empty($d['operativo']);
                })),
                'visibles' => $vis,
                'extra' => max(0, count($gente) - count($vis)),
                'total' => count($gente),
                'personas' => $gente,
                'hay_tema' => (bool) array_filter($gente, static function ($g) {
                    return !empty($g['hay_tema']);
                }),
            ];
        }

        return [
            'max_visibles' => self::MAX_VIS,
            'complejos' => $complejos,
            'tokens' => $tokens,
            'hueco_casa' => 'PresenciaEngine no coloca residentes en vivienda; no se dibuja el trayecto.',
        ];
    }

    /**
     * @param list<array<string, mixed>> $people
     * @return list<array<string, mixed>>
     */
    public static function pickVisible(array $people): array
    {
        $vis = [];
        $used = [];
        foreach ($people as $p) {
            if (!empty($p['hay_tema']) && count($vis) < self::MAX_VIS) {
                $vis[] = $p;
                $used[$p['id']] = true;
            }
        }
        $dests = [];
        foreach ($people as $p) {
            $d = (string) ($p['destino_id'] ?? '');
            if ($d !== '' && !in_array($d, $dests, true)) {
                $dests[] = $d;
            }
        }
        foreach ($dests as $d) {
            if (count($vis) >= self::MAX_VIS) {
                break;
            }
            foreach ($people as $p) {
                if (($p['destino_id'] ?? '') === $d && empty($used[$p['id']])) {
                    $vis[] = $p;
                    $used[$p['id']] = true;
                    break;
                }
            }
        }
        foreach ($people as $p) {
            if (count($vis) >= self::MAX_VIS) {
                break;
            }
            if (empty($used[$p['id']])) {
                $vis[] = $p;
                $used[$p['id']] = true;
            }
        }
        return $vis;
    }

    /**
     * @param array<string, mixed> $residente
     * @return array{url: ?string, lote: bool}
     */
    private static function tokenDe(array $residente, string $rid, VisualPackStore $packs): array
    {
        $packId = EmotionalStateService::packIdDe($residente, $packs);
        if (is_string($packId) && $packId !== '') {
            $pack = $packs->pack($packId);
            if (is_array($pack)) {
                $asset = $packs->asset($pack, ExpresionVisual::NEUTRAL);
                if (is_array($asset) && !empty($asset['existe']) && !empty($asset['url_relativa'])) {
                    return ['url' => (string) $asset['url_relativa'], 'lote' => false];
                }
            }
        }
        $n = count(self::LOTE);
        $idx = $n > 0 ? (int) (sprintf('%u', crc32($rid)) % $n) : 0;
        $file = self::LOTE[$idx] ?? self::LOTE[0];
        return ['url' => 'assets/personajes/tokens-m/' . $file, 'lote' => true];
    }
}
