<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroIntervencion;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$root = dirname(__DIR__);
$catalog = new Catalog($root);
$base = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12],
    'encuentros' => [],
];

$encOrg = [
    'id' => 'enc_org',
    'estado' => 'en_curso',
    'intencion' => 'celeste_organizado',
    'participantes' => ['per_a', 'per_b'],
    'dia' => 1,
    'hora' => 12,
    'lugar' => 'lug_cafeteria',
];
$partidaOrg = $base;
$partidaOrg['encuentros'][] = $encOrg;
$vOrg = EncuentroIntervencion::vistaParaPlay($partidaOrg, $encOrg, $catalog);
ok($vOrg['motivo_no_disponible'] === null, 'organizado en curso: sin motivo bloqueo');
ok($vOrg['puede_intervenir_motor'] === true, 'organizado en curso: motor permite');

$encEsp = $encOrg;
$encEsp['id'] = 'enc_esp';
$encEsp['intencion'] = 'autonomo';
$vEsp = EncuentroIntervencion::vistaParaPlay($partidaOrg, $encEsp, $catalog);
ok($vEsp['motivo_no_disponible'] === 'no_organizado_por_celestine', 'espontáneo: motivo no_organizado');
ok($vEsp['disponible'] === false, 'espontáneo: MENTES no disponible');

$encUsada = $encOrg;
$encUsada['intervencion_celeste'] = ['usada' => true];
ok(
    EncuentroIntervencion::motivoNoIntervenir($partidaOrg, $encUsada) === 'intervencion_ya_usada',
    'ya usada: motivo intervencion_ya_usada'
);

echo $fail === 0 ? "\nencuentro_cta_motivo_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
