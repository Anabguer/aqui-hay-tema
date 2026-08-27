<?php
declare(strict_types=1);

/*
 * Test: Bancos de copy espontaneos (F1, F2, F6, F7, F9, F14, F15).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoVoz;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $failures++; }
}

DomainBootstrap::boot();
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'voz-espontaneos-' . time());

// Verificar que todas las familias nuevas están soportadas
$fams = MensajitoVoz::familias();
ok(in_array('f_opinion', $fams, true), 'f_opinion soportada');
ok(in_array('f_dilema', $fams, true), 'f_dilema soportada');
ok(in_array('f_confidencia', $fams, true), 'f_confidencia soportada');
ok(in_array('f_alerta_vecinal', $fams, true), 'f_alerta_vecinal soportada');
ok(in_array('seguimiento_consejo', $fams, true), 'seguimiento_consejo soportada');
ok(in_array('f_promesa', $fams, true), 'f_promesa soportada');
ok(in_array('f_curiosidad_celestine', $fams, true), 'f_curiosidad_celestine soportada');

// F1: Opinión
$p['residentes']['per_p001']['identidad_publica']['nombre'] = 'Ana';
$l1 = MensajitoVoz::linea($p, 'f_opinion', ['otro' => 'Carlos', 'texto' => 'amigo'], 'test_f1', 'per_p001');
ok($l1 !== '' && strlen($l1) > 5, 'f_opinion: genera texto');
ok(strpos($l1, 'Carlos') !== false, 'f_opinion: contiene nombre del otro');

// F2: Dilema
$l2 = MensajitoVoz::linea($p, 'f_dilema', ['nombre_a' => 'Jaime', 'nombre_b' => 'Pedro', 'texto' => 'dos'], 'test_f2', 'per_p001');
ok($l2 !== '' && strlen($l2) > 5, 'f_dilema: genera texto');
ok(strpos($l2, 'Jaime') !== false || strpos($l2, 'Pedro') !== false, 'f_dilema: contiene un nombre');

// F6: Confidencia
$l6 = MensajitoVoz::linea($p, 'f_confidencia', ['texto' => 'preocupado'], 'test_f6', 'per_p001');
ok($l6 !== '' && strlen($l6) > 5, 'f_confidencia: genera texto');

// F7: Alerta vecinal
$l7 = MensajitoVoz::linea($p, 'f_alerta_vecinal', ['otro' => 'Laura', 'texto' => 'apagado'], 'test_f7', 'per_p001');
ok($l7 !== '' && strlen($l7) > 5, 'f_alerta_vecinal: genera texto');
ok(strpos($l7, 'Laura') !== false, 'f_alerta_vecinal: contiene nombre');

// F9: Seguimiento consejo
$l9 = MensajitoVoz::linea($p, 'seguimiento_consejo', ['consejo_id' => 'c1', 'texto' => 'funcionó'], 'test_f9', 'per_p001');
ok($l9 !== '' && strlen($l9) > 5, 'seguimiento_consejo: genera texto');

// F14: Promesa
$l14 = MensajitoVoz::linea($p, 'f_promesa', [], 'test_f14', 'per_p001');
ok($l14 !== '' && strlen($l14) > 5, 'f_promesa: genera texto');

// F15: Curiosidad por Celestine
$l15 = MensajitoVoz::linea($p, 'f_curiosidad_celestine', [], 'test_f15', 'per_p001');
ok($l15 !== '' && strlen($l15) > 5, 'f_curiosidad_celestine: genera texto');

// Familia desconocida: no rompe
$lUnknown = MensajitoVoz::linea($p, 'f_inexistente', [], 'test_unk', null);
ok($lUnknown === '', 'familia desconocida: retorna vacío');

echo "\n";
echo $failures === 0 ? "OK mensajitos_voz_espontaneos\n" : "FAIL mensajitos_voz_espontaneos ({$failures})\n";
exit($failures > 0 ? 1 : 0);