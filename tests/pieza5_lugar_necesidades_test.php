<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\NecesidadEstado;
use AquiHayTema\Engine\JsonFile;

$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$root = dirname(__DIR__);
$json = JsonFile::read($root . '/data/lugares/lugares.json');

// Test 1: perfilLugar returns correct data for canonical place
$gimnasio = null;
foreach ($json['items'] as $lug) {
    if (($lug['id'] ?? '') === 'lug_gimnasio') {
        $gimnasio = $lug;
        break;
    }
}
$perfil = NecesidadEstado::perfilLugar($gimnasio);
ok($perfil !== null, 'perfilLugar returns data for gimnasio');
ok($perfil['principal'] === 'actividad', 'Gimnasio: principal = actividad');
ok($perfil['secundaria'] === 'diversion', 'Gimnasio: secundaria = diversion');

// Test 2: perfilLugar returns null for non-canonical place
$arcade = null;
foreach ($json['items'] as $lug) {
    if (($lug['id'] ?? '') === 'lug_arcade') {
        $arcade = $lug;
        break;
    }
}
$perfilArcade = NecesidadEstado::perfilLugar($arcade);
ok($perfilArcade === null, 'perfilLugar returns null for arcade');

// Test 3: copyLugar returns structured data
$copy = NecesidadEstado::copyLugar($gimnasio);
ok(isset($copy['principal']), 'copyLugar has principal');
ok($copy['principal']['id'] === 'actividad', 'copyLugar principal id = actividad');
ok($copy['principal']['icono'] !== '', 'copyLugar principal has icono');
ok($copy['principal']['nivel'] === 'principal', 'copyLugar principal nivel = principal');
ok(isset($copy['secundaria']), 'copyLugar has secundaria');
ok($copy['secundaria']['id'] === 'diversion', 'copyLugar secundaria id = diversion');

// Test 4: All canonical places have perfil
$canonicos = ['lug_biblioteca', 'lug_cafeteria', 'lug_parque', 'lug_cine', 'lug_restaurante', 'lug_bar', 'lug_discoteca', 'lug_bingo', 'lug_gimnasio'];
foreach ($canonicos as $cid) {
    $lug = null;
    foreach ($json['items'] as $l) {
        if (($l['id'] ?? '') === $cid) {
            $lug = $l;
            break;
        }
    }
    $p = NecesidadEstado::perfilLugar($lug);
    ok($p !== null, "{$cid} tiene perfil de necesidades");
}

// Test 5: Biblioteca has calma as principal
$biblioteca = null;
foreach ($json['items'] as $lug) {
    if (($lug['id'] ?? '') === 'lug_biblioteca') {
        $biblioteca = $lug;
        break;
    }
}
$perfilBib = NecesidadEstado::perfilLugar($biblioteca);
ok($perfilBib['principal'] === 'calma', 'Biblioteca: principal = calma');
ok($perfilBib['secundaria'] === '', 'Biblioteca: sin secundaria');

echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "{$failures} TESTS FAILED") . "\n";
exit($failures > 0 ? 1 : 0);
