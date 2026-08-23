<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Perfil generado de UNA partida. La identidad visual del catálogo no cambia.
 * Hobbies/rasgos/preferencias son estables aquí; en otra partida pueden ser otros.
 */
final class PerfilPartida
{
    /**
     * @return array<string, mixed>|null
     */
    public static function de(array $partida, string $residenteId): ?array
    {
        $rt = $partida['residentes'][$residenteId]['runtime']['perfil_partida'] ?? null;
        return is_array($rt) ? $rt : null;
    }

    public static function edadDesdeCatalogo(array $partida, string $residenteId, Catalog $catalog): ?int
    {
        $res = $partida['residentes'][$residenteId] ?? null;
        if (!is_array($res) || !empty($res['_placeholder'])) {
            return null;
        }
        try {
            $cat = ResidenteRuntime::catalogoParaRuntime($res, $catalog);
        } catch (\Throwable $ignored) {
            return null;
        }
        return isset($cat['identidad']['edad']) ? (int) $cat['identidad']['edad'] : null;
    }

    public static function edadResuelta(array $partida, string $residenteId, ?Catalog $catalog = null): ?int
    {
        $perfil = self::de($partida, $residenteId);
        if ($perfil !== null && isset($perfil['edad']) && $perfil['edad'] !== null) {
            return (int) $perfil['edad'];
        }
        if ($catalog === null) {
            return null;
        }
        return self::edadDesdeCatalogo($partida, $residenteId, $catalog);
    }

    /**
     * Backfill determinista: si el catálogo tiene edad y el perfil de partida no, copia sin regenerar.
     */
    /**
     * Backfill determinista: saves antiguos sin lugares_preferentes no muestran tokens en el mapa.
     */
    public static function reconciliarLugaresPreferentes(array &$partida): void
    {
        $seed = (string) ($partida['meta']['seed'] ?? $partida['meta']['partida_id'] ?? '');
        $operativos = array_values(array_filter(
            LugaresCanonicos::IDS,
            static fn(string $lug): bool => LugaresCanonicos::operativoEnProducto($lug)
        ));
        if ($operativos === []) {
            return;
        }

        foreach ($partida['residentes'] ?? [] as $residenteId => $res) {
            if (!is_string($residenteId) || $residenteId === '' || !is_array($res)) {
                continue;
            }
            if (!isset($res['runtime']['perfil_partida']) || !is_array($res['runtime']['perfil_partida'])) {
                continue;
            }
            $perfil = &$partida['residentes'][$residenteId]['runtime']['perfil_partida'];
            $prefs = $perfil['lugares_preferentes'] ?? null;
            if (is_array($prefs) && $prefs !== []) {
                unset($perfil);
                continue;
            }
            $n = min(2, count($operativos));
            $base = (int) sprintf('%u', crc32($seed . '|' . $residenteId . '|lugpref'));
            $chosen = [];
            for ($i = 0; $i < $n; $i++) {
                $chosen[] = $operativos[($base + $i * 3) % count($operativos)];
            }
            $perfil['lugares_preferentes'] = array_values(array_unique($chosen));
            unset($perfil);
        }
    }

    public static function reconciliarEdades(array &$partida, Catalog $catalog): void
    {
        foreach ($partida['residentes'] ?? [] as $residenteId => $res) {
            if (!is_string($residenteId) || $residenteId === '' || !is_array($res)) {
                continue;
            }
            if (!isset($res['runtime']['perfil_partida']) || !is_array($res['runtime']['perfil_partida'])) {
                continue;
            }
            $perfil = &$partida['residentes'][$residenteId]['runtime']['perfil_partida'];
            if (isset($perfil['edad']) && $perfil['edad'] !== null) {
                continue;
            }
            $catEdad = self::edadDesdeCatalogo($partida, $residenteId, $catalog);
            if ($catEdad !== null) {
                $perfil['edad'] = $catEdad;
            }
            unset($perfil);
        }
    }

    /**
     * Legacy: si no hay generación, usa hobbies/rasgos del catálogo (saves antiguos).
     *
     * @return array<string, mixed>
     */
    public static function deOLegacy(array $partida, string $residenteId, ?Catalog $catalog = null): array
    {
        $gen = self::de($partida, $residenteId);
        if ($gen !== null) {
            if (($gen['edad'] ?? null) === null && $catalog !== null) {
                $catEdad = self::edadDesdeCatalogo($partida, $residenteId, $catalog);
                if ($catEdad !== null) {
                    $gen['edad'] = $catEdad;
                }
            }
            return $gen;
        }
        $res = $partida['residentes'][$residenteId] ?? [];
        $hobbies = [];
        $rasgos = [];
        $visual = [];
        $edad = null;
        if ($catalog !== null && empty($res['_placeholder'])) {
            try {
                $cat = ResidenteRuntime::catalogoParaRuntime($res, $catalog);
            } catch (\Throwable $ignored) {
                $cat = null;
            }
            if (is_array($cat)) {
                $hp = $cat['vida']['hobby_principal'] ?? null;
                if (is_string($hp) && $hp !== '') {
                    $hobbies[] = $hp;
                }
                foreach ($cat['vida']['hobbies_secundarios'] ?? [] as $h) {
                    if (is_string($h) && $h !== '') {
                        $hobbies[] = $h;
                    }
                }
                foreach ($cat['vida']['rasgos_publicos'] ?? [] as $r) {
                    if (is_string($r) && $r !== '') {
                        $rasgos[] = $r;
                    }
                }
                $edad = isset($cat['identidad']['edad']) ? (int) $cat['identidad']['edad'] : null;
                $visual = IndicadoresVisuales::desdeCatalogo($cat, $catalog->store());
            }
        }
        return [
            'fuente' => 'legacy_catalogo',
            'hobbies' => array_values(array_unique($hobbies)),
            'rasgos' => array_values(array_unique($rasgos)),
            'indicadores_visuales' => $visual,
            'edad' => $edad,
            'preferencias' => [
                'personalidad_pos' => [],
                'personalidad_neg' => [],
                'visual_pos' => [],
                'visual_neg' => [],
                'hobbies_pos' => [],
                'hobbies_neg' => [],
            ],
        ];
    }
}
