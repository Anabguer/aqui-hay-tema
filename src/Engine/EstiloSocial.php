<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Deriva ejes de estilo sin reescribir la etiqueta de ficha. */
final class EstiloSocial
{
    public static function resolver(array $vida, CatalogStore $store): array
    {
        $etiqueta = isset($vida['estilo_social']) ? (string) $vida['estilo_social'] : null;
        $ejesFicha = is_array($vida['estilo_social_ejes'] ?? null) ? $vida['estilo_social_ejes'] : null;
        $ejesCatalogo = $etiqueta ? $store->ejesEstilo($etiqueta) : null;

        $ejes = $ejesFicha ?? $ejesCatalogo;

        return [
            'etiqueta' => $etiqueta,
            'ejes' => $ejes,
            'fuente' => $ejesFicha !== null ? 'ficha' : ($ejesCatalogo !== null ? 'catalogo_etiqueta' : null),
        ];
    }
}
