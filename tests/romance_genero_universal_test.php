<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CompatibilidadCalculator;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\RomanceElegibilidad;

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

$service = new PartidaService($root);
$catalog = new Catalog($root);
$cal = CalibracionConfig::load($root);

$seedA = 'rom-gen-a-' . bin2hex(random_bytes(4));
$seedB = 'rom-gen-b-' . bin2hex(random_bytes(4));
$pA = $service->nuevaPartida('juego_v1', $seedA);
$pB = $service->nuevaPartida('juego_v1', $seedB);

$idsA = array_keys(array_filter($pA['residentes'] ?? [], static fn($r) => is_array($r) && ($r['presencia'] ?? '') === 'residente'));
$idsB = array_keys(array_filter($pB['residentes'] ?? [], static fn($r) => is_array($r) && ($r['presencia'] ?? '') === 'residente'));
ok(count($idsA) === 3, 'juego_v1 arranca con 3 NPC');
ok($idsA !== $idsB || $seedA === $seedB, 'seeds distintas pueden dar poblaciones distintas (no assertion estricta de ids)');

foreach ($idsA as $id) {
    $ficha = $catalog->loadPersonaje((string) ($pA['residentes'][$id]['catalog_id'] ?? $id));
    $ident = $ficha['identidad'] ?? [];
    ok(!array_key_exists('atraido_por', $ident), "$id catálogo sin atraido_por");
    ok(!array_key_exists('etiqueta_orientacion_visible', $ident), "$id catálogo sin etiqueta_orientacion_visible");
}

for ($i = 0; $i < count($idsA); $i++) {
    for ($j = $i + 1; $j < count($idsA); $j++) {
        $a = (string) $idsA[$i];
        $b = (string) $idsA[$j];
        $el = RomanceElegibilidad::par($pA, $a, $b, $cal);
        $motivo = (string) ($el['motivo'] ?? '');
        if ($motivo === 'parentesco_veto' || $motivo === 'edad_limite_duro') {
            ok(true, "$a-$b veto permitido ($motivo)");
            continue;
        }
        ok(($el['ok'] ?? false) === true, "$a-$b elegible sin veto de género");
        ok($motivo !== 'orientacion' && $motivo !== 'genero', "$a-$b sin motivo orientación");
        $pa = PerfilPartida::de($pA, $a) ?? PerfilPartida::deOLegacy($pA, $a, $catalog);
        $pb = PerfilPartida::de($pA, $b) ?? PerfilPartida::deOLegacy($pA, $b, $catalog);
        $cmp = CompatibilidadCalculator::aHaciaB($pa, $pb, $cal);
        ok(($cmp['romance_elegible'] ?? false) === true, "$a->$b compat sin veto género");
    }
}

ok(($pA['meta']['seed'] ?? '') === $seedA, 'seed de partida persistida');
ok(($pA['meta']['seed'] ?? '') !== 'juego-v1', 'nueva partida no usa seed fija juego-v1 por defecto');

exit($failures > 0 ? 1 : 0);
