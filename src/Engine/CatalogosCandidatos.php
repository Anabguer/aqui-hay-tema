<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Catálogos candidatos de personalidad/gustos. NO los carga CatalogStore.
 * No escribe fichas P001–P200. No canoniza.
 */
final class CatalogosCandidatos
{
    public const DIR = '/data/catalogos/_candidatos_personalidad';

    public const DESTINOS = [
        'lug_cafeteria' => 'Cafetería',
        'lug_biblioteca' => 'Biblioteca',
        'lug_tienda' => 'Tienda',
        'lug_restaurante' => 'Restaurante',
        'lug_bingo' => 'Bingo',
        'lug_cine' => 'Cine',
        'lug_arcade' => 'Arcade',
        'lug_bar' => 'Bar',
        'lug_discoteca' => 'Discoteca',
        'lug_karaoke' => 'Karaoke',
        'lug_parque' => 'Parque',
        'lug_picnic' => 'Picnic',
        'lug_mirador' => 'Mirador',
        'lug_gimnasio' => 'Gimnasio',
        'lug_spa' => 'Spa',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public static function cargar(string $projectRoot): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $dir = rtrim($projectRoot, '/\\') . self::DIR;
        self::$cache = [
            'meta' => JsonFile::read($dir . '/00_meta.json'),
            'aficiones' => JsonFile::read($dir . '/aficiones.json'),
            'gustos' => JsonFile::read($dir . '/gustos.json'),
            'rechazos' => JsonFile::read($dir . '/rechazos.json'),
            'rasgos' => JsonFile::read($dir . '/rasgos.json'),
            'social' => JsonFile::read($dir . '/preferencias_sociales.json'),
            'afecto' => JsonFile::read($dir . '/afecto_estilo.json'),
            'manias' => JsonFile::read($dir . '/manias.json'),
        ];
        return self::$cache;
    }

    public static function resetCache(): void
    {
        self::$cache = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function items(array $pack, string $clave): array
    {
        $block = $pack[$clave] ?? [];
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];
        $out = [];
        foreach ($items as $it) {
            if (is_array($it) && isset($it['id'])) {
                $out[] = $it;
            }
        }
        return $out;
    }

    public static function porId(array $pack, string $clave, string $id): ?array
    {
        foreach (self::items($pack, $clave) as $it) {
            if ((string) $it['id'] === $id) {
                return $it;
            }
        }
        return null;
    }

    public static function frase(array $item, string $canal = 'libreta', int $slot = 0): string
    {
        $pool = self::frasesCanal($item, $canal);
        if ($pool === []) {
            $pool = is_array($item['descubrimientos'] ?? null) ? $item['descubrimientos'] : [];
        }
        if ($pool === []) {
            return (string) ($item['etiqueta'] ?? $item['id'] ?? '');
        }
        $i = $slot % count($pool);
        $t = $pool[$i] ?? $pool[0];
        return is_string($t) ? $t : (string) ($item['etiqueta'] ?? $item['id'] ?? '');
    }

    /**
     * Frase de un valor de eje (social o afecto). No volcar ids tipo Excel.
     */
    public static function fraseEje(array $pack, string $bloque, string $ejeId, string $valorId, int $slot = 0): string
    {
        $ejes = $pack[$bloque]['ejes'] ?? [];
        if (!is_array($ejes)) {
            return $valorId;
        }
        foreach ($ejes as $e) {
            if (!is_array($e) || (string) ($e['id'] ?? '') !== $ejeId) {
                continue;
            }
            foreach ($e['valores'] ?? [] as $v) {
                if (!is_array($v) || (string) ($v['id'] ?? '') !== $valorId) {
                    continue;
                }
                $pool = [];
                foreach ($v['descubrimientos'] ?? [] as $t) {
                    if (is_string($t) && $t !== '') {
                        $pool[] = $t;
                    }
                }
                if ($pool === []) {
                    return (string) ($v['etiqueta'] ?? $valorId);
                }
                return $pool[$slot % count($pool)];
            }
        }
        return $valorId;
    }

    /**
     * @return list<string>
     */
    public static function frasesCanal(array $item, string $canal): array
    {
        $all = [];
        foreach ($item['descubrimientos'] ?? [] as $t) {
            if (is_string($t) && $t !== '') {
                $all[] = $t;
            }
        }
        $idx = $item['canales'][$canal] ?? null;
        if (!is_array($idx) || $idx === []) {
            return $all;
        }
        $out = [];
        foreach ($idx as $i) {
            if (isset($all[(int) $i])) {
                $out[] = $all[(int) $i];
            }
        }
        return $out !== [] ? $out : $all;
    }

    /**
     * Patrones que no queremos en prosa de Celestine.
     *
     * @return list<string>
     */
    public static function patronesGeneroProhibidos(): array
    {
        return [
            'ni loca',
            'ni loco',
            'tímido/a',
            'orgulloso/a',
            'enterado/a',
            'no es fría',
            'no es fria',
            'que la vean sudar',
            'que lo vean sudar',
        ];
    }
}
