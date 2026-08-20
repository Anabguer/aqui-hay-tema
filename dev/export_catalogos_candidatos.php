<?php
declare(strict_types=1);

/**
 * Vuelca candidatos a JSON. No toca catálogos de producción.
 * Uso: php dev/export_catalogos_candidatos.php
 */
$root = dirname(__DIR__);
$dir = $root . '/data/catalogos/_candidatos_personalidad';
if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    fwrite(STDERR, "no dir\n");
    exit(1);
}

require __DIR__ . '/datos_catalogos_candidatos_helpers.php';
$meta = require __DIR__ . '/datos_catalogos_candidatos_meta.php';
$aficiones = require __DIR__ . '/datos_catalogos_candidatos_aficiones.php';
$gustos = require __DIR__ . '/datos_catalogos_candidatos_gustos.php';
$rechazos = require __DIR__ . '/datos_catalogos_candidatos_rechazos.php';
$rasgos = require __DIR__ . '/datos_catalogos_candidatos_rasgos.php';
$social = require __DIR__ . '/datos_catalogos_candidatos_social.php';
$afecto = require __DIR__ . '/datos_catalogos_candidatos_afecto.php';
$manias = require __DIR__ . '/datos_catalogos_candidatos_manias.php';

$copyFile = __DIR__ . '/datos_catalogos_candidatos_copy_v2.php';
if (is_file($copyFile)) {
    $copy = require $copyFile;
    $aplicarCopy = static function (array $items, array $pool): array {
        foreach ($items as $i => $it) {
            $id = (string) ($it['id'] ?? '');
            if ($id !== '' && isset($pool[$id]) && is_array($pool[$id]) && count($pool[$id]) >= 4) {
                $items[$i]['descubrimientos'] = array_values($pool[$id]);
            }
            $desc = is_array($items[$i]['descubrimientos'] ?? null) ? $items[$i]['descubrimientos'] : [];
            $items[$i]['canales'] = aht_reparto_canales($desc);
        }
        return $items;
    };
    $aficiones = $aplicarCopy($aficiones, $copy['aficiones'] ?? []);
    $gustos = $aplicarCopy($gustos, $copy['gustos'] ?? []);
    $rechazos = $aplicarCopy($rechazos, $copy['rechazos'] ?? []);
    $rasgos = $aplicarCopy($rasgos, $copy['rasgos'] ?? []);
}

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

$wrap = static function (string $catalogo, $items, array $extra = []) use ($flags): array {
    return array_merge([
        'meta' => [
            'catalogo' => $catalogo,
            'estado' => 'PROPUESTA_NO_ACTIVA',
            'canon' => false,
            'nota' => 'Candidato. CatalogStore de PLAY no carga esta carpeta.',
        ],
        'items' => $items,
    ], $extra);
};

file_put_contents($dir . '/00_meta.json', json_encode($meta, $flags) . "\n");
file_put_contents($dir . '/aficiones.json', json_encode($wrap('aficiones_candidatas', $aficiones, [
    'familias' => [
        'lectura', 'cine', 'juegos', 'musica', 'movimiento', 'aire_libre',
        'mesa', 'manos', 'noche', 'cuidado', 'pueblo',
    ],
]), $flags) . "\n");
file_put_contents($dir . '/gustos.json', json_encode($wrap('gustos_candidatos', $gustos), $flags) . "\n");
file_put_contents($dir . '/rechazos.json', json_encode($wrap('rechazos_candidatos', $rechazos, [
    'destinos_rechazables' => [
        'lug_cafeteria', 'lug_biblioteca', 'lug_tienda', 'lug_restaurante',
        'lug_bingo', 'lug_cine', 'lug_arcade', 'lug_bar', 'lug_discoteca',
        'lug_karaoke', 'lug_parque', 'lug_picnic', 'lug_mirador', 'lug_gimnasio', 'lug_spa',
    ],
    'nota_destinos' => 'Un rechazo tipo destino usa el id del lugar. No se duplica el edificio aquí.',
]), $flags) . "\n");
file_put_contents($dir . '/rasgos.json', json_encode($wrap('rasgos_candidatos', $rasgos, [
    'ejes' => ['social', 'humor', 'vinculo', 'ego', 'afecto', 'cognicion', 'ritmo'],
    'alias_produccion_a_conservar' => [
        'observadora' => 'observador',
        'practica' => 'practico',
        'socarrona' => 'socarron',
    ],
]), $flags) . "\n");
file_put_contents($dir . '/preferencias_sociales.json', json_encode([
    'meta' => [
        'catalogo' => 'preferencias_sociales_candidatas',
        'estado' => 'PROPUESTA_NO_ACTIVA',
        'canon' => false,
    ],
    'items' => [],
    'ejes' => $social['ejes'],
    'labels_derivados_opcionales' => $social['labels_derivados_opcionales'],
    'nota' => $social['nota'],
    'regla' => $social['regla'],
], $flags) . "\n");
file_put_contents($dir . '/afecto_estilo.json', json_encode([
    'meta' => [
        'catalogo' => 'afecto_estilo_candidato',
        'estado' => 'PROPUESTA_NO_ACTIVA',
        'canon' => false,
        'fuera_de' => ['Relacional V1', 'orientaciones', 'dealbreakers', 'ejes_preferencia'],
    ],
    'items' => [],
    'nota' => $afecto['nota'],
    'ejes' => $afecto['ejes'],
], $flags) . "\n");
file_put_contents($dir . '/manias.json', json_encode($wrap('manias_candidatas', $manias), $flags) . "\n");

$n = [
    'aficiones' => count($aficiones),
    'gustos' => count($gustos),
    'rechazos' => count($rechazos),
    'rasgos' => count($rasgos),
    'manias' => count($manias),
    'ejes_sociales' => count($social['ejes']),
    'ejes_afecto' => count($afecto['ejes']),
];
echo json_encode($n, JSON_UNESCAPED_UNICODE) . "\n";
