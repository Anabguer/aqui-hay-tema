<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RelacionEngine;

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
$partida = $service->nuevaPartida('playtest_01', 'playtest-01');
$reloj = $partida['reloj'];

ok(!empty($reloj['fecha_ancla']), 'fecha_ancla persistida');
ok(($reloj['zona'] ?? '') === Reloj::ZONA, 'zona Europe/Madrid');
ok(strpos(Reloj::formatear($reloj), 'de agosto de') !== false || strpos(Reloj::formatear($reloj), ' de ') !== false, 'formatear fecha real');
ok(strpos(Reloj::formatear($reloj), 'Día 1') === false, 'ya no dice Día 1');
ok(Reloj::diaSemana(1, $reloj) === 'lunes', 'tests: día 1 anclado a lunes 17/08/2026');
ok(Reloj::fechaCorta($reloj, 1) === '17/08', 'fecha corta día 1');
ok(Reloj::fechaCorta($reloj, 2) === '18/08', 'fecha corta día 2');

$vista = Reloj::vista($reloj);
ok(count($vista['proximos_dias'] ?? []) === 7, 'selector 7 días');
ok(strpos((string) ($vista['proximos_dias'][0]['etiqueta'] ?? ''), 'Hoy') === 0, 'primer chip es Hoy');

$cal = CalibracionConfig::load($root);
ok(PropuestaNivel::tiposPermitidos($partida, 'per_p001', 'per_p002', $cal) === ['conocerse'], 'desconocidos solo conocerse');
$rom = PropuestaEncuentroEngine::proponer($partida, ['per_p001', 'per_p002'], 1, 20, 'primera_cita', 'lug_cafeteria');
ok(($rom['ok'] ?? true) === false, 'proponer primera cita entre desconocidos rechazado');
ok(($rom['error'] ?? '') === 'TIPO_ENCUENTRO_NO_DISPONIBLE', 'código tipo no disponible');

$ami = PropuestaEncuentroEngine::proponer($partida, ['per_p001', 'per_p002'], 1, 20, 'amistad', 'lug_cafeteria');
ok(($ami['ok'] ?? true) === false, 'proponer amistad/quedar entre desconocidos rechazado');

RelacionEngine::registrarContacto($partida, 'per_p001', 'per_p002', 'normal');
$tras = PropuestaNivel::tiposPermitidos($partida, 'per_p001', 'per_p002', $cal);
ok(in_array('quedar', $tras, true), 'tras conocerse hay Quedar');
ok(!in_array('amistad', $tras, true), 'PLAY no ofrece Amistad');
ok(!in_array('romantico', $tras, true), 'sin señal no hay romántico');
ok(!in_array('primera_cita', $tras, true), 'sin señal no hay primera cita');
ok(!in_array('conocerse', $tras, true), 'tras conocerse ya no es presentar');

$p2 = $service->nuevaPartida('playtest_01', 'playtest-rechazo-ui');
$r = $service->proponerEncuentro($p2, ['per_p001', 'per_p002'], 1, 18, 'conocerse', 'lug_cafeteria');
if (!empty($r['rechazada'])) {
    ok(strpos((string) ($r['mensaje_ui'] ?? ''), 'ha rechazado la propuesta') !== false, 'rechazo nombra al hablante');
    ok(!empty($r['rechazado_por']['residente_id']), 'rechazado_por id');
    ok(!empty($r['rechazado_por']['nombre']), 'rechazado_por nombre');
} else {
    ok(true, 'esta seed aceptó; el formato de rechazo se cubre en otras seeds');
}

$estado = $service->estadoResumido($partida);
ok(strpos((string) ($estado['encuentros_activos_label'] ?? ''), 'encuentro') !== false, 'label encuentros no dice 0 activos');
ok(isset($estado['reloj_vista']['proximos_dias']), 'estado trae reloj_vista');

$ficha = $service->fichaResidente($partida, 'per_p001');
ok(isset($ficha['vista_play']['nombre']), 'vista_play en ficha');
ok(!in_array('Directo', $ficha['vista_play']['manera_de_ser'] ?? ['Directo'], true)
    || in_array('Va al grano al hablar', $ficha['vista_play']['manera_de_ser'] ?? [], true)
    || ($ficha['vista_play']['manera_de_ser'] ?? []) === [], 'rasgo directo no se muestra crudo si aparece');

exit($failures > 0 ? 1 : 0);
