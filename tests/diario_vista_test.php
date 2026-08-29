<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioHitoEngine;
use AquiHayTema\Engine\DiarioVista;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;

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

DomainBootstrap::boot();
$service = new PartidaService($root);

// --- 1) Entrada con texto pero sin titulo → titulo + explicacion ---
$p1 = $service->nuevaPartida('juego_v1', 'diario-vista-texto');
$ids = array_keys($p1['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
DiarioEngine::crear($p1, [
    'tipo' => 'cotilleo',
    'texto' => 'Hugo y Walid pasaron un rato juntos. La cosa fue normal.',
    'actores' => [$a, $b],
    'origen' => [
        'evento_id' => 'enc_test_vista_1',
        'tipo_evento' => 'encuentro_terminado',
        '_placeholder' => false,
    ],
    '_placeholder_contenido' => false,
]);
$v1 = DiarioVista::listarParaResidente($p1, $a);
ok(count($v1) === 1, '1. una entrada visible');
ok(trim((string) ($v1[0]['titulo'] ?? '')) !== '', '1. titulo derivado');
ok(str_contains((string) ($v1[0]['explicacion'] ?? ''), 'pasaron un rato'), '1. explicacion con texto');
ok(($v1[0]['filtro_grupo'] ?? '') === 'planes', '1. filtro planes para encuentro');

// --- 2) Hito relacional: preferir diario_hito y ocultar espejo cotilleo ---
$p2 = $service->nuevaPartida('juego_v1', 'diario-vista-dedup');
$p2['features']['buzon_enabled'] = true;
$xa = (string) array_key_first($p2['residentes']);
$xb = null;
foreach (array_keys($p2['residentes']) as $rid) {
    if ($rid !== $xa) {
        $xb = (string) $rid;
        break;
    }
}
RelacionBitacora::registrar($p2, RelacionBitacora::SE_CONOCIERON, [$xa, $xb]);
$v2 = DiarioVista::listarParaResidente($p2, $xa);
$titulos = array_map(static fn($e) => (string) ($e['titulo'] ?? ''), $v2);
ok(count($v2) === 1, '2. sin duplicar cotilleo + diario_hito');
ok(in_array('Primer contacto', $titulos, true), '2. conserva hito propio');

// --- 3) Placeholder omitido ---
$p3 = $service->nuevaPartida('juego_v1', 'diario-vista-ph');
DiarioEngine::crear($p3, [
    'tipo' => 'ruido',
    'texto' => '',
    'actores' => [$xa],
    'origen' => ['evento_id' => 'ph_vista', 'tipo_evento' => 'test'],
    '_placeholder_contenido' => true,
]);
ok(count(DiarioVista::listarParaResidente($p3, $xa)) === 0, '3. omite placeholder');

// --- 4) Personas resueltas ---
$p4 = $p1;
$personas = $v1[0]['personas'] ?? [];
ok(count($personas) === 1, '4. una persona implicada');
ok(trim((string) ($personas[0]['nombre'] ?? '')) !== '', '4. nombre resuelto');
ok(($personas[0]['id'] ?? '') === $b, '4. id del otro actor');

// --- 5) diario_hito con titulo y tono ---
$p5 = $service->nuevaPartida('juego_v1', 'diario-vista-tono');
RelacionBitacora::registrar($p5, RelacionBitacora::RECHAZO_IMPORTANTE, [$xa, $xb], $xa . '>' . $xb);
$v5 = DiarioVista::listarParaResidente($p5, $xa);
ok(($v5[0]['tono'] ?? '') === 'negativo', '5. tono negativo en rechazo');
ok(($v5[0]['filtro_grupo'] ?? '') === 'relaciones', '5. filtro relaciones');

echo $failures === 0 ? "OK diario_vista\n" : "FAIL diario_vista ({$failures})\n";
exit($failures > 0 ? 1 : 0);
