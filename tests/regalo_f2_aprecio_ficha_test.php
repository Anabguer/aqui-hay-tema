<?php
declare(strict_types=1);

/* Regalos F2: aprecio_celeste como banda (I) + proyeccion en ficha.
   Nunca numero bruto; bandas calibradas; sin tocar relaciones entre residentes. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\AprecioCelesteVista;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\FichaPlayVista;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cal = regalo_cal();

// bandas canonicas
$v = AprecioCelesteVista::vista(70, $cal);
ok($v['banda'] === 'confianza' && $v['texto'] === 'Confía en ti.', 'aprecio 70 -> confianza');
$v = AprecioCelesteVista::vista(30, $cal);
ok($v['banda'] === 'cariño' && $v['texto'] === 'Le caes bien.', 'aprecio 30 -> cariño');
$v = AprecioCelesteVista::vista(0, $cal);
ok($v['banda'] === 'neutral', 'aprecio 0 -> neutral');
$v = AprecioCelesteVista::vista(-30, $cal);
ok($v['banda'] === 'distancia' && $v['texto'] !== '', 'aprecio -30 -> distancia');
$v = AprecioCelesteVista::vista(-80, $cal);
ok($v['banda'] === 'molestia', 'aprecio -80 -> molestia');
// frontera: -19 neutral, -20 distancia (min DESC de calibracion)
ok(AprecioCelesteVista::vista(-19, $cal)['banda'] === 'neutral', 'frontera -19 -> neutral');
ok(AprecioCelesteVista::vista(-20, $cal)['banda'] === 'distancia', 'frontera -20 -> distancia');
// sin calibracion: fallback canonico estable
ok(AprecioCelesteVista::vista(70)['banda'] === 'confianza', 'fallback sin calibracion');
// nunca expone el numero
foreach ([100, 42, 0, -7, -100] as $n) {
    $vista = AprecioCelesteVista::vista($n, $cal);
    ok(!preg_match('/\d/', $vista['texto']), "texto banda sin cifras ($n)");
}

// FichaPlayVista proyecta la banda tal cual (sin numero)
$ficha = [
    'identidad' => ['nombre' => 'Ana'],
    'discovery' => ['campos' => []],
    'descubrimientos' => [],
    'estado_emocional' => ['id' => 'neutro'],
    'trabajo' => [],
    'aprecio_celeste' => ['banda' => 'cariño', 'texto' => 'Le caes bien.'],
];
$store = new CatalogStore(dirname(__DIR__));
$vista = FichaPlayVista::de($ficha, $store);
ok(is_array($vista['aprecio_celeste']) && $vista['aprecio_celeste']['banda'] === 'cariño', 'vista_play incluye aprecio_celeste');
unset($ficha['aprecio_celeste']);
$vista2 = FichaPlayVista::de($ficha, $store);
ok($vista2['aprecio_celeste'] === null, 'sin aprecio: null (oculto), no cero inventado');

exit($failures > 0 ? 1 : 0);
