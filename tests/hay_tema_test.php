<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\HayTema;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\VistaPuebloV3;

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

/**
 * @return array<string, mixed>
 */
function ht_base(): array
{
    return [
        'reloj' => ['dia_pueblo' => 3, 'hora_actual' => 11],
        'buzon' => [],
        'historial_coincidencias' => [],
        'residentes' => [
            'per_a' => ['runtime' => ['estado_emocional' => EstadoEmocional::estructura('alegre')]],
            'per_b' => ['runtime' => ['estado_emocional' => EstadoEmocional::estructura('enfadado')]],
            'per_c' => ['runtime' => ['estado_emocional' => EstadoEmocional::estructura('neutro')]],
        ],
    ];
}

$p = ht_base();
$solo = HayTema::de($p, 'per_a', 'lug_cafeteria', ['per_a']);
ok($solo['hay_tema'] === false, 'café solo, sin hecho: hay_tema false');
ok($solo['tema_id'] === null, 'café solo: sin tema_id');

$emo = HayTema::de($p, 'per_a', 'lug_cafeteria', ['per_a']);
ok($emo['hay_tema'] === false, 'emoción alegre no activa hay_tema');
$emoB = HayTema::de($p, 'per_b', 'lug_cafeteria', ['per_b']);
ok($emoB['hay_tema'] === false, 'emoción enfadado no activa hay_tema');

$p['buzon'][] = [
    'id' => 'msg_pet',
    'dia' => 3,
    'clasificacion' => BuzonEngine::PETICION,
    'canal' => BuzonEngine::CANAL_BUZON,
    'tipo' => 'peticion',
    'actores' => ['per_a'],
    'de_persona' => 'per_a',
];
ok(HayTema::de($p, 'per_a', 'lug_cafeteria', ['per_a'])['hay_tema'] === false, 'petición de buzón no es hay_tema');

$p['buzon'][] = [
    'id' => 'msg_prop',
    'dia' => 3,
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    'canal' => BuzonEngine::CANAL_BUZON,
    'tipo' => 'propuesta',
    'actores' => ['per_a', 'per_b'],
];
ok(HayTema::de($p, 'per_a', 'lug_cafeteria', ['per_a', 'per_b'])['hay_tema'] === false, 'oportunidad/propuesta no es hay_tema');

$pCot = ht_base();
$pCot['buzon'][] = [
    'id' => 'msg_cot_cafe',
    'dia' => 3,
    'clasificacion' => BuzonEngine::COTILLEO,
    'canal' => BuzonEngine::CANAL_COTILLEO,
    'tipo' => 'cotilleo',
    'actores' => ['per_a', 'per_b'],
    'lugar_id' => 'lug_cafeteria',
    'texto' => 'Hoy ha pasado algo entre A y B.',
];
$enCafe = HayTema::de($pCot, 'per_a', 'lug_cafeteria', ['per_a']);
ok($enCafe['hay_tema'] === true, 'cotilleo de hoy en ese lugar: hay_tema true');
ok($enCafe['tema_id'] === 'msg_cot_cafe', 'tema_id = id del mensaje de El Cotilleo');
ok(HayTema::de($pCot, 'per_a', 'lug_gimnasio', ['per_a'])['hay_tema'] === false, 'el mismo cotilleo no sigue a la persona a otro sitio');
ok(HayTema::de($pCot, 'per_c', 'lug_cafeteria', ['per_c', 'per_a'])['hay_tema'] === false, 'quien no sale en el hecho no tiene sello');

$pAyer = ht_base();
$pAyer['buzon'][] = [
    'id' => 'msg_ayer',
    'dia' => 2,
    'clasificacion' => BuzonEngine::COTILLEO,
    'canal' => BuzonEngine::CANAL_COTILLEO,
    'tipo' => 'cotilleo',
    'actores' => ['per_a'],
    'lugar_id' => 'lug_cafeteria',
];
ok(HayTema::de($pAyer, 'per_a', 'lug_cafeteria', ['per_a'])['hay_tema'] === false, 'cotilleo de ayer no activa el sello de hoy');

$pDisc = ht_base();
$pDisc['buzon'][] = [
    'id' => 'msg_bronca',
    'dia' => 3,
    'clasificacion' => BuzonEngine::COTILLEO,
    'canal' => BuzonEngine::CANAL_COTILLEO,
    'tipo' => 'discusion',
    'actores' => ['per_a', 'per_b'],
    'texto' => 'Se han enfadado.',
];
ok(HayTema::de($pDisc, 'per_a', 'lug_cafeteria', ['per_a'])['hay_tema'] === true, 'discusión de hoy (sin lugar) marca a la persona donde esté');
ok(HayTema::de($pDisc, 'per_b', 'lug_gimnasio', ['per_b'])['hay_tema'] === true, 'discusión de hoy marca también al otro');

$pSenal = ht_base();
$pSenal['buzon'][] = [
    'id' => 'msg_acerc',
    'dia' => 3,
    'clasificacion' => BuzonEngine::COTILLEO,
    'canal' => BuzonEngine::CANAL_COTILLEO,
    'tipo' => 'senal_romantica',
    'actores' => ['per_a', 'per_b'],
    'de_persona' => 'per_a',
    'origen' => ['informacion_revelada' => ['desde' => 'per_a', 'hacia' => 'per_b']],
];
ok(HayTema::de($pSenal, 'per_b', 'lug_parque', ['per_b'])['hay_tema'] === true, 'acercamiento publicado (señal) es un hecho, no un score');

$pPat = ht_base();
foreach ([1, 2, 3] as $d) {
    $pPat['historial_coincidencias'][] = [
        'dia' => $d,
        'hora' => 11,
        'lugar_id' => 'lug_cafeteria',
        'residentes' => ['per_a', 'per_b'],
    ];
}
$ambos = HayTema::de($pPat, 'per_a', 'lug_cafeteria', ['per_a', 'per_b']);
ok($ambos['hay_tema'] === true, 'patrón de coincidencia (3 días, ambos en el sitio): hay_tema true');
ok(is_string($ambos['tema_id']) && strpos((string) $ambos['tema_id'], 'coin_patron:') === 0, 'patrón expone tema_id interno');
ok(HayTema::de($pPat, 'per_a', 'lug_cafeteria', ['per_a'])['hay_tema'] === false, 'el mismo patrón con uno solo en el café: false');
ok(HayTema::de($pPat, 'per_a', 'lug_gimnasio', ['per_a', 'per_b'])['hay_tema'] === false, 'patrón del café no sella en el gimnasio');

$pUnDia = ht_base();
$pUnDia['historial_coincidencias'][] = [
    'dia' => 3,
    'hora' => 11,
    'lugar_id' => 'lug_cafeteria',
    'residentes' => ['per_a', 'per_b'],
];
ok(HayTema::de($pUnDia, 'per_a', 'lug_cafeteria', ['per_a', 'per_b'])['hay_tema'] === false, 'una coincidencia técnica de un día no es hay_tema');

$gente = HayTema::aplicar($pCot, [
    ['id' => 'per_a', 'destino_id' => 'lug_cafeteria'],
    ['id' => 'per_c', 'destino_id' => 'lug_cafeteria'],
]);
ok(!empty($gente[0]['hay_tema']) && $gente[0]['tema_id'] === 'msg_cot_cafe', 'aplicar marca a quien tiene el hecho');
ok(empty($gente[1]['hay_tema']) && $gente[1]['tema_id'] === null, 'aplicar no marca al vecino sin hecho');

$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'hay-tema-vista');
$ph = $service->crearResidentePlaceholderDev($partida);
$ida = 'per_qa_valid';
$idb = $ph['residente']['catalog_id'];
$enc = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa cafetería');
$ir = $service->irAlProximoEncuentro($partida);
ok($ir['ok'] ?? false, 'ir al próximo');
$partida['residentes'][$ida]['runtime']['estado_emocional'] = EstadoEmocional::estructura('alegre');
$mapa = PresenciaEngine::resolver($partida, $root);
$pueblo = VistaPuebloV3::de($partida, $mapa, $root);
$cafe = null;
foreach ($pueblo['complejos'] ?? [] as $c) {
    if (($c['id'] ?? '') === 'cafe_libros') {
        $cafe = $c;
        break;
    }
}
ok(is_array($cafe) && ($cafe['total'] ?? 0) >= 2, 'presencia real en café');
$temaMarca = array_values(array_filter($cafe['personas'] ?? [], static function ($row) {
    return !empty($row['hay_tema']);
}));
ok($temaMarca === [], 'encuentro próximo/en curso NO activa hay_tema');

BuzonEngine::crear($partida, [
    'id' => 'msg_play_tema',
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'cotilleo',
    'texto' => 'Hoy ha pasado algo en el café.',
    'actores' => [$ida, $idb],
    'lugar_id' => 'lug_cafeteria',
    '_placeholder_contenido' => false,
]);
$pueblo2 = VistaPuebloV3::de($partida, PresenciaEngine::resolver($partida, $root), $root);
$cafe2 = null;
foreach ($pueblo2['complejos'] ?? [] as $c) {
    if (($c['id'] ?? '') === 'cafe_libros') {
        $cafe2 = $c;
        break;
    }
}
$temaHecho = [];
foreach ($cafe2['personas'] ?? [] as $row) {
    if (in_array($row['id'], [$ida, $idb], true) && !empty($row['hay_tema'])) {
        $temaHecho[] = $row;
    }
}
ok(count($temaHecho) === 2, 'PLAY pueblo: hay_tema true cuando hay cotilleo real de esos dos en el café');
ok(($temaHecho[0]['tema_id'] ?? null) === 'msg_play_tema', 'PLAY pueblo expone tema_id');
ok(!empty($cafe2['hay_tema']), 'complejo.hay_tema si algún token lo tiene');

echo $failures === 0 ? "OK hay_tema\n" : "FAIL hay_tema ({$failures})\n";
exit($failures > 0 ? 1 : 0);
