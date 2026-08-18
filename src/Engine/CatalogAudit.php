<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Compara capas históricas; la fuente activa es JSON. */
final class CatalogAudit
{
    public static function comparar(string $projectRoot): array
    {
        $store = new CatalogStore($projectRoot);
        $ficha = JsonFile::read(rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/personajes/per_i03.json');
        $hobby = $ficha['vida']['hobby_principal'] ?? null;
        $estilo = $ficha['vida']['estilo_social'] ?? null;
        $rasgos = $ficha['vida']['rasgos_publicos'] ?? [];

        return [
            'fuente_verdad' => 'data/catalogos/*.json',
            'hobbies' => $store->ids('hobbies'),
            'rasgos' => $store->ids('rasgos'),
            'estilos' => $store->ids('estilos_sociales'),
            'rocio' => [
                'hobby_principal' => $hobby,
                'estilo_social' => $estilo,
                'rasgos_publicos' => $rasgos,
                'hobby_en_catalogo' => $store->accepts('hobbies', (string) $hobby),
                'estilo_en_catalogo' => $store->accepts('estilos_sociales', (string) $estilo),
                'rasgos_en_catalogo' => array_values(array_filter(
                    $rasgos,
                    static fn($r) => $store->accepts('rasgos', (string) $r)
                )),
                'ejes_derivados' => EstiloSocial::resolver($ficha['vida'] ?? [], $store),
            ],
        ];
    }
}
