<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaConjunta;
use AquiHayTema\Engine\AforoEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\ContactoCalidad;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\LugarAtributos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionBandas;
use AquiHayTema\Engine\RelacionDesgaste;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionGrafo;
use AquiHayTema\Engine\SimuladorPuebloVivo;
use AquiHayTema\Engine\TerceroRomantico;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

$root = dirname(__DIR__);
$failures = 0;

function d424_ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cal = CalibracionConfig::load($root);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'd424');
$ph = $service->crearResidentePlaceholderDev($partida);
$a = 'per_qa_valid';
$b = $ph['residente']['catalog_id'];

RelacionGrafo::asegurarTodos($partida, $cal);
d424_ok(RelacionEngine::seConocen($partida, $a, $b) === false, 'latente no es conocido');
$soc = RelacionEngine::socialHacia($partida, $a, $b);
d424_ok(is_array($soc) && (int) $soc['valor'] === 0, 'desconocido visible con social 0');
d424_ok(($soc['banda'] ?? '') === 'desconocido', 'etiqueta desconocido');

RelacionEngine::registrarContacto($partida, $a, $b, ContactoCalidad::LEVE, $cal, 1, 0);
d424_ok(RelacionEngine::seConocen($partida, $a, $b), 'tras contacto conocidos=true');
d424_ok(RelacionEngine::valorSocialHacia($partida, $a, $b) === 0, '0 con contacto sigue 0');
d424_ok(RelacionBandas::social(0, true, $cal) === 'conocido', '0 con contacto = conocido');

d424_ok(RelacionBandas::romance(0, $cal) === 'sin_interes', 'romance 0 sin interés');
d424_ok(RelacionBandas::romance(50, $cal) === 'pillado', 'romance 50 pillado');
d424_ok(RelacionDesgaste::proyectarValor(10, 20, $cal) === 0, 'social 10 se evapora hacia 0');
d424_ok(RelacionDesgaste::proyectarValor(90, 20, $cal) >= 85, 'social 90 apenas baja');
d424_ok(RelacionDesgaste::proyectarValor(10, 20, $cal) >= 0, 'desgaste nunca negativo');

$mods = EstadoEmocional::modificadores('alegre', $cal);
d424_ok((int) $mods['aceptar_planes'] > 0, 'alegre favorece aceptación');
d424_ok($mods['no_modifica_romance_automatico'] === true, 'emoción no baja romance sola');

$perfilA = $partida['residentes'][$a]['runtime']['perfil_partida'] ?? [];
$h0 = $perfilA['hobbies'][0] ?? null;
d424_ok(is_string($h0) && DiscoveryReveal::jugadorSabeHobby($partida, $a, $h0), 'reveal inicial 1 hobby');
$h1 = $perfilA['hobbies'][1] ?? null;
if (is_string($h1)) {
    d424_ok(!DiscoveryReveal::jugadorSabeHobby($partida, $a, $h1), 'hobby 2 sigue oculto');
}

ConocimientoNpc::revelar($partida, $a, $b, [ConocimientoNpc::campoHobby('cine')], 'test');
d424_ok(ConocimientoNpc::sabe($partida, $a, $b, ConocimientoNpc::campoHobby('cine')), 'NPC A sabe cine de B');
d424_ok(!ConocimientoNpc::sabe($partida, $b, $a, ConocimientoNpc::campoHobby('cine')), 'B no hereda el secreto');

d424_ok(PropuestaNivel::tipoPara($partida, $a, $b) === PropuestaNivel::PRESENTAR
    || RelacionEngine::seConocen($partida, $a, $b), 'nivel presentar o ya conocidos');

$attr = LugarAtributos::de('lug_cafeteria');
d424_ok($attr['aforo'] > 0 && $attr['duracion_minutos'] >= 60, 'cafetería tiene aforo y duración');
d424_ok(AforoEngine::cabe($partida, 'lug_cafeteria', 1, 19, 2), 'aforo inicial cabe 2');

$franja = AgendaConjunta::primeraFranja($partida, [$a, $b], 1, 19, 22, 1, 3, 'lug_cafeteria');
d424_ok(!empty($franja['ok']), 'agenda conjunta encuentra hueco');

$vol = new VoluntadPonderadaEvaluator($cal);
$pFake = ['participantes' => [$a, $b], 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria'];
$ev = $vol->evaluar($partida, $pFake, $a);
d424_ok(in_array($ev['decision'], ['acepta', 'rechaza'], true), 'voluntad ponderada decide');
d424_ok(($ev['p'] ?? 1) < 1.0, 'voluntad nunca 100%');

$rProp = PropuestaEncuentroEngine::proponer($partida, [$a, $b], (int) ($franja['dia'] ?? 1), (int) ($franja['hora'] ?? 19), 'conocerse', 'lug_cafeteria', null, $vol);
d424_ok(isset($rProp['propuesta']), 'proponer registra');

$ficha = $service->fichaResidente($partida, $a);
d424_ok(isset($ficha['relaciones'][$b]), 'todos los residentes aparecen en relaciones');
d424_ok(($ficha['relaciones'][$b]['conocidos'] ?? false) === true, 'ficha refleja contacto');

d424_ok(TerceroRomantico::multiplicador($partida, $a, $b, $cal) === 1.0, 'sin pareja no hay freno de tercero');

$lab = SimuladorPuebloVivo::ejecutar($root, [3], [2], 1, 'd424-sim', null);
d424_ok(isset($lab['por_tamano'][3][2]['eventos_vida_por_dia']), 'sim 3 residentes 2 días corre');

exit($failures > 0 ? 1 : 0);
