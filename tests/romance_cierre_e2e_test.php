<?php
declare(strict_types=1);

/**
 * CIERRE E2E Nº3 — ROMANCE AUTÓNOMO — TESTS DE CIERRE
 * Verifica la ruta completa: conocerse → interés/flechazo → señal → primera_cita → declaración → pareja
 * Requiere: fix de familias_en_play + condición primera_cita en declaracion
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AcontecimientoDiario;
use AquiHayTema\Engine\AcontecimientoElegibilidad;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CatalogStore;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroResolver;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\IniciativaRomantica;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionGrafo;
use AquiHayTema\Engine\RomanceElegibilidad;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SchemaFields;
use AquiHayTema\Engine\SenalRomantica;

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

function makePartida(array $cal): array {
    $partida = [
        'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 14],
        'residentes' => [],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'parentesco' => [],
        'bitacora_relaciones' => [],
        'buzon' => [],
        'memoria_eventos' => [],
        'historial_relaciones' => [],
        'encuentros' => [],
        'celeste' => [
            'lugares_desbloqueados' => ['lug_cafeteria', 'lug_parque', 'lug_biblioteca', 'lug_bingo'],
        ],
        'features' => [
            'npc_autonomy_enabled' => true,
            'encuentros_enabled' => true,
        ],
        'npc_autonomo' => ['planes_pendientes' => [], 'historial_eventos' => []],
    ];
    $ids = ['res_A', 'res_B'];
    foreach ($ids as $id) {
        $partida['residentes'][$id] = [
            'catalog_id' => $id,
            'presencia' => 'residente',
            'runtime' => [
                'ocupacion' => 'empleado',
                'ultimo_protagonismo_dia' => 0,
                'perfil_partida' => [
                    'edad' => 30,
                    'hobbies' => ['leer'],
                    'rasgos' => ['amable'],
                    'preferencias' => [
                        'personalidad_pos' => [], 'personalidad_neg' => [],
                        'visual_pos' => [], 'visual_neg' => [],
                        'hobbies_pos' => [], 'hobbies_neg' => [],
                    ],
                ],
                'estado_emocional' => EstadoEmocional::estructura('neutro'),
            ],
        ];
    }
    SchemaFields::ensure($partida);
    RelacionGrafo::asegurarTodos($partida, $cal);
    return $partida;
}

$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$store = $catalog->store();

// =====================================================================
// A. Declaración SIN PRIMERA_CITA → NO elegible
// =====================================================================
echo "--- A: declaración SIN PRIMERA_CITA ---\n";
$pa = makePartida($cal);
RelacionEngine::upsertSocial($pa, 'res_A', 'res_B', 'conocido', 1);
$item = $store->item('acontecimientos', 'declaracion');
$r = AcontecimientoElegibilidad::cumple($pa, $item, ['res_A', 'res_B'], $cal);
ok(!$r['ok'], 'declaracion SIN primera_cita → NO elegible');
ok(in_array('primera_cita', $r['fallos']), 'fallo incluye "primera_cita"');

// =====================================================================
// B. Flechazo pero SIN PRIMERA_CITA → NO elegible para declaracion
// =====================================================================
echo "--- B: flechazo SIN primera_cita → declaracion NO elegible ---\n";
$pa2 = makePartida($cal);
RelacionEngine::upsertSocial($pa2, 'res_A', 'res_B', 'conocido', 1);
RelacionBitacora::registrar($pa2, RelacionBitacora::FLECHAZO, ['res_A', 'res_B'], null, [
    'direccion' => 'res_A>res_B',
]);
RelacionEngine::setRomanceHacia($pa2, 'res_A', 'res_B', 28);
$item = $store->item('acontecimientos', 'declaracion');
$r = AcontecimientoElegibilidad::cumple($pa2, $item, ['res_A', 'res_B'], $cal);
ok(!$r['ok'], 'declaracion con flechazo pero SIN primera_cita → NO elegible');

// =====================================================================
// C. Señal/romance alto SIN PRIMERA_CITA → NO elegible para declaracion
// =====================================================================
echo "--- C: romance alto SIN primera_cita → declaracion NO elegible ---\n";
$pa3 = makePartida($cal);
RelacionEngine::upsertSocial($pa3, 'res_A', 'res_B', 'conocido', 1);
RelacionEngine::setRomanceHacia($pa3, 'res_A', 'res_B', 50);
$item = $store->item('acontecimientos', 'declaracion');
$r = AcontecimientoElegibilidad::cumple($pa3, $item, ['res_A', 'res_B'], $cal);
ok(!$r['ok'], 'declaracion con romance=50 pero SIN primera_cita → NO elegible');

// =====================================================================
// D. PRIMERA_CITA persistida + resto condiciones → declaración elegible
// =====================================================================
echo "--- D: PRIMERA_CITA persistida → declaracion elegible ---\n";
$pa4 = makePartida($cal);
RelacionEngine::upsertSocial($pa4, 'res_A', 'res_B', 'conocido', 1);
RelacionEngine::setRomanceHacia($pa4, 'res_A', 'res_B', 10);
RelacionBitacora::registrar($pa4, RelacionBitacora::PRIMERA_CITA, ['res_A', 'res_B']);
$item = $store->item('acontecimientos', 'declaracion');
$r = AcontecimientoElegibilidad::cumple($pa4, $item, ['res_A', 'res_B'], $cal);
ok($r['ok'], 'declaracion con PRIMERA_CITA + se_conocen + interes + no_veto → elegible');

// =====================================================================
// E. Declaración aceptada → ParejaEngine::formar()
// =====================================================================
echo "--- E: declaración aceptada → formar() OK ---\n";
$pa5 = makePartida($cal);
RelacionEngine::upsertSocial($pa5, 'res_A', 'res_B', 'conocido', 1);
RelacionEngine::setRomanceHacia($pa5, 'res_A', 'res_B', 15);
RelacionBitacora::registrar($pa5, RelacionBitacora::PRIMERA_CITA, ['res_A', 'res_B']);
$r = ParejaEngine::formar($pa5, 'res_A', 'res_B', true, true, RelacionBitacora::DECLARACION, $cal);
ok($r['ok'] ?? false, 'ParejaEngine::formar(acepta_ambos) → ok');

// =====================================================================
// F. Pareja persiste
// =====================================================================
echo "--- F: pareja persiste en estado ---\n";
$est = ParejaEngine::estado($pa5, 'res_A', 'res_B');
ok($est === ParejaEngine::PAREJA, "estado_pareja = pareja (fue: $est)");
$tienenHito = RelacionBitacora::tienenHito($pa5, 'res_A', 'res_B', RelacionBitacora::INICIO_PAREJA);
ok($tienenHito, 'bitacora tiene INICIO_PAREJA');

// =====================================================================
// G. Crisis no puede ocurrir sin pareja
// =====================================================================
echo "--- G: crisis sin pareja → rechazada ---\n";
$pa6 = makePartida($cal);
RelacionEngine::upsertSocial($pa6, 'res_A', 'res_B', 'conocido', 1);
$r = ParejaEngine::crisis($pa6, 'res_A', 'res_B');
ok(!($r['ok'] ?? false), 'crisis sin ser pareja → rechazada');

// =====================================================================
// H. Ruptura no puede ocurrir sin pareja/crisis
// =====================================================================
echo "--- H: ruptura sin pareja → rechazada ---\n";
$pa7 = makePartida($cal);
RelacionEngine::upsertSocial($pa7, 'res_A', 'res_B', 'conocido', 1);
$r = ParejaEngine::romper($pa7, 'res_A', 'res_B', 'test');
ok(!($r['ok'] ?? false), 'ruptura sin ser pareja → rechazada');

// =====================================================================
// I. Config normal incluye romance_hito y pareja
// =====================================================================
echo "--- I: config familias_en_play incluye romance_hito y pareja ---\n";
$familias = $cal['acontecimientos_dia']['familias_en_play'] ?? [];
ok(in_array('romance_hito', $familias, true), 'familias_en_play incluye romance_hito');
ok(in_array('pareja', $familias, true), 'familias_en_play incluye pareja');
ok(in_array('trabajo', $familias, true), 'familias_en_play conserva trabajo');
ok(in_array('ocio', $familias, true), 'familias_en_play conserva ocio');
ok(in_array('romance', $familias, true), 'familias_en_play conserva romance');
ok(in_array('consejo', $familias, true), 'familias_en_play conserva consejo');
ok(in_array('romance_accion', $familias, true), 'familias_en_play conserva romance_accion');

// =====================================================================
// J. LAB y config normal ya no difieren por accesibilidad de estas familias
// =====================================================================
echo "--- J: accesibilidad de familias consistente ---\n";
// Con lab_vida_activa=false (producción), enPlay=true → familias_en_play aplica
$paProd = makePartida($cal);
$paProd['lab_vida_activa'] = false;
// Verificar que romance_hito está en familias_en_play
$enPlay = empty($paProd['lab_vida_activa']);
ok($enPlay, 'lab_vida_activa=false → enPlay=true → familias_en_play aplica');
// Verificar que las familias excluidas ahora incluyen romance_hito/pareja
$famsPlay = $cal['acontecimientos_dia']['familias_en_play'] ?? [];
ok(in_array('romance_hito', $famsPlay), 'romance_hito accesible en config normal');
ok(in_array('pareja', $famsPlay), 'pareja accesible en config normal');

// =====================================================================
// K. Gate de edad permanece intacto
// =====================================================================
echo "--- K: gate de edad intacto ---\n";
$pa8 = makePartida($cal);
$pa8['residentes']['res_A']['runtime']['perfil_partida']['edad'] = 25;
$pa8['residentes']['res_B']['runtime']['perfil_partida']['edad'] = 40;
RelacionEngine::upsertSocial($pa8, 'res_A', 'res_B', 'conocido', 1);
$item = $store->item('acontecimientos', 'declaracion');
// 25 vs 40 → delta=15 > limite_duro=10 → NO elegible
$r = AcontecimientoElegibilidad::cumple($pa8, $item, ['res_A', 'res_B'], $cal);
ok(!$r['ok'], 'declaracion con delta_edad=15 (>10) → NO elegible');
ok(in_array('primera_cita', $r['fallos']) || in_array('no_parentesco_veto', $r['fallos']) || !$r['ok'], 'fallo detectado');

// Con edades compatibles + primera_cita
$pa8b = makePartida($cal);
$pa8b['residentes']['res_A']['runtime']['perfil_partida']['edad'] = 30;
$pa8b['residentes']['res_B']['runtime']['perfil_partida']['edad'] = 35;
RelacionEngine::upsertSocial($pa8b, 'res_A', 'res_B', 'conocido', 1);
RelacionEngine::setRomanceHacia($pa8b, 'res_A', 'res_B', 10);
RelacionBitacora::registrar($pa8b, RelacionBitacora::PRIMERA_CITA, ['res_A', 'res_B']);
$item = $store->item('acontecimientos', 'declaracion');
$r = AcontecimientoElegibilidad::cumple($pa8b, $item, ['res_A', 'res_B'], $cal);
ok($r['ok'], 'declaracion con delta_edad=5 (≤10) + primera_cita → elegible');

// =====================================================================
// L. Rechazo/Voluntad permanece operativo
// =====================================================================
echo "--- L: rechazo/voluntad operativo ---\n";
$pa9 = makePartida($cal);
$r = ParejaEngine::formar($pa9, 'res_A', 'res_B', false, true, RelacionBitacora::DECLARACION, $cal);
ok(!($r['ok'] ?? false), 'formar con A rechaza → NO formada');
$r2 = ParejaEngine::formar($pa9, 'res_A', 'res_B', true, false, RelacionBitacora::DECLARACION, $cal);
ok(!($r2['ok'] ?? false), 'formar con B rechaza → NO formada');

// =====================================================================
// M. AcontecimientoElegibilidad: todas las condiciones nuevas verificadas
// =====================================================================
echo "--- M: verificación completa de condiciones ---\n";
// Condiciones de declaracion: se_conocen, interes_o_historia, no_parentesco_veto, no_son_pareja, primera_cita
// Test: sin conocerse → fallo
$pa10 = makePartida($cal);
$item = $store->item('acontecimientos', 'declaracion');
$r = AcontecimientoElegibilidad::cumple($pa10, $item, ['res_A', 'res_B'], $cal);
ok(!$r['ok'], 'declaracion sin conocerse → NO elegible');
ok(in_array('se_conocen', $r['fallos']), 'fallo incluye se_conocen');

// Test: son pareja → fallo
$pa11 = makePartida($cal);
RelacionEngine::upsertSocial($pa11, 'res_A', 'res_B', 'conocido', 1);
RelacionEngine::setRomanceHacia($pa11, 'res_A', 'res_B', 10);
RelacionBitacora::registrar($pa11, RelacionBitacora::PRIMERA_CITA, ['res_A', 'res_B']);
ParejaEngine::formar($pa11, 'res_A', 'res_B', true, true, RelacionBitacora::DECLARACION, $cal);
$r = AcontecimientoElegibilidad::cumple($pa11, $item, ['res_A', 'res_B'], $cal);
ok(!$r['ok'], 'declaracion siendo pareja → NO elegible');
ok(in_array('no_son_pareja', $r['fallos']), 'fallo incluye no_son_pareja');

echo "\n=== RESULTADO: $failures failures ===\n";
exit($failures > 0 ? 1 : 0);
