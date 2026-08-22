<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Hobby propio ↔ lugares jugables (Nuevo plan).
 * Fuente única: catálogo aficiones + hobbies_lugares (vía CatalogStore::hobby).
 */
final class HobbyAccionable
{
    /**
     * @return list<string>
     */
    public static function idsGenerables(CatalogStore $store): array
    {
        $out = [];
        foreach (GeneradorResidente::idsGenerables($store, 'hobbies') as $id) {
            if (self::esAccionable($id, $store)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    public static function esAccionable(string $hobbyId, CatalogStore $store): bool
    {
        return self::lugaresCanonico($hobbyId, $store) !== [];
    }

    /**
     * @return list<string>
     */
    public static function lugaresCanonico(string $hobbyId, CatalogStore $store): array
    {
        $item = $store->hobby($hobbyId);
        if (!is_array($item)) {
            return [];
        }
        $lugs = is_array($item['lugar_ids'] ?? null) ? $item['lugar_ids'] : [];
        $canon = [];
        foreach ($lugs as $lug) {
            if (!is_string($lug) || $lug === '' || $lug === 'lug_casa') {
                continue;
            }
            if (LugaresCanonicos::operativoEnProducto($lug)) {
                $canon[] = $lug;
            }
        }
        return array_values(array_unique($canon));
    }

    /**
     * Pista corta para ficha (solo hobbies descubiertos). Deriva de hobby → lugar real.
     */
    public static function pista(string $hobbyId, CatalogStore $store): ?string
    {
        $item = $store->hobby($hobbyId);
        if (!is_array($item)) {
            return null;
        }
        $lugares = self::lugaresCanonico($hobbyId, $store);
        if ($lugares === []) {
            return null;
        }
        $nombres = [];
        foreach ($lugares as $lid) {
            $n = EtiquetaFicha::lugar($lid, $store);
            if ($n !== '') {
                $nombres[] = $n;
            }
        }
        if ($nombres === []) {
            return null;
        }
        $hobbyNombre = EtiquetaFicha::hobby($hobbyId, $store);
        return self::frasePista($hobbyId, $hobbyNombre, $lugares, $nombres);
    }

    /**
     * @param list<string> $lugarIds
     * @param list<string> $nombres
     */
    private static function frasePista(string $hobbyId, string $hobbyNombre, array $lugarIds, array $nombres): string
    {
        $prep = static function (string $lid, string $nombre): string {
            if ($lid === 'lug_cafeteria') {
                return 'la Cafetería';
            }
            if (in_array($lid, ['lug_cine', 'lug_gimnasio', 'lug_bar', 'lug_bingo', 'lug_discoteca'], true)) {
                return 'el ' . $nombre;
            }
            return $nombre;
        };
        if (count($nombres) === 1) {
            $dest = $prep($lugarIds[0], $nombres[0]);
            $plantillas = [
                'leer' => 'Le gusta leer. %s puede ser un buen plan.',
                'cine' => 'Le encanta el cine. Ya sabes dónde llevarle.',
                'deporte' => 'Le gusta hacer deporte. %s puede sentarle bien.',
                'cafe_social' => 'Disfruta pasando tiempo en %s.',
                'cocina' => 'Le gusta cocinar. %s es un sitio que suele apetecerle.',
                'baile' => 'Le gusta bailar. %s encaja con su ritmo.',
                'bingo' => 'Le va el bingo. %s es su terreno.',
                'copas' => 'Le apetece tomar algo en %s.',
                'musica' => 'La música le va. %s puede animarle.',
            ];
            if (isset($plantillas[$hobbyId])) {
                return sprintf($plantillas[$hobbyId], $dest);
            }
            return sprintf('Le gusta %s. %s puede ser un buen plan.', self::lower($hobbyNombre), $dest);
        }
        $a = $prep($lugarIds[0], $nombres[0]);
        $b = $prep($lugarIds[1], $nombres[1]);
        return sprintf('Le gusta %s. %s o %s pueden encajar.', self::lower($hobbyNombre), $a, $b);
    }

    private static function lower(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    }
}
