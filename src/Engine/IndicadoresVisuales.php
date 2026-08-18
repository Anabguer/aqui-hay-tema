<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Extrae indicadores visuales de una identidad de catálogo.
 * No inventa etiquetas que la imagen no soporte: solo coincidencias de texto/ids conocidos.
 */
final class IndicadoresVisuales
{
    /** @var array<string, string> */
    private const FRASES = [
        'gafas' => 'gafas',
        'bigote' => 'bigote',
        'perilla' => 'perilla',
        'barba' => 'barba',
        'canas' => 'canas',
        'tatuaje' => 'tatuajes',
        'auricular' => 'auriculares',
        'fade' => 'fade',
        'rapado' => 'fade',
    ];

    /**
     * @return list<string>
     */
    public static function desdeCatalogo(array $catalogo, CatalogStore $store): array
    {
        $ids = [];
        $visual = is_array($catalogo['visual'] ?? null) ? $catalogo['visual'] : [];
        $blob = strtolower((string) ($visual['estilo_visual'] ?? ''));
        foreach ($visual['rasgos_fisicos'] ?? [] as $r) {
            if (is_string($r)) {
                $blob .= ' ' . strtolower($r);
            }
        }
        foreach (self::FRASES as $needle => $id) {
            if ($needle !== '' && strpos($blob, $needle) !== false) {
                $ids[] = $id;
            }
        }
        if (strpos($blob, 'pelo largo') !== false || strpos($blob, 'melena') !== false) {
            $ids[] = 'pelo_largo';
        }
        if (strpos($blob, 'pelo corto') !== false || strpos($blob, 'corto y') !== false) {
            $ids[] = 'pelo_corto';
        }
        if (strpos($blob, 'teñid') !== false || strpos($blob, 'pelo de color') !== false) {
            $ids[] = 'pelo_color';
        }
        foreach ($visual['etiquetas_look_base'] ?? [] as $et) {
            $et = (string) $et;
            if ($store->accepts('indicadores_visuales', $et) || $store->accepts('etiquetas_look', $et)) {
                $ids[] = $et;
            }
        }
        $canon = [];
        foreach (array_unique($ids) as $id) {
            if ($store->accepts('indicadores_visuales', $id) || $store->accepts('etiquetas_look', $id)) {
                $canon[] = $store->accepts('indicadores_visuales', $id)
                    ? $store->canonId('indicadores_visuales', $id)
                    : $id;
            }
        }
        return array_values(array_unique($canon));
    }
}
