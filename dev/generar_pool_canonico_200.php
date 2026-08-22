<?php
declare(strict_types=1);

/**
 * Genera data/personajes/_pool_canonico.json y las fichas per_p009…per_p200 faltantes.
 * No sobrescribe per_p001…per_p008 ni fichas ya existentes.
 *
 * Uso: php dev/generar_pool_canonico_200.php
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EdadCanonica;
use AquiHayTema\Engine\JsonFile;
use AquiHayTema\Engine\PoolJugableCanon;
use AquiHayTema\Engine\VisualPackStore;

$root = dirname(__DIR__);
$personajesDir = $root . '/data/personajes';

$nombresMujer = [
    'Carmen', 'Lucía', 'Elena', 'Marta', 'Sara', 'Paula', 'Laura', 'Ana', 'Isabel', 'Cristina',
    'Beatriz', 'Nuria', 'Clara', 'Alba', 'Irene', 'Sofía', 'Marina', 'Raquel', 'Patricia', 'Andrea',
    'Julia', 'Noelia', 'Alicia', 'Rocío', 'Teresa', 'Pilar', 'Montse', 'Silvia', 'Victoria', 'Inés',
    'Lorena', 'Aitana', 'Carla', 'Diana', 'Emma', 'Fátima', 'Gloria', 'Helena', 'Jimena', 'Karla',
    'Lidia', 'Mónica', 'Nerea', 'Olga', 'Paloma', 'Rebeca', 'Susana', 'Tamara', 'Úrsula', 'Vera',
    'Yolanda', 'Zoe', 'Adriana', 'Berta', 'Celia', 'Dolores', 'Esther', 'Francesca', 'Gema', 'Hilda',
    'Iris', 'Jana', 'Katia', 'Leire', 'Miriam', 'Natalia', 'Oriana', 'Paz', 'Queralt', 'Rosa',
    'Sandra', 'Tania', 'Uxía', 'Valeria', 'Wendy', 'Xenia', 'Yasmin', 'Zaira', 'Ángela', 'Belén',
    'Candela', 'Desirée', 'Estela', 'Fiona', 'Gisela', 'Hortensia', 'India', 'Julieta', 'Kiara', 'Luna',
];
$nombresHombre = [
    'José', 'Raúl', 'Álex', 'Dani', 'Marcos', 'Iván', 'Pablo', 'Hugo', 'Sergio', 'Miguel',
    'Carlos', 'David', 'Javier', 'Antonio', 'Manuel', 'Francisco', 'Luis', 'Pedro', 'Diego', 'Álvaro',
    'Adrián', 'Alberto', 'Andrés', 'Bruno', 'César', 'Daniel', 'Eduardo', 'Fernando', 'Gabriel', 'Guillermo',
    'Héctor', 'Ignacio', 'Jorge', 'Leo', 'Mateo', 'Nicolás', 'Óscar', 'Ricardo', 'Rubén', 'Tomás',
    'Víctor', 'Xavier', 'Yago', 'Zacarías', 'Ángel', 'Borja', 'Cristian', 'Emilio', 'Félix', 'Gonzalo',
    'Hugo', 'Ismael', 'Jaime', 'Kevin', 'Lorenzo', 'Mario', 'Nacho', 'Omar', 'Paco', 'Quique',
    'Ramón', 'Samuel', 'Teo', 'Unai', 'Vicente', 'Walid', 'Yeray', 'Zeno', 'Arturo', 'Benito',
    'Camilo', 'Domingo', 'Enrique', 'Fermín', 'Germán', 'Hipólito', 'Iker', 'Jon', 'Koldo', 'Luis',
    'Marc', 'Nico', 'Oriol', 'Pol', 'Quique', 'Rafa', 'Saúl', 'Toni', 'Ulises', 'Víctor',
];
$nombresNb = [
    'Alex', 'Noa', 'Mar', 'Cris', 'Sam', 'Río', 'Sol', 'Len', 'Nico', 'Ari',
    'Dani', 'Eli', 'Jade', 'Kai', 'Luz', 'Mika', 'Nyx', 'Ori', 'Paz', 'Quinn',
];

$packs = (new VisualPackStore($root))->packs();
$manifestPersonajes = [];
$ids = [];
$creadas = 0;
$omitidas = 0;

for ($i = 1; $i <= PoolJugableCanon::TOTAL; $i++) {
    $id = PoolJugableCanon::idDesdeIndice($i);
    $packId = PoolJugableCanon::packIdDesdeCatalogId($id) ?? ('P' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
    $ids[] = $id;

    $fichaPath = $personajesDir . '/' . $id . '.json';
    $nombreExistente = null;
    $generoExistente = null;
    if (is_file($fichaPath)) {
        $existing = JsonFile::read($fichaPath);
        $nombreExistente = $existing['identidad']['nombre'] ?? null;
        $generoExistente = $existing['identidad']['genero'] ?? null;
        $omitidas++;
    }

    $pack = $packs[$packId] ?? null;
    $metaGender = null;
    if (is_array($pack)) {
        $folder = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) ($pack['carpeta'] ?? ''));
        $metaPath = $folder . DIRECTORY_SEPARATOR . $packId . '_meta.json';
        if (!is_file($metaPath)) {
            $metaPath = $folder . DIRECTORY_SEPARATOR . 'meta.json';
        }
        if (is_file($metaPath)) {
            $meta = JsonFile::read($metaPath);
            $metaGender = $meta['visual_gender'] ?? null;
        }
    }

    $genero = match ($generoExistente ?? $metaGender) {
        'mujer', 'hombre', 'no_binarie' => (string) ($generoExistente ?? $metaGender),
        'femenino' => 'mujer',
        'masculino' => 'hombre',
        default => ($i % 3 === 0) ? 'no_binarie' : (($i % 2 === 0) ? 'mujer' : 'hombre'),
    };

    if ($nombreExistente !== null && is_string($nombreExistente) && $nombreExistente !== '') {
        $nombre = $nombreExistente;
    } else {
        $poolNombres = match ($genero) {
            'mujer' => $nombresMujer,
            'no_binarie' => $nombresNb,
            default => $nombresHombre,
        };
        $nombre = $poolNombres[($i - 1) % count($poolNombres)];
    }

    $manifestPersonajes[] = [
        'id' => $id,
        'pack_id' => $packId,
        'nombre' => $nombre,
        'genero' => $genero,
    ];

    if (is_file($fichaPath)) {
        continue;
    }

    $edad = EdadCanonica::desdePackMeta($root, $packId);
    $identidad = [
        'nombre' => $nombre,
        'genero' => $genero,
        'apertura_descubrimiento' => 'permeable',
    ];
    if ($edad !== null) {
        $identidad['edad'] = $edad;
    }

    $ficha = [
        'id' => $id,
        'piloto' => false,
        'identidad' => $identidad,
        'vida' => new stdClass(),
        'visual' => [
            'pack_id' => $packId,
            'estilo_visual' => 'AHT_PERSONAJES_V1',
        ],
    ];
  JsonFile::write($fichaPath, $ficha);
    $creadas++;
}

$manifest = [
    'version' => 1,
    'total' => PoolJugableCanon::TOTAL,
    'capacidad_simultanea' => 46,
    'nota' => 'Pool jugable canónico. capacidad_simultanea ≠ total del catálogo.',
    'ids' => $ids,
    'personajes' => $manifestPersonajes,
];
$manifestPath = $personajesDir . '/_pool_canonico.json';
if (is_file($manifestPath)) {
    $prev = JsonFile::read($manifestPath);
    if (is_array($prev['excluidos_seleccion'] ?? null)) {
        $manifest['excluidos_seleccion'] = $prev['excluidos_seleccion'];
    }
}
JsonFile::write($manifestPath, $manifest);

echo "pool_canonico_200: creadas=$creadas omitidas=$omitidas manifest=" . count($ids) . " ids\n";
