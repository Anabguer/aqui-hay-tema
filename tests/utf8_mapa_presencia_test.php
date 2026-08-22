<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\MapaHandler;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\Utf8Text;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

ok(Utf8Text::isValid('José'), 'UTF-8 válido pasa');
$latin = "Jos\xE9";
ok(!Utf8Text::isValid($latin), 'Latin-1 inválido detectado');
$fixed = Utf8Text::repair($latin);
ok(Utf8Text::isValid($fixed), 'repair devuelve UTF-8 válido');
ok($fixed === 'José', 'repair Latin-1 → José');

$ctx = new ApiContext(dirname(__DIR__));
$svc = new PartidaService(dirname(__DIR__));
$p = $svc->nuevaPartida('test_fixtures_v0', 'utf8-mapa');
$ph = $svc->crearResidentePlaceholderDev($p);
$ida = 'per_qa_valid';
$p['residentes'][$ida]['identidad_publica']['nombre'] = $latin;
$svc->programarEncuentro($p, [$ida, $ph['residente']['catalog_id']], 1, 19, 'conocerse', 'lug_cafeteria');
while ((int) $p['reloj']['hora_actual'] < 19) {
    $svc->avanzarReloj($p, 1);
}
$resp = MapaHandler::presencia($ctx, [], $p);
$bad = Utf8Text::rutasInvalidas($resp);
ok($bad === [], 'mapa.presencia sin rutas UTF-8 inválidas');

$p2 = $svc->nuevaPartida('test_fixtures_v0', 'utf8-ini');
$ph2 = $svc->crearResidentePlaceholderDev($p2);
$ida2 = 'per_qa_valid';
$p2['residentes'][$ida2]['identidad_publica']['nombre'] = 'Álvaro García';
$svc->programarEncuentro($p2, [$ida2, $ph2['residente']['catalog_id']], 1, 19, 'conocerse', 'lug_cafeteria');
while ((int) $p2['reloj']['hora_actual'] < 19) {
    $svc->avanzarReloj($p2, 1);
}
$resp2 = MapaHandler::presencia($ctx, [], $p2);
ok(Utf8Text::rutasInvalidas($resp2) === [], 'nombre acentuado: iniciales UTF-8 válidas');
try {
    json_encode($resp, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    ok(true, 'json_encode mapa.presencia OK');
} catch (Throwable $e) {
    ok(false, 'json_encode: ' . $e->getMessage());
}

echo $failures === 0 ? "utf8_mapa_presencia_test OK\n" : "utf8_mapa_presencia_test FAIL ($failures)\n";
exit($failures > 0 ? 1 : 0);
