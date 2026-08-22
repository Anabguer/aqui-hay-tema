<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\ExpressionResolver;
use AquiHayTema\Engine\ExpresionVisual;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\SchemaFields;
use AquiHayTema\Engine\VisualPackStore;

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

DomainBootstrap::resetForTests();
DomainBootstrap::boot();

$store = new VisualPackStore($root);
$catalog = new CatalogStore($root);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'expr-test');
$ph = $service->crearResidentePlaceholderDev($partida);
$rid = $ph['residente']['catalog_id'];

SchemaFields::ensure($partida);
$res = $partida['residentes'][$rid];
ok(isset($res['runtime']['estado_emocional']['id']), 'runtime tiene estado_emocional');
ok(isset($res['runtime']['expresion_visual']['id']), 'runtime tiene expresion_visual');
ok($res['runtime']['estado_emocional']['id'] === 'neutro', 'default neutro');
ok($res['runtime']['expresion_visual']['id'] === 'neutral', 'default expression neutral');
ok($res['runtime']['animo'] === 'neutro', 'animo es alias del estado, no del png');

$pack = $store->pack('lab_01_teo');
ok(is_array($pack), 'pack lab_01_teo registrado');
ok($store->disponible($pack, 'neutral'), 'Teo tiene neutral');
ok($store->disponible($pack, 'alegre'), 'Teo tiene alegre');
ok(!$store->disponible($pack, 'esceptico'), 'Teo aún no tiene esceptico (progresivo)');

$teoFrustrado = ExpressionResolver::resolver([
    'estado_emocional_id' => 'frustrado',
    'personalidad' => ['ironico'],
    'contexto' => ['origen' => 'encuentro'],
    'pack' => $pack,
], $store, $catalog);
ok($teoFrustrado['estado_emocional_id'] === 'frustrado', 'resolver conserva estado interno');
ok($teoFrustrado['expression_id'] === 'enfadado', 'frustrado ≠ enfadado.png directo; mapeo placeholder a enfadado');
ok($teoFrustrado['personalidad_aplicada'] === false, 'personalidad aún no se aplica');
ok($teoFrustrado['asset']['existe'] ?? false, 'asset enfadado existe');

$teoCeloso = ExpressionResolver::resolver([
    'estado_emocional_id' => 'celoso',
    'pack' => $pack,
], $store, $catalog);
ok($teoCeloso['expression_id'] === 'neutral', 'celoso sin clave de mapeo → neutral (no se inventa cara)');
ok($teoCeloso['fallback'] === false || $teoCeloso['motivo'] === 'sin_mapeo', 'sin mapeo no es fallback de asset faltante');

$v1Alegre = ExpressionResolver::resolver([
    'estado_emocional_id' => 'alegre',
    'pack' => $pack,
], $store, $catalog);
ok($v1Alegre['expression_id'] === 'alegre', 'V1 alegre → expresión alegre sin mapeo explícito en pack');
ok($v1Alegre['asset']['existe'] ?? false, 'asset alegre existe en pack Teo');

$v1Neutro = ExpressionResolver::resolver([
    'estado_emocional_id' => 'neutro',
    'pack' => $pack,
], $store, $catalog);
ok($v1Neutro['expression_id'] === 'neutral', 'V1 neutro → expresión neutral');

$packEsceptico = $pack;
$packEsceptico['mapeo_estado_a_expresion'] = ['celoso' => 'esceptico'];
$teoCelosoAsset = ExpressionResolver::resolver([
    'estado_emocional_id' => 'celoso',
    'pack' => $packEsceptico,
], $store, $catalog);
ok($teoCelosoAsset['expression_id'] === 'neutral' && $teoCelosoAsset['fallback'] === true, 'mapeo a esceptico sin PNG → fallback neutral');

$packOnlyNeutral = $pack;
$packOnlyNeutral['expresiones'] = [
    'neutral' => $pack['expresiones']['neutral'],
];
$packOnlyNeutral['mapeo_estado_a_expresion'] = [];
$rVar = ExpressionResolver::resolver([
    'estado_emocional_id' => 'frustrado',
    'expresion_solicitada' => 'alegre',
    'pack' => $packOnlyNeutral,
], $store, $catalog);
ok($rVar['expression_id'] === 'neutral', 'pack con N=1 (solo neutral) no falla al pedir otra expresión');
ok($rVar['fallback'] === true, 'fallback neutral marcado cuando faltan assets del pack');
ok(!empty($rVar['asset']['existe'] ?? false), 'neutral existe y se usa como fallback');

$packB = $pack;
$packB['mapeo_estado_a_expresion']['frustrado'] = 'esceptico';
$otro = ExpressionResolver::resolver([
    'estado_emocional_id' => 'frustrado',
    'pack' => $packB,
], $store, $catalog);
ok($teoFrustrado['expression_id'] !== $otro['expression_id'] || $otro['fallback'], 'dos packs pueden resolver distinto el mismo estado');
ok($otro['expression_id'] === 'neutral' && $otro['fallback'], 'pack B pide esceptico inexistente → neutral');

$emo = $service->emociones();
$vinc = $emo; // vincular pack al placeholder
$partida['residentes'][$rid]['runtime']['visual_pack_id'] = 'lab_01_teo';
$rEst = $emo->aplicar($partida, $rid, 'frustrado', 'dev_manual', null, null);
ok(($rEst['ok'] ?? false) && $rEst['estado_emocional']['id'] === 'frustrado', 'aplicar estado frustrado');
ok($rEst['expresion']['expression_id'] === 'enfadado', 'resolver tras aplicar usa pack vinculado');

$antesEstado = $partida['residentes'][$rid]['runtime']['estado_emocional']['id'];
$ov = $emo->overrideExpresionDev($partida, $rid, 'alegre');
ok($ov['ok'] ?? false, 'override DEV ok');
ok($partida['residentes'][$rid]['runtime']['estado_emocional']['id'] === $antesEstado, 'override NO cambia estado emocional');
ok($ov['expresion']['expression_id'] === 'alegre', 'override pinta alegre');
ok($ov['sin_evento_de_juego'] ?? false, 'override no es evento de juego');

$packV = $pack;
$packV['expresiones']['alegre']['identidad_version'] = 99;
$mismatch = ExpressionResolver::resolver([
    'estado_emocional_id' => 'ilusionado',
    'pack' => $packV,
], $store, $catalog);
ok($mismatch['expression_id'] === 'neutral' && $mismatch['fallback'], 'identidad_version distinta se ignora');

$rHasta = $emo->aplicar($partida, $rid, 'incomodo', 'dev_manual', null, ['dia' => 1, 'hora' => 10]);
ok($rHasta['ok'] ?? false, 'estado con hasta');
$service->avanzarReloj($partida, 12);
ok($partida['residentes'][$rid]['runtime']['estado_emocional']['id'] === 'neutro', 'hasta vencido vuelve a neutro');
ok($partida['residentes'][$rid]['runtime']['estado_emocional']['origen'] === 'expiracion', 'origen expiracion');

$ids = ExpresionVisual::PROVISIONALES;
ok(in_array('neutral', $ids, true) && count($ids) === 9, 'catálogo visual tiene 9 ids, neutral incluido');

$antes = count(glob($root . '/assets/personajes/_laboratorio/LAB_01_Teo/*.png') ?: []);
ExpressionResolver::resolver(['estado_emocional_id' => 'ilusionado', 'pack' => $pack], $store, $catalog);
$despues = count(glob($root . '/assets/personajes/_laboratorio/LAB_01_Teo/*.png') ?: []);
ok($antes === $despues, 'el motor no crea PNG');

ok(!EstadoEmocional::vencido(null, ['dia_pueblo' => 9, 'hora_actual' => 23]), 'hasta null no vence');

exit($failures > 0 ? 1 : 0);
