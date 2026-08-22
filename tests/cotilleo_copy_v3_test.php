<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CotilleoCategoria;
use AquiHayTema\Engine\CotilleoLlegadaCopy;
use AquiHayTema\Engine\CopyDescubrimiento;
use AquiHayTema\Engine\EncuentroCotilleoCopy;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\VistaCotilleoV3;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

// A. Llegada sin texto técnico
$llegada = CotilleoLlegadaCopy::texto('Benito', 'per_benito');
ok(!str_contains($llegada, 'población'), 'llegada sin población');
ok(!str_contains($llegada, '3+5'), 'llegada sin 3+5');
ok(!str_contains($llegada, 'incorporación'), 'llegada sin incorporación técnica');
ok(str_contains($llegada, 'Benito'), 'llegada menciona nombre');

// B/C. Encuentro breve con descubrimiento acotado
$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'cotilleo-copy-v3');
$partida['features']['buzon_enabled'] = true;
$partida['features']['discovery_enabled'] = true;
$ida = 'per_p001';
$idb = 'per_p002';
$enc = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa cita');
$dia = (int) $enc['encuentro']['dia'];
$hora = (int) $enc['encuentro']['hora'];
$now = ((int) $partida['reloj']['dia_pueblo']) * 24 + (int) $partida['reloj']['hora_actual'];
$dur = max(1, (int) ($enc['encuentro']['duracion_horas'] ?? 1));
$adv = $service->avanzarRelojPasoAPaso($partida, max(1, $dia * 24 + $hora + $dur - $now));
ok($adv['ok'] ?? false, 'cita termina');

$cotilleos = array_values(array_filter($partida['buzon'] ?? [], static fn($m) => is_array($m) && ($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO));
ok($cotilleos !== [], 'hay cotilleo de cita');
$textoCita = (string) ($cotilleos[count($cotilleos) - 1]['texto'] ?? '');
ok(str_contains($textoCita, 'Cafetería'), 'copy menciona cafetería');
ok(!str_contains($textoCita, 'Anota, anota'), 'sin muletilla de informe');
ok(strlen($textoCita) < 220, 'copy de cita no es un párrafo interminable');
ok(substr_count($textoCita, '.') <= 3, 'como máximo unas pocas frases');

$meta = $cotilleos[count($cotilleos) - 1]['cotilleo_meta']['categoria'] ?? '';
ok(in_array($meta, [
    CotilleoCategoria::ENCUENTRO,
    CotilleoCategoria::DESCUBRIMIENTO,
    CotilleoCategoria::RELACION,
    CotilleoCategoria::ROMANCE,
    CotilleoCategoria::DRAMA,
], true), 'categoría de encuentro válida');

echo "\n--- Cita ---\n{$textoCita}\n";

// D. Categoría romance desde tipo
$rom = CotilleoCategoria::de(['tipo' => 'senal_romantica']);
ok($rom['id'] === CotilleoCategoria::ROMANCE && $rom['destacado'] === true, 'señal romántica → romance destacado');
$drama = CotilleoCategoria::de(['tipo' => 'discusion']);
ok($drama['id'] === CotilleoCategoria::DRAMA, 'discusión → drama');

// E/F. Orden y lateral (ultimo)
$ptOrden = $service->nuevaPartida('playtest_01', 'cotilleo-orden-v3');
$ptOrden['reloj']['dia_pueblo'] = 3;
$ptOrden['buzon'] = [
    ['id' => 'c1', 'clasificacion' => BuzonEngine::COTILLEO, 'texto' => 'A antiguo', 'dia' => 3, 'estado' => 'leido', 'tipo' => 'cotilleo'],
    ['id' => 'c2', 'clasificacion' => BuzonEngine::COTILLEO, 'texto' => 'B medio', 'dia' => 3, 'estado' => 'leido', 'tipo' => 'cotilleo'],
    ['id' => 'c3', 'clasificacion' => BuzonEngine::COTILLEO, 'texto' => 'C reciente', 'dia' => 3, 'estado' => 'pendiente', 'tipo' => 'llegada_pueblo', 'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::PUEBLO, true)],
];
$cotiOrden = VistaCotilleoV3::de($ptOrden);
ok(($cotiOrden['hoy'][0]['texto'] ?? '') === 'C reciente', 'orden hoy: más reciente arriba (C)');
ok(($cotiOrden['hoy'][1]['texto'] ?? '') === 'B medio', 'orden hoy: medio (B)');
ok(($cotiOrden['hoy'][2]['texto'] ?? '') === 'A antiguo', 'orden hoy: más antiguo abajo (A)');
ok(($cotiOrden['ultimo']['texto'] ?? '') === 'C reciente', 'ultimo = C para lateral');
ok(($cotiOrden['hoy'][0]['categoria'] ?? '') === CotilleoCategoria::PUEBLO, 'categoría pueblo en vista');

// Copy descubrimiento breve
$store = $service->getCatalog()->store();
$pista = CopyDescubrimiento::textoCotilleo('Diana', 'hobby:dep_deporte', 'dep_deporte', $store);
ok(is_string($pista) && str_contains($pista, 'Diana') && !str_contains($pista, 'Has descubierto'), 'pista cotilleo breve');

// Sintaxis compilar
$comp = EncuentroCotilleoCopy::compilar($partida, $partida['encuentros'][0], $partida['encuentros'][0]['resultado'] ?? [], $service->getCatalog(), $root);
ok(is_array($comp) && ($comp['texto'] ?? '') !== '', 'EncuentroCotilleoCopy::compilar devuelve texto');

exit($failures > 0 ? 1 : 0);
