<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\HistoriaPuebloVista;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\TutorialPrimerosPasos;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\HistoriaEmocionesBridge;
use AquiHayTema\Engine\HistoriaEncuentroBridge;
use AquiHayTema\Engine\HistoriaMarchaBridge;
use AquiHayTema\Engine\HistoriaCotilleoBridge;
use AquiHayTema\Engine\HistoriaEventosPuebloBridge;
use AquiHayTema\Engine\HistoriaAutonomiaBridge;
use AquiHayTema\Engine\HistoriaConfianzaBridge;
use AquiHayTema\Engine\HistoriaVinculoBridge;
use AquiHayTema\Engine\HistoriaInteresMutuoBridge;
use AquiHayTema\Engine\HistoriaRegaloBridge;

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

function diag(string $m): void
{
    echo "  DIAG: $m\n";
}

$service = new PartidaService($root);

// Helper: crear partida con tutorial completado
function crearPartidaConTutorial(): array
{
    global $service, $root;
    $p = $service->nuevaPartida('juego_v1', 'hp-trigger-' . microtime(true));
    $p['tutorial']['jugable_completado'] = true;
    TutorialPrimerosPasos::marcarFinaleVisto($p);
    return $p;
}

// Helper: obtener primeros N residentes activos
function getRes(array $p, int $n): array
{
    $ids = array_slice(array_keys($p['residentes'] ?? []), 0, $n);
    foreach ($ids as $i => $rid) {
        $p['residentes'][$rid]['presencia'] = 'residente';
    }
    return $ids;
}

// Helper: verificar si un hito existe en historia_pueblo
function hitoExiste(array $p, string $hitoId): bool
{
    foreach ($p['historia_pueblo'] ?? [] as $e) {
        if (($e['hito_id'] ?? '') === $hitoId) {
            return true;
        }
    }
    return false;
}

// Helper: contar entradas de un hito
function contarHito(array $p, string $hitoId): int
{
    $n = 0;
    foreach ($p['historia_pueblo'] ?? [] as $e) {
        if (($e['hito_id'] ?? '') === $hitoId) {
            $n++;
        }
    }
    return $n;
}

echo "=== TESTS DE TRIGGERS DE HISTORIA DEL PUEBLO ===\n\n";

// ====================================================================
// === alBitacoraHito: triggers relacionales                         ===
// ====================================================================

$p = crearPartidaConTutorial();
$res = getRes($p, 4);
$pA = $res[0];
$pB = $res[1];
$pC = $res[2];
$pD = $res[3];

// --- 1) hito_02: SE_CONOCIERON ---
ok(!hitoExiste($p, 'hito_02'), 'T01. hito_02 no existe antes de SE_CONOCIERON');
RelacionBitacora::registrar($p, RelacionBitacora::SE_CONOCIERON, [$pA, $pB]);
ok(hitoExiste($p, 'hito_02'), 'T02. hito_02 registrado con SE_CONOCIERON');
ok(contarHito($p, 'hito_02') === 1, 'T03. hito_02 exactamente 1 entrada');

// Idempotencia
RelacionBitacora::registrar($p, RelacionBitacora::SE_CONOCIERON, [$pA, $pB]);
ok(contarHito($p, 'hito_02') === 1, 'T04. hito_02 idempotente (no duplica)');

// --- 2) hito_03: FLECHAZO ---
ok(!hitoExiste($p, 'hito_03'), 'T05. hito_03 no existe antes de FLECHAZO');
RelacionBitacora::registrar($p, RelacionBitacora::FLECHAZO, [$pA, $pB], $pA . '>' . $pB);
ok(hitoExiste($p, 'hito_03'), 'T06. hito_03 registrado con FLECHAZO');
ok(contarHito($p, 'hito_03') === 1, 'T07. hito_03 exactamente 1 entrada');

// --- 3) hito_05: PRIMERA_CITA ---
ok(!hitoExiste($p, 'hito_05'), 'T08. hito_05 no existe antes de PRIMERA_CITA');
RelacionBitacora::registrar($p, RelacionBitacora::PRIMERA_CITA, [$pA, $pB]);
ok(hitoExiste($p, 'hito_05'), 'T09. hito_05 registrado con PRIMERA_CITA');
ok(contarHito($p, 'hito_05') === 1, 'T10. hito_05 exactamente 1 entrada');

// --- 4) hito_10: INICIO_PAREJA ---
ok(!hitoExiste($p, 'hito_10'), 'T11. hito_10 no existe antes de INICIO_PAREJA');
RelacionBitacora::registrar($p, RelacionBitacora::INICIO_PAREJA, [$pA, $pB]);
ok(hitoExiste($p, 'hito_10'), 'T12. hito_10 registrado con INICIO_PAREJA');
ok(contarHito($p, 'hito_10') === 1, 'T13. hito_10 exactamente 1 entrada');

// --- 5) hito_11: DECLARACION aceptada ---
ok(!hitoExiste($p, 'hito_11'), 'T14. hito_11 no existe antes de DECLARACION');
RelacionBitacora::registrar($p, RelacionBitacora::DECLARACION, [$pA, $pB], null, [
    'acepta_a' => true,
    'acepta_b' => true,
]);
ok(hitoExiste($p, 'hito_11'), 'T15. hito_11 registrado con DECLARACION aceptada');
ok(!hitoExiste($p, 'hito_12'), 'T16. hito_12 NO registrado cuando ambas aceptan');

// --- 6) hito_12: DECLARACION rechazada ---
ok(!hitoExiste($p, 'hito_12'), 'T17. hito_12 no existe antes de DECLARACION rechazada');
$nH11Antes = contarHito($p, 'hito_11');
RelacionBitacora::registrar($p, RelacionBitacora::DECLARACION, [$pC, $pD], null, [
    'acepta_a' => true,
    'acepta_b' => false,
]);
ok(hitoExiste($p, 'hito_12'), 'T18. hito_12 registrado con DECLARACION rechazada');
ok(contarHito($p, 'hito_11') === $nH11Antes, 'T19. hito_11 no se incrementa con DECLARACION rechazada');

// --- 7) hito_13: DISCUSION_FUERTE ---
ok(!hitoExiste($p, 'hito_13'), 'T20. hito_13 no existe antes de DISCUSION_FUERTE');
RelacionBitacora::registrar($p, RelacionBitacora::DISCUSION_FUERTE, [$pA, $pC]);
ok(hitoExiste($p, 'hito_13'), 'T21. hito_13 registrado con DISCUSION_FUERTE');

// --- 8) hito_14: CRISIS ---
ok(!hitoExiste($p, 'hito_14'), 'T22. hito_14 no existe antes de CRISIS');
RelacionBitacora::registrar($p, RelacionBitacora::CRISIS, [$pA, $pB]);
ok(hitoExiste($p, 'hito_14'), 'T23. hito_14 registrado con CRISIS');

// --- 9) hito_15: RUPTURA ---
ok(!hitoExiste($p, 'hito_15'), 'T24. hito_15 no existe antes de RUPTURA');
RelacionBitacora::registrar($p, RelacionBitacora::RUPTURA, [$pA, $pB]);
ok(hitoExiste($p, 'hito_15'), 'T25. hito_15 registrado con RUPTURA');

// --- 10) hito_26: APOYO_IMPORTANTE ---
ok(!hitoExiste($p, 'hito_26'), 'T26. hito_26 no existe antes de APOYO_IMPORTANTE');
RelacionBitacora::registrar($p, RelacionBitacora::APOYO_IMPORTANTE, [$pA, $pC]);
ok(hitoExiste($p, 'hito_26'), 'T27. hito_26 registrado con APOYO_IMPORTANTE');

// --- 11) hito_16: RECONCILIACION ---
ok(!hitoExiste($p, 'hito_16'), 'T28. hito_16 no existe antes de RECONCILIACION');
RelacionBitacora::registrar($p, RelacionBitacora::RECONCILIACION, [$pA, $pB]);
ok(hitoExiste($p, 'hito_16'), 'T29. hito_16 registrado con RECONCILIACION');

// --- 12) hito_31: ya NO se dispara con VUELTA (ahora es encuentro_continuidad) ---
ok(!hitoExiste($p, 'hito_31'), 'T30. hito_31 no existe antes de VUELTA');
RelacionBitacora::registrar($p, RelacionBitacora::VUELTA, [$pA, $pB]);
ok(!hitoExiste($p, 'hito_31'), 'T31. hito_31 NO registrado con VUELTA (trigger cambiado)');

// ====================================================================
// === Emotional bridge: hito_17, hito_18, hito_19                   ===
// ====================================================================

$p2 = crearPartidaConTutorial();
$res2 = getRes($p2, 2);
$pE = $res2[0];

// --- 13) hito_17: primera vez TRISTE ---
ok(!hitoExiste($p2, 'hito_17'), 'T32. hito_17 no existe antes de emoción triste');
$resultadoTriste = HistoriaEmocionesBridge::handle($p2, [
    'payload' => [
        'residente_id' => $pE,
        'antes' => ['id' => EstadoEmocional::NEUTRO],
        'despues' => ['id' => EstadoEmocional::TRISTE],
    ],
]);
ok(hitoExiste($p2, 'hito_17'), 'T33. hito_17 registrado con primer estado triste');
ok(contarHito($p2, 'hito_17') === 1, 'T34. hito_17 exactamente 1 entrada');

// Idempotencia: segundo triste no duplica
HistoriaEmocionesBridge::handle($p2, [
    'payload' => [
        'residente_id' => $pE,
        'antes' => ['id' => EstadoEmocional::NEUTRO],
        'despues' => ['id' => EstadoEmocional::TRISTE],
    ],
]);
ok(contarHito($p2, 'hito_17') === 1, 'T35. hito_17 idempotente');

// --- 14) hito_18: primera vez ENFADADO ---
ok(!hitoExiste($p2, 'hito_18'), 'T36. hito_18 no existe antes de emoción enfadado');
HistoriaEmocionesBridge::handle($p2, [
    'payload' => [
        'residente_id' => $pE,
        'antes' => ['id' => EstadoEmocional::NEUTRO],
        'despues' => ['id' => EstadoEmocional::ENFADADO],
    ],
]);
ok(hitoExiste($p2, 'hito_18'), 'T37. hito_18 registrado con primer estado enfadado');

// --- 15) hito_19: primera vez ALEGRE ---
ok(!hitoExiste($p2, 'hito_19'), 'T38. hito_19 no existe antes de emoción alegre');
HistoriaEmocionesBridge::handle($p2, [
    'payload' => [
        'residente_id' => $pE,
        'antes' => ['id' => EstadoEmocional::NEUTRO],
        'despues' => ['id' => EstadoEmocional::ALEGRE],
    ],
]);
ok(hitoExiste($p2, 'hito_19'), 'T39. hito_19 registrado con primer estado alegre');

// Emoción sin cambio no registra
$antes = contarHito($p2, 'hito_17');
HistoriaEmocionesBridge::handle($p2, [
    'payload' => [
        'residente_id' => $pE,
        'antes' => ['id' => EstadoEmocional::TRISTE],
        'despues' => ['id' => EstadoEmocional::TRISTE],
    ],
]);
ok(contarHito($p2, 'hito_17') === $antes, 'T40. sin cambio de emoción no registra');

// ====================================================================
// === Encounter bridge: hito_06, hito_07, hito_21                  ===
// ====================================================================

$p3 = crearPartidaConTutorial();
$res3 = getRes($p3, 3);
$pF = $res3[0];
$pG = $res3[1];

// --- 16) hito_06: primera cita con resultado bueno ---
ok(!hitoExiste($p3, 'hito_06'), 'T41. hito_06 no existe antes de cita buena');
$resultadoCitaBuena = HistoriaEncuentroBridge::handle($p3, [
    'payload' => [
        'encuentro' => [
            'id' => 'enc_cita_buena_1',
            'tipo' => 'primera_cita',
            'participantes' => [$pF, $pG],
        ],
        'resultado' => [
            'por_participante' => [
                $pF => ['resultado' => 'bien'],
                $pG => ['resultado' => 'muy_bien'],
            ],
        ],
    ],
]);
ok(hitoExiste($p3, 'hito_06'), 'T42. hito_06 registrado con primera cita buena');
ok(!hitoExiste($p3, 'hito_07'), 'T43. hito_07 NO registrado en cita buena');

// --- 17) hito_07: primera cita con resultado malo ---
$p4 = crearPartidaConTutorial();
$res4 = getRes($p4, 2);
$pH = $res4[0];
$pI = $res4[1];

ok(!hitoExiste($p4, 'hito_07'), 'T44. hito_07 no existe antes de cita mala');
HistoriaEncuentroBridge::handle($p4, [
    'payload' => [
        'encuentro' => [
            'id' => 'enc_cita_mala_1',
            'tipo' => 'primera_cita',
            'participantes' => [$pH, $pI],
        ],
        'resultado' => [
            'por_participante' => [
                $pH => ['resultado' => 'mal'],
                $pI => ['resultado' => 'muy_mal'],
            ],
        ],
    ],
]);
ok(hitoExiste($p4, 'hito_07'), 'T45. hito_07 registrado con primera cita mala');
ok(!hitoExiste($p4, 'hito_06'), 'T46. hito_06 NO registrado en cita mala');

// --- 18) hito_21: primer encuentro en bar ---
$p5 = crearPartidaConTutorial();
$res5 = getRes($p5, 2);
$pJ = $res5[0];
$pK = $res5[1];

ok(!hitoExiste($p5, 'hito_21'), 'T47. hito_21 no existe antes de encuentro en bar');
HistoriaEncuentroBridge::handle($p5, [
    'payload' => [
        'encuentro' => [
            'id' => 'enc_bar_1',
            'tipo' => 'conocerse',
            'lugar' => 'lug_chiringuito',
            'participantes' => [$pJ, $pK],
        ],
        'resultado' => [
            'por_participante' => [
                $pJ => ['resultado' => 'bien'],
                $pK => ['resultado' => 'bien'],
            ],
        ],
    ],
]);
ok(hitoExiste($p5, 'hito_21'), 'T48. hito_21 registrado con primer encuentro en bar');

// Idempotencia: segundo encuentro en bar no duplica
HistoriaEncuentroBridge::handle($p5, [
    'payload' => [
        'encuentro' => [
            'id' => 'enc_bar_2',
            'tipo' => 'cita',
            'lugar' => 'lug_chiringuito',
            'participantes' => [$pJ, $pK],
        ],
        'resultado' => [
            'por_participante' => [
                $pJ => ['resultado' => 'bien'],
                $pK => ['resultado' => 'bien'],
            ],
        ],
    ],
]);
ok(contarHito($p5, 'hito_21') === 1, 'T49. hito_21 idempotente');

// ====================================================================
// === Marcha bridge: hito_25                                        ===
// ====================================================================

$p6 = crearPartidaConTutorial();
$res6 = getRes($p6, 2);
$pL = $res6[0];

ok(!hitoExiste($p6, 'hito_25'), 'T50. hito_25 no existe antes de marcha');
HistoriaMarchaBridge::handle($p6, [
    'payload' => [
        'residente_id' => $pL,
        'causa' => 'aislamiento',
    ],
]);
ok(hitoExiste($p6, 'hito_25'), 'T51. hito_25 registrado con marcha efectiva');

// ====================================================================
// === EventosPueblo bridge: hito_23, hito_32                       ===
// ====================================================================

$p7 = crearPartidaConTutorial();
$res7 = getRes($p7, 7);
$pM = $res7[0];
$pN = $res7[1];
$pO = $res7[2];
$pP = $res7[3];
$pQ = $res7[4];
$pR = $res7[5];
$pS = $res7[6];

// --- 19) hito_23: evento pueblo con 4+ asistentes ---
ok(!hitoExiste($p7, 'hito_23'), 'T52. hito_23 no existe antes de evento pueblo');
HistoriaEventosPuebloBridge::handle($p7, [
    'payload' => [
        'encuentro' => [
            'id' => 'enc_evento_1',
            'intencion' => 'evento_pueblo',
            'participantes' => [$pM, $pN, $pO, $pP],
        ],
        'resultado' => ['por_participante' => []],
    ],
]);
ok(hitoExiste($p7, 'hito_23'), 'T53. hito_23 registrado con evento pueblo 4+ asistentes');

// --- 20) hito_32: evento pueblo con 6+ asistentes ---
ok(!hitoExiste($p7, 'hito_32'), 'T54. hito_32 no existe antes de evento grande');
HistoriaEventosPuebloBridge::handle($p7, [
    'payload' => [
        'encuentro' => [
            'id' => 'enc_evento_2',
            'intencion' => 'evento_pueblo',
            'participantes' => [$pM, $pN, $pO, $pP, $pQ, $pR],
        ],
        'resultado' => ['por_participante' => []],
    ],
]);
ok(hitoExiste($p7, 'hito_32'), 'T55. hito_32 registrado con evento pueblo 6+ asistentes');

// ====================================================================
// === Autonomía bridge: hito_24                                    ===
// ====================================================================

$p8 = crearPartidaConTutorial();
$res8 = getRes($p8, 2);
$pT = $res8[0];

ok(!hitoExiste($p8, 'hito_24'), 'T56. hito_24 no existe antes de NPC autónomo');
HistoriaAutonomiaBridge::handle($p8, [
    'payload' => [
        'actores' => [$pT],
    ],
]);
ok(hitoExiste($p8, 'hito_24'), 'T57. hito_24 registrado con plan NPC autónomo');

// ====================================================================
// === Cotilleo bridge: hito_33                                     ===
// ====================================================================

$p9 = crearPartidaConTutorial();
$res9 = getRes($p9, 2);
$pU = $res9[0];

ok(!hitoExiste($p9, 'hito_33'), 'T58. hito_33 no existe antes de cotilleo');
// Use gossip-worthy hito (discusion_fuerte), not benign (se_conocieron)
HistoriaCotilleoBridge::handle($p9, [
    'payload' => [
        'mensaje' => [
            'tipo' => 'cotilleo_hito',
            'actores' => [$pU],
            'hito_tipo' => 'discusion_fuerte',
        ],
    ],
]);
ok(hitoExiste($p9, 'hito_33'), 'T59. hito_33 registrado con primer cotilleo gossip');
// Benign hito_tipo should NOT register hito_33
$p9b = crearPartidaConTutorial();
HistoriaCotilleoBridge::handle($p9b, [
    'payload' => [
        'mensaje' => [
            'tipo' => 'cotilleo_hito',
            'actores' => [$pU],
            'hito_tipo' => 'se_conocieron',
        ],
    ],
]);
ok(!hitoExiste($p9b, 'hito_33'), 'T59b. hito_33 NO registrado con cotilleo benigno');

// ====================================================================
// === Confianza bridge: hito_22                                    ===
// ====================================================================

$p10 = crearPartidaConTutorial();
$res10 = getRes($p10, 2);
$pV = $res10[0];
$pW = $res10[1];

// Simular relación social con banda "amigo" (social value >= 40)
RelacionBitacora::registrar($p10, RelacionBitacora::SE_CONOCIERON, [$pV, $pW]);
$sorted10 = [$pV, $pW];
sort($sorted10, SORT_STRING);
$socId = 'soc_' . $sorted10[0] . '_' . $sorted10[1];
$p10['relaciones_sociales'] = [
    [
        'id' => $socId,
        'persona_a' => $sorted10[0],
        'persona_b' => $sorted10[1],
        'conocidos' => true,
        'a_hacia_b' => ['valor' => 45, 'banda' => 'amigo'],
        'b_hacia_a' => ['valor' => 42, 'banda' => 'amigo'],
        'ultimo_contacto_calidad' => 'significativo',
    ],
];

ok(!hitoExiste($p10, 'hito_22'), 'T60. hito_22 no existe antes de confianza');
HistoriaConfianzaBridge::handle($p10, [
    'payload' => [
        'actores' => [$pV, $pW],
    ],
]);
ok(hitoExiste($p10, 'hito_22'), 'T61. hito_22 registrado con confianza alcanzada');

// ====================================================================
// === Vínculo bridge: hito_28, hito_30                             ===
// ====================================================================

$p11 = crearPartidaConTutorial();
$res11 = getRes($p11, 2);
$pX = $res11[0];
$pY = $res11[1];

RelacionBitacora::registrar($p11, RelacionBitacora::SE_CONOCIERON, [$pX, $pY]);
$sorted11 = [$pX, $pY];
sort($sorted11, SORT_STRING);
$socId2 = 'soc_' . $sorted11[0] . '_' . $sorted11[1];
$p11['relaciones_sociales'] = [
    [
        'id' => $socId2,
        'persona_a' => $sorted11[0],
        'persona_b' => $sorted11[1],
        'conocidos' => true,
        'a_hacia_b' => ['valor' => 85, 'banda' => 'mejor_amigo'],
        'b_hacia_a' => ['valor' => 80, 'banda' => 'mejor_amigo'],
    ],
];

ok(!hitoExiste($p11, 'hito_28'), 'T62. hito_28 no existe antes de vínculo');
ok(!hitoExiste($p11, 'hito_30'), 'T63. hito_30 no existe antes de vínculo');
HistoriaVinculoBridge::handle($p11, [
    'payload' => [
        'actores' => [$pX, $pY],
    ],
]);
ok(hitoExiste($p11, 'hito_28'), 'T64. hito_28 registrado con mejor_amigo');
ok(hitoExiste($p11, 'hito_30'), 'T65. hito_30 registrado con buen_amigo+');

// ====================================================================
// === Interés mutuo bridge: hito_04                                ===
// ====================================================================

$p12 = crearPartidaConTutorial();
$res12 = getRes($p12, 2);
$pZ = $res12[0];
$pAA = $res12[1];

RelacionBitacora::registrar($p12, RelacionBitacora::SE_CONOCIERON, [$pZ, $pAA]);

ok(!hitoExiste($p12, 'hito_04'), 'T66. hito_04 no existe antes de interés mutuo');
// Simular que ambos tienen señal romántica (flechazo en ambas direcciones)
$sorted12 = [$pZ, $pAA];
sort($sorted12, SORT_STRING);
$romId = 'rel_' . $sorted12[0] . '_' . $sorted12[1];
$p12['relaciones_romanticas'] = [
    [
        'id' => $romId,
        'persona_a' => $sorted12[0],
        'persona_b' => $sorted12[1],
        'a_hacia_b' => ['valor' => 15],
        'b_hacia_a' => ['valor' => 12],
        'flechazos' => [
            ['desde' => $pZ, 'hacia' => $pAA, 'dia' => 1],
            ['desde' => $pAA, 'hacia' => $pZ, 'dia' => 1],
        ],
    ],
];
$p12['relaciones_sociales'] = [
    [
        'id' => 'soc_' . $sorted12[0] . '_' . $sorted12[1],
        'persona_a' => $sorted12[0],
        'persona_b' => $sorted12[1],
        'conocidos' => true,
    ],
];

HistoriaInteresMutuoBridge::handle($p12, [
    'payload' => [
        'actores' => [$pZ, $pAA],
    ],
]);
ok(hitoExiste($p12, 'hito_04'), 'T67. hito_04 registrado con interés mutuo');

// ====================================================================
// === Verificar circuito común (registro → recompensa → pending)    ===
// ====================================================================

$p13 = crearPartidaConTutorial();
$res13 = getRes($p13, 3);
$protagonistas = array_slice($res13, 0, 3);

// Registrar un hito manualmente
$reg = HistoriaPuebloEngine::registrar($p13, 'hito_05', $protagonistas, ['origen' => 'test']);
ok($reg['ok'] === true, 'T68. registrar retorna ok');
ok(($reg['ya_existia'] ?? false) === false, 'T69. primer registro no es ya_existia');
ok(isset($reg['entrada']), 'T70. registro retorna entrada');
ok(($reg['entrada']['celebracion_estado'] ?? '') === 'pendiente', 'T71. celebracion_estado es pendiente');

// Verificar que aparece en celebraciones pendientes
$pendientes = HistoriaPuebloEngine::celebracionesPendientes($p13, $root, $p13['meta']['partida_id']);
$ids = array_column($pendientes, 'hito_id');
ok(in_array('hito_05', $ids, true), 'T72. hito_05 aparece en celebraciones pendientes');

// ACK
$ack = HistoriaPuebloEngine::ack($p13, 'hito_05');
ok($ack === true, 'T73. ack retorna true');

// Verificar que ya no está pendiente
$pendientes2 = HistoriaPuebloEngine::celebracionesPendientes($p13, $root, $p13['meta']['partida_id']);
$ids2 = array_column($pendientes2, 'hito_id');
ok(!in_array('hito_05', $ids2, true), 'T74. hito_05 ya no está pendiente tras ACK');

// Duplicación
$reg2 = HistoriaPuebloEngine::registrar($p13, 'hito_05', $protagonistas, ['origen' => 'test']);
ok(($reg2['ya_existia'] ?? false) === true, 'T75. segundo registro retorna ya_existia=true');
ok(contarHito($p13, 'hito_05') === 1, 'T76. no se duplica entrada');

// ====================================================================
// === hito_27: tentativa romántica fallida (2 caminos)              ===
// ====================================================================

// --- A) BESO rechazado → RECHAZO_IMPORTANTE + subtipo beso_rechazado ---
$p14 = crearPartidaConTutorial();
$res14 = getRes($p14, 2);
$p27A = $res14[0];
$p27B = $res14[1];

ok(!hitoExiste($p14, 'hito_27'), 'T77. hito_27 no existe antes de beso rechazado');
RelacionBitacora::registrar($p14, RelacionBitacora::RECHAZO_IMPORTANTE, [$p27A, $p27B], $p27A . '>' . $p27B, null, null, ['subtipo' => 'beso_rechazado']);
ok(hitoExiste($p14, 'hito_27'), 'T78. hito_27 registrado con beso rechazado');
ok(contarHito($p14, 'hito_27') === 1, 'T79. hito_27 exactamente 1 entrada');

// Idempotencia
RelacionBitacora::registrar($p14, RelacionBitacora::RECHAZO_IMPORTANTE, [$p27A, $p27B], $p27A . '>' . $p27B, null, null, ['subtipo' => 'beso_rechazado']);
ok(contarHito($p14, 'hito_27') === 1, 'T80. hito_27 idempotente (no duplica)');

// --- B) COQUETEAR fallido → INTENTO_ROMANTICO_FALLIDO + subtipo coquetear_fallido ---
$p15 = crearPartidaConTutorial();
$res15 = getRes($p15, 2);
$p27C = $res15[0];
$p27D = $res15[1];

ok(!hitoExiste($p15, 'hito_27'), 'T81. hito_27 no existe antes de coquetear fallido');
RelacionBitacora::registrar($p15, RelacionBitacora::INTENTO_ROMANTICO_FALLIDO, [$p27C, $p27D], $p27C . '>' . $p27D, null, null, ['subtipo' => 'coquetear_fallido']);
ok(hitoExiste($p15, 'hito_27'), 'T82. hito_27 registrado con coquetear fallido');
ok(contarHito($p15, 'hito_27') === 1, 'T83. hito_27 exactamente 1 entrada');

// --- C) RECHAZO_IMPORTANTE sin subtipo NO registra hito_27 ---
$p16 = crearPartidaConTutorial();
$res16 = getRes($p16, 2);
$p27E = $res16[0];
$p27F = $res16[1];

RelacionBitacora::registrar($p16, RelacionBitacora::RECHAZO_IMPORTANTE, [$p27E, $p27F], $p27E . '>' . $p27F);
ok(!hitoExiste($p16, 'hito_27'), 'T84. RECHAZO_IMPORTANTE sin subtipo NO registra hito_27');

// --- D) INTENTO_ROMANTICO_FALLIDO sin subtipo NO registra hito_27 ---
$p17 = crearPartidaConTutorial();
$res17 = getRes($p17, 2);
$p27G = $res17[0];
$p27H = $res17[1];

RelacionBitacora::registrar($p17, RelacionBitacora::INTENTO_ROMANTICO_FALLIDO, [$p27G, $p27H], $p27G . '>' . $p27H);
ok(!hitoExiste($p17, 'hito_27'), 'T85. INTENTO_ROMANTICO_FALLIDO sin subtipo NO registra hito_27');

// ====================================================================
// === hito_08 / hito_09: HITO_ROMANTICO con subtipo                 ===
// ====================================================================

// --- hito_08: HITO_ROMANTICO + subtipo beso ---
$p18 = crearPartidaConTutorial();
$res18 = getRes($p18, 2);
$p08A = $res18[0];
$p08B = $res18[1];

ok(!hitoExiste($p18, 'hito_08'), 'T86. hito_08 no existe antes de HITO_ROMANTICO beso');
RelacionBitacora::registrar($p18, RelacionBitacora::HITO_ROMANTICO, [$p08A, $p08B], $p08A . '>' . $p08B, null, null, ['subtipo' => 'beso']);
ok(hitoExiste($p18, 'hito_08'), 'T87. hito_08 registrado con HITO_ROMANTICO + beso');

// --- hito_09: HITO_ROMANTICO + subtipo coquetear ---
$p19 = crearPartidaConTutorial();
$res19 = getRes($p19, 2);
$p09A = $res19[0];
$p09B = $res19[1];

ok(!hitoExiste($p19, 'hito_09'), 'T88. hito_09 no existe antes de HITO_ROMANTICO coquetear');
RelacionBitacora::registrar($p19, RelacionBitacora::HITO_ROMANTICO, [$p09A, $p09B], $p09A . '>' . $p09B, null, null, ['subtipo' => 'coquetear']);
ok(hitoExiste($p19, 'hito_09'), 'T89. hito_09 registrado con HITO_ROMANTICO + coquetear');

// --- HITO_ROMANTICO sin subtipo NO registra hito_08 ni hito_09 ---
$p20 = crearPartidaConTutorial();
$res20 = getRes($p20, 2);
$p08C = $res20[0];
$p08D = $res20[1];

RelacionBitacora::registrar($p20, RelacionBitacora::HITO_ROMANTICO, [$p08C, $p08D], $p08C . '>' . $p08D);
ok(!hitoExiste($p20, 'hito_08'), 'T90. HITO_ROMANTICO sin subtipo NO registra hito_08');
ok(!hitoExiste($p20, 'hito_09'), 'T91. HITO_ROMANTICO sin subtipo NO registra hito_09');

// ====================================================================
// === hito_31: continuidad de dúo via HistoriaPuebloEngine          ===
// ====================================================================

$p21 = crearPartidaConTutorial();
$res21 = getRes($p21, 2);
$p31A = $res21[0];
$p31B = $res21[1];

ok(!hitoExiste($p21, 'hito_31'), 'T92. hito_31 no existe antes');
$reg31 = HistoriaPuebloEngine::registrar($p21, 'hito_31', [$p31A, $p31B], ['origen' => 'encuentro_continuidad']);
ok(hitoExiste($p21, 'hito_31'), 'T93. hito_31 registrado');
ok(contarHito($p21, 'hito_31') === 1, 'T94. hito_31 exactamente 1 entrada');

// Idempotencia
HistoriaPuebloEngine::registrar($p21, 'hito_31', [$p31A, $p31B], ['origen' => 'encuentro_continuidad']);
ok(contarHito($p21, 'hito_31') === 1, 'T95. hito_31 idempotente (no duplica)');

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
