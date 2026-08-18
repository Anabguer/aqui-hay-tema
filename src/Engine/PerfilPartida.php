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

    /**
     * Legacy: si no hay generación, usa hobbies/rasgos del catálogo (saves antiguos).
     *
     * @return array<string, mixed>
     */
    public static function deOLegacy(array $partida, string $residenteId, ?Catalog $catalog = null): array
    {
        $gen = self::de($partida, $residenteId);
        if ($gen !== null) {
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
