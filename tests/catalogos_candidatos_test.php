<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CatalogosCandidatos;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\GeneradorFichaCandidata;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorCatalogosPersonalidad;

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

CatalogosCandidatos::resetCache();
$pack = CatalogosCandidatos::cargar($root);
$af = CatalogosCandidatos::items($pack, 'aficiones');
$gu = CatalogosCandidatos::items($pack, 'gustos');
$ra = CatalogosCandidatos::items($pack, 'rasgos');
$re = CatalogosCandidatos::items($pack, 'rechazos');
$ma = CatalogosCandidatos::items($pack, 'manias');

ok(count($af) >= 35, 'aficiones suficientes (n=' . count($af) . ')');
ok(count($gu) >= 20, 'gustos suficientes (n=' . count($gu) . ')');
ok(count($ra) >= 28, 'rasgos suficientes (n=' . count($ra) . ')');
ok(count($ma) >= 60, 'manías 60–80 (n=' . count($ma) . ')');
ok(count($ma) <= 90, 'manías no hinchadas (n=' . count($ma) . ')');
ok(count($pack['social']['ejes'] ?? []) === 5, '5 ejes sociales (3 V0 + ruido + grupo)');
ok(count($pack['afecto']['ejes'] ?? []) === 3, '3 ejes de afecto_estilo');

$eti = static function (array $items, string $id): string {
    foreach ($items as $it) {
        if (($it['id'] ?? '') === $id) {
            return (string) ($it['etiqueta'] ?? '');
        }
    }
    return '';
};
ok($eti($af, 'yoga') === 'Yoga / movilidad', 'etiqueta yoga clara');
ok($eti($af, 'asociacion') === 'Asociaciones del pueblo', 'etiqueta asociacion clara');
ok($eti($af, 'moda') === 'Moda y escaparates', 'etiqueta moda clara');
ok($eti($af, 'spa') === 'Spa', 'etiqueta spa sin mimo');
ok($eti($gu, 'coleccionar_piezas') === 'Objetos de colección', 'etiqueta colección clara');
ok($eti($gu, 'grupos_grandes') === 'Grupos grandes', 'etiqueta grupos clara');
ok($eti($ra, 'timido') === 'Timidez', 'rasgo timidez invariable');
ok($eti($ra, 'orgulloso') === 'Orgullo', 'rasgo orgullo invariable');

$hayTiendaRopa = false;
$hayTienda = false;
foreach ($af as $it) {
    foreach ($it['lugar_ids'] ?? [] as $l) {
        if ($l === 'lug_tienda_ropa') {
            $hayTiendaRopa = true;
        }
        if ($l === 'lug_tienda') {
            $hayTienda = true;
        }
    }
}
ok(!$hayTiendaRopa, 'candidatos no arrastran lug_tienda_ropa');
ok($hayTienda, 'candidatos usan lug_tienda');
ok(isset(CatalogosCandidatos::DESTINOS['lug_tienda']), 'DESTINOS canónico Tienda');

$minimos = ['aficiones' => 6, 'rasgos' => 6, 'gustos' => 4, 'rechazos' => 4, 'manias' => 2];
$porCat = ['aficiones' => $af, 'rasgos' => $ra, 'gustos' => $gu, 'rechazos' => $re, 'manias' => $ma];
$corta = 0;
foreach ($porCat as $cat => $items) {
    foreach ($items as $it) {
        $n = 0;
        foreach ($it['descubrimientos'] ?? [] as $fr) {
            if (is_string($fr) && $fr !== '') {
                $n++;
            }
        }
        if ($n < $minimos[$cat]) {
            $corta++;
        }
    }
}
ok($corta === 0, 'copy por encima del mínimo V2 (cortas=' . $corta . ')');

$ban = CatalogosCandidatos::patronesGeneroProhibidos();
$hitsGenero = [];
foreach ($porCat as $items) {
    foreach ($items as $it) {
        $blob = strtolower((string) ($it['etiqueta'] ?? '') . ' ' . implode(' ', $it['descubrimientos'] ?? []));
        foreach ($ban as $p) {
            if ($p !== '' && strpos($blob, strtolower($p)) !== false) {
                $hitsGenero[] = ($it['id'] ?? '?') . ':' . $p;
            }
        }
        if (strpos((string) ($it['etiqueta'] ?? ''), '/a') !== false) {
            $hitsGenero[] = ($it['id'] ?? '?') . ':slash-a';
        }
    }
}
ok($hitsGenero === [], 'copy sin género fijo problemático (' . implode(',', $hitsGenero) . ')');

$prod = new CatalogStore($root);
ok(count($prod->items('hobbies')) < count($af), 'producción no ha absorbido el candidato');
ok($prod->accepts('hobbies', 'leer'), 'producción sigue teniendo leer');
ok(!$prod->accepts('hobbies', 'karaoke'), 'karaoke candidato no está en producción');

$lab = SimuladorCatalogosPersonalidad::ejecutar($root, 30, 'lab-catalogos-30');
ok(count($lab['muestras']) === 30, '30 muestras');
ok(($lab['muestras'][0]['id_muestra'] ?? '') === 'mues_01', 'ids mues_ no per_p');
$ids = [];
$nombres = [];
$mal33 = 0;
foreach ($lab['muestras'] as $m) {
    $ids[] = $m['id_muestra'];
    $nombres[] = $m['nombre'];
    if (!GeneradorFichaCandidata::cardinalidadOk($m)) {
        $mal33++;
    }
    ok(!preg_match('/^per_p/', (string) $m['id_muestra']), 'no usa ids P00x');
}
ok(count(array_unique($ids)) === 30, '30 ids distintos');
ok(count(array_unique($nombres)) === 30, '30 nombres distintos');
ok($mal33 === 0, 'muestra 30: 100% con 3+3');

ok(($lab['muestras'][0]['twist'] ?? '') === 'timidez + karaoke', 'twist 0 karaoke');
ok(in_array('karaoke', $lab['muestras'][0]['aficiones'], true), 'mues_01 canta karaoke');
ok(in_array('timido', $lab['muestras'][0]['rasgos'], true), 'mues_01 timidez');
ok(count($lab['muestras'][0]['aficiones']) === 3, 'mues_01 tiene 3 aficiones');
ok(count($lab['muestras'][0]['rasgos']) === 3, 'mues_01 tiene 3 rasgos');

ok(($lab['auditoria']['destinos_con_menos_de_2_afinidades'] ?? ['x']) === [], '15 destinos con ≥2 afinidades');
ok(($lab['auditoria']['avisos_clon'] ?? ['x']) === [], '30 muestras sin clones ≥0.55');
ok(($lab['auditoria']['items_sin_usos'] ?? ['x']) === [], 'ningún item sin usos');
ok(($lab['auditoria']['copy_por_debajo_del_minimo'] ?? ['x']) === [], 'auditoría copy mínimo vacía');

$p0 = $lab['muestras'][0];
ok(($p0['visible_al_llegar']['aficion'] ?? '') === ($p0['aficiones'][0] ?? 'x'), 'reveal inicial = primera afición');

$rng200 = new RngService('lab-catalogos-200');
$usadas = [];
$fail200 = 0;
for ($i = 0; $i < 200; $i++) {
    $f = GeneradorFichaCandidata::una($pack, $rng200, $usadas, ['modo' => 'produccion', 'indice' => $i]);
    if (!GeneradorFichaCandidata::cardinalidadOk($f)) {
        $fail200++;
    }
}
ok($fail200 === 0, '200 residentes: 100% con exactamente 3 aficiones + 3 rasgos');

ok(is_file($root . '/data/catalogos/_candidatos_personalidad/00_meta.json'), 'JSON candidato existe');
ok(strpos((string) file_get_contents($root . '/data/catalogos/aficiones.json'), 'karaoke') === false, 'no se contaminó aficiones.json de PLAY');

exit($failures > 0 ? 1 : 0);
