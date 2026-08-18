<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Advertencias de similitud. No bloquea fichas. No juzga calidad narrativa.
 */
final class DiversityAnalyzer
{
    public static function analizarFichas(array $fichas, CatalogStore $store, float $umbral = 0.55): array
    {
        $perfiles = [];
        foreach ($fichas as $ficha) {
            $id = (string) ($ficha['id'] ?? '');
            if ($id === '' || !empty($ficha['_placeholder']) || !empty($ficha['_dev_only'])) {
                continue;
            }
            $perfiles[$id] = self::perfil($ficha, $store);
        }

        $pares = [];
        $ids = array_keys($perfiles);
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = $ids[$i];
                $b = $ids[$j];
                $sim = self::similitud($perfiles[$a], $perfiles[$b]);
                $pares[] = [
                    'a' => $a,
                    'b' => $b,
                    'nombres' => [$perfiles[$a]['nombre'], $perfiles[$b]['nombre']],
                    'similitud' => $sim['total'],
                    'desglose' => $sim['desglose'],
                    'aviso' => $sim['total'] >= $umbral,
                ];
            }
        }
        usort($pares, static fn($x, $y) => $y['similitud'] <=> $x['similitud']);

        return [
            'ok' => true,
            '_nota' => 'Advertencia, no bloqueo. Umbral por defecto 0.55.',
            'umbral' => $umbral,
            'personajes' => count($perfiles),
            'pares' => $pares,
            'avisos' => array_values(array_filter($pares, static fn($p) => $p['aviso'])),
        ];
    }

    public static function desdeDirectorio(string $dir, CatalogStore $store, float $umbral = 0.55): array
    {
        $fichas = [];
        foreach (glob(rtrim($dir, '/\\') . '/per_*.json') ?: [] as $file) {
            $fichas[] = JsonFile::read($file);
        }
        return self::analizarFichas($fichas, $store, $umbral);
    }

    public static function perfil(array $ficha, CatalogStore $store): array
    {
        $vida = $ficha['vida'] ?? [];
        $rasgos = [];
        foreach (array_merge($vida['rasgos_publicos'] ?? [], $vida['rasgos_ocultos'] ?? []) as $r) {
            $rasgos[] = $store->canonId('rasgos', (string) $r);
        }
        $estilo = EstiloSocial::resolver($vida, $store);
        $voz = VozPerfil::desdeFicha($ficha);

        return [
            'id' => $ficha['id'] ?? null,
            'nombre' => $ficha['identidad']['nombre'] ?? $ficha['id'],
            'hobby' => $store->canonId('hobbies', (string) ($vida['hobby_principal'] ?? '')),
            'hobbies_sec' => array_map(
                static fn($h) => $store->canonId('hobbies', (string) $h),
                $vida['hobbies_secundarios'] ?? []
            ),
            'rasgos' => array_values(array_unique($rasgos)),
            'estilo_etiqueta' => $estilo['etiqueta'],
            'estilo_ejes' => $estilo['ejes'] ?? [],
            'voz' => $voz['registro'],
            'ocupacion' => $vida['ocupacion'] ?? null,
            'franja' => $vida['franja_disponibilidad'] ?? null,
            'look' => $ficha['visual']['etiquetas_look_base'] ?? [],
        ];
    }

    private static function similitud(array $a, array $b): array
    {
        $desglose = [
            'hobby' => $a['hobby'] !== '' && $a['hobby'] === $b['hobby'] ? 1.0 : 0.0,
            'hobbies_sec' => self::jaccard($a['hobbies_sec'], $b['hobbies_sec']),
            'rasgos' => self::jaccard($a['rasgos'], $b['rasgos']),
            'estilo' => self::estiloScore($a, $b),
            'voz' => ($a['voz'] && $a['voz'] === $b['voz']) ? 1.0 : 0.0,
            'ocupacion' => ($a['ocupacion'] && $a['ocupacion'] === $b['ocupacion']) ? 1.0 : 0.0,
            'franja' => ($a['franja'] && $a['franja'] === $b['franja']) ? 1.0 : 0.0,
            'look' => self::jaccard($a['look'], $b['look']),
        ];
        $pesos = [
            'hobby' => 0.18,
            'hobbies_sec' => 0.08,
            'rasgos' => 0.22,
            'estilo' => 0.15,
            'voz' => 0.12,
            'ocupacion' => 0.10,
            'franja' => 0.08,
            'look' => 0.07,
        ];
        $total = 0.0;
        foreach ($pesos as $k => $w) {
            $total += $w * $desglose[$k];
        }
        return ['total' => round($total, 3), 'desglose' => $desglose];
    }

    private static function estiloScore(array $a, array $b): float
    {
        if ($a['estilo_etiqueta'] && $a['estilo_etiqueta'] === $b['estilo_etiqueta']) {
            return 1.0;
        }
        $ea = $a['estilo_ejes'] ?? [];
        $eb = $b['estilo_ejes'] ?? [];
        if ($ea === [] || $eb === []) {
            return 0.0;
        }
        $keys = ['energia_social', 'selectividad', 'ritmo'];
        $eq = 0;
        foreach ($keys as $k) {
            if (($ea[$k] ?? null) !== null && ($ea[$k] ?? null) === ($eb[$k] ?? null)) {
                $eq++;
            }
        }
        return $eq / 3;
    }

    private static function jaccard(array $a, array $b): float
    {
        $a = array_values(array_unique($a));
        $b = array_values(array_unique($b));
        if ($a === [] && $b === []) {
            return 0.0;
        }
        $inter = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));
        return $union === 0 ? 0.0 : $inter / $union;
    }
}
