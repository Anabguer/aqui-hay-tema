<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CotilleoCategoria;
use AquiHayTema\Engine\CotilleoNarrativo;
use AquiHayTema\Engine\EncuentroCotilleoCopy;
use AquiHayTema\Engine\HayTema;
use AquiHayTema\Engine\HayTemaVista;
use AquiHayTema\Engine\RelacionEngine;

$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$partida = [
    'reloj' => ['dia_pueblo' => 6, 'hora_actual' => 20],
    'buzon' => [],
    'residentes' => [
        'per_edu' => ['identidad_publica' => ['nombre' => 'Eduardo']],
        'per_ben' => ['identidad_publica' => ['nombre' => 'Benito']],
    ],
    'historial_coincidencias' => [],
];

foreach ([3, 4, 5, 6] as $d) {
    $partida['historial_coincidencias'][] = [
        'dia' => $d,
        'hora' => 20,
        'lugar_id' => 'lug_bar',
        'residentes' => ['per_edu', 'per_ben'],
    ];
}

// Eduardo/Benito: desconocidos + patrón de mera co-presencia
ok(!RelacionEngine::seConocen($partida, 'per_edu', 'per_ben'), 'Eduardo/Benito no se conocen');

$patron = CotilleoNarrativo::vistaPatron($partida, ['per_edu', 'per_ben'], 'lug_bar');
ok(!str_contains((string) $patron['texto'], 'Algo se cuece'), 'patrón desconocidos: sin implicar relación');
ok(!str_contains((string) $patron['texto'], 'población'), 'patrón sin datos técnicos');
ok(!str_contains((string) $patron['texto'], 'consta'), 'patrón sin tono de auditoría');
ok(str_contains((string) $patron['texto'], 'en el Bar') || str_contains((string) $patron['texto'], 'en el bar'), 'gramática: en el lugar');
ok(
    str_contains((string) $patron['texto'], 'casualidad')
    || str_contains((string) $patron['texto'], 'sospech')
    || str_contains((string) $patron['texto'], 'Curioso'),
    'patrón con tono de cotilleo'
);
ok(($patron['categoria'] ?? '') === CotilleoCategoria::COINCIDENCIAS, 'categoría patrón = coincidencias');
ok(!str_contains((string) $patron['pista'], 'consta'), 'pista sin tono técnico');

$tema = HayTema::de($partida, 'per_edu', 'lug_bar', ['per_edu', 'per_ben']);
ok($tema['hay_tema'] === true, 'hay_tema en bar con ambos');
ok(is_array($tema['tema_vista'] ?? null), 'expone tema_vista');
ok(($tema['tema_vista']['tipo'] ?? '') === 'cotilleo_patron', 'tema_vista tipo patrón');
ok(($tema['tema_vista']['se_conocen'] ?? true) === false, 'tema_vista refleja desconocidos');

$vistaId = HayTemaVista::resolver($partida, (string) $tema['tema_id']);
ok(is_array($vistaId) && ($vistaId['texto'] ?? '') === ($patron['texto'] ?? ''), 'tema_id resuelve al mismo hecho');

// Señal romántica vs patrón
$partidaRom = $partida;
$partidaRom['buzon'][] = [
    'id' => 'msg_rom',
    'dia' => 6,
    'clasificacion' => 'cotilleo',
    'canal' => 'cotilleo',
    'tipo' => 'senal_romantica',
    'texto' => 'Eduardo lleva demasiado rato pendiente de Benito.',
    'actores' => ['per_edu', 'per_ben'],
    'cotilleo_meta' => ['categoria' => CotilleoCategoria::ROMANCE, 'destacado' => true],
];
$rom = HayTemaVista::resolver($partidaRom, 'msg_rom');
ok(($rom['categoria'] ?? '') === CotilleoCategoria::ROMANCE, 'señal romántica categoría romance');
ok(str_contains((string) ($rom['pista'] ?? ''), 'queden'), 'pista romance distinta');

$temaRomCine = HayTema::de($partidaRom, 'per_ben', 'lug_cine', ['per_ben', 'per_edu']);
ok($temaRomCine['hay_tema'] === false, 'señal romántica sin lugar no marca Aquí hay tema en el cine');
$partidaRomBar = $partidaRom;
$partidaRomBar['buzon'][] = [
    'id' => 'msg_rom_bar',
    'dia' => 6,
    'clasificacion' => 'cotilleo',
    'canal' => 'cotilleo',
    'tipo' => 'senal_romantica',
    'texto' => 'Benito lleva un rato demasiado pendiente de Diana. Aquí hay tema.',
    'actores' => ['per_ben', 'per_dia'],
    'lugar_id' => 'lug_bar',
    'cotilleo_meta' => ['categoria' => CotilleoCategoria::ROMANCE, 'destacado' => true],
];
$temaRomBar = HayTema::de($partidaRomBar, 'per_ben', 'lug_bar', ['per_ben']);
ok($temaRomBar['hay_tema'] === true, 'señal romántica con lugar sí marca en ese sitio');

$partidaDrama = $partida;
$partidaDrama['buzon'][] = [
    'id' => 'msg_drama',
    'dia' => 6,
    'clasificacion' => 'cotilleo',
    'canal' => 'cotilleo',
    'tipo' => 'discusion',
    'texto' => 'Eduardo y Benito se han enfadado.',
    'actores' => ['per_edu', 'per_ben'],
];
$drama = HayTemaVista::resolver($partidaDrama, 'msg_drama');
ok(($drama['categoria'] ?? '') === CotilleoCategoria::DRAMA, 'discusión categoría drama');
ok(($drama['categoria'] ?? '') !== CotilleoCategoria::ROMANCE, 'drama no es romance');

$partidaCon = $partida;
RelacionEngine::upsertSocial($partidaCon, 'per_edu', 'per_ben', 'conocidos', 2, true);
$con = CotilleoNarrativo::vistaPatron($partidaCon, ['per_edu', 'per_ben'], 'lug_bar');
ok(($con['categoria'] ?? '') === CotilleoCategoria::RELACION, 'conocidos → categoría relación');
ok(($con['se_conocen'] ?? false) === true, 'vista refleja conocidos');

ok(EncuentroCotilleoCopy::prepLugarEstancia('lug_cine', 'Cine') === 'en el Cine', 'estancia: en el Cine');
ok(EncuentroCotilleoCopy::prepLugarPublico('lug_cine', 'Cine') === 'al Cine', 'movimiento: al Cine');

echo "\n--- Patrón Eduardo/Benito ---\n" . ($patron['texto'] ?? '') . "\nPista: " . ($patron['pista'] ?? '') . "\n";

exit($failures > 0 ? 1 : 0);
