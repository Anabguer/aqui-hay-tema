<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\PartidaService;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('test_fixtures_v0', 'pensamiento-modal-' . time());
$rid = array_key_first($partida['residentes'] ?? []);
ok(is_string($rid) && $rid !== '', 'partida con residente');

$reloj = &$partida['reloj'];
$reloj['dia_pueblo'] = 5;
$reloj['hora_actual'] = 19;

$desdeHoy = ['dia' => 5, 'hora' => 14];
$desdeAyer = ['dia' => 4, 'hora' => 23];
$desdeAntes = ['dia' => 3, 'hora' => 17];

// ─── NEUTRO ─────────────────────────────────────
$estadoNeutro = EstadoEmocional::estructura(EstadoEmocional::NEUTRO, null, 'inicial', $desdeHoy, null);
$resNeutro = EmocionalNarrativa::pensamientoModal($partida, $rid, $estadoNeutro);
ok($resNeutro === null, 'neutro: pensamientoModal devuelve null');

// ─── ALEGRE + CUMPLE ───────────────────────────
$estado = EstadoEmocional::estructura(EstadoEmocional::ALEGRE, null, 'cumple_felicidad', $desdeHoy, null);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'cumple: devuelve payload');
ok($res['texto_estado'] === 'Estoy alegre', 'cumple: texto_estado en 1ra persona');
ok(strpos($res['pensamiento'], 'palabras muy bonitas') !== false, 'cumple: pensamiento contiene dato real');
ok($res['desde_texto'] === 'Desde las 14:00', 'cumple: temporalidad mismo día HH:00');

// ─── ALEGRE + ENCONTRAR TRABAJO ────────────────
$estado = EstadoEmocional::estructura(EstadoEmocional::ALEGRE, null, 'encontrar_trabajo', $desdeAyer, null);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'encontrar_trabajo: devuelve payload');
ok($res['texto_estado'] === 'Estoy alegre', 'encontrar_trabajo: texto_estado');
ok(strpos($res['pensamiento'], 'encontrado trabajo') !== false, 'encontrar_trabajo: pensamiento contiene dato real');
ok($res['desde_texto'] === 'Desde ayer · 23:00', 'encontrar_trabajo: temporalidad ayer');

// ─── ALEGRE + CONSEJO CELESTINE ────────────────
$estado = EstadoEmocional::estructura(EstadoEmocional::ALEGRE, null, 'consejo_celestine', $desdeAntes, null);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'consejo: devuelve payload');
ok(strpos($res['pensamiento'], 'buen consejo') !== false, 'consejo: pensamiento contiene dato real');
ok($res['desde_texto'] === 'Desde hace 2 días · 17:00', 'consejo: temporalidad hace N días');

// ─── ALEGRE + HOBBY RECUPERACION ───────────────
$estado = EstadoEmocional::estructura(EstadoEmocional::ALEGRE, null, 'hobby_recuperacion', $desdeHoy, null);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'hobby: devuelve payload');
ok(strpos($res['pensamiento'], 'rato a lo mío') !== false, 'hobby: pensamiento contiene dato real');

// ─── ALEGRE + ENCUENTRO BIEN ───────────────────
$estado = EstadoEmocional::estructura(
    EstadoEmocional::ALEGRE,
    null,
    'encuentro',
    $desdeHoy,
    null,
    ['resultado_experiencia' => 'bien', 'encuentro_id' => 'enc_bien_01']
);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'encuentro_bien: devuelve payload');
ok(strpos($res['pensamiento'], 'buen rato') !== false, 'encuentro_bien: pensamiento positivo');

// ─── TRISTE + PERDER TRABAJO ───────────────────
$estado = EstadoEmocional::estructura(EstadoEmocional::TRISTE, null, 'perder_trabajo', $desdeHoy, null);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'perder_trabajo: devuelve payload');
ok($res['texto_estado'] === 'Estoy triste', 'perder_trabajo: texto_estado en 1ra persona');
ok(strpos($res['pensamiento'], 'soltado del trabajo') !== false, 'perder_trabajo: pensamiento contiene dato real');
ok(strpos($res['pensamiento'], 'moral por los suelos') !== false, 'perder_trabajo: tono proporcionado');

// ─── TRISTE + RECHAZO REPETIDO ─────────────────
$estado = EstadoEmocional::estructura(
    EstadoEmocional::TRISTE,
    null,
    'rechazo_repetido',
    $desdeHoy,
    null,
    ['hacia' => $rid]
);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'rechazo: devuelve payload');
ok(strpos($res['pensamiento'], 'respiro') !== false, 'rechazo: pensamiento contiene dato real');

// ─── TRISTE + ENCUENTRO MAL ────────────────────
$estado = EstadoEmocional::estructura(
    EstadoEmocional::TRISTE,
    null,
    'encuentro',
    $desdeHoy,
    null,
    ['resultado_experiencia' => 'mal', 'encuentro_id' => 'enc_mal_01']
);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'encuentro_mal: devuelve payload');
ok($res['texto_estado'] === 'Estoy triste', 'encuentro_mal: texto_estado');
ok(strpos($res['pensamiento'], 'mal rato') !== false || strpos($res['pensamiento'], 'bajón') !== false, 'encuentro_mal: tono proporcionado');

// ─── TRISTE + ENCUENTRO MUY MAL ────────────────
$estado = EstadoEmocional::estructura(
    EstadoEmocional::TRISTE,
    null,
    'encuentro',
    $desdeHoy,
    null,
    ['resultado_experiencia' => 'muy_mal', 'encuentro_id' => 'enc_muy_mal_01']
);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'encuentro_muy_mal: devuelve payload');
ok(strpos($res['pensamiento'], 'desastre') !== false || strpos($res['pensamiento'], 'bajón') !== false, 'encuentro_muy_mal: tono proporcionado');

// ─── ENFADADO + ORIGEN DESCONOCIDO ─────────────
$estado = EstadoEmocional::estructura(EstadoEmocional::ENFADADO, null, 'inicial', $desdeHoy, null);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok($res === null, 'origen_inicial_enfadado: devuelve null (sin pensamiento)');

// ─── TEMPORALIDAD: sin timestamp fiable ─────────
$estado = EstadoEmocional::estructura(EstadoEmocional::ALEGRE, null, 'cumple_felicidad', null, null);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'sin_desde: devuelve payload');
ok($res['desde_texto'] === null, 'sin_desde: temporalidad null');

// ─── TEMPORALIDAD: dia 0 ────────────────────────
$estado = EstadoEmocional::estructura(
    EstadoEmocional::ALEGRE,
    null,
    'cumple_felicidad',
    ['dia' => 0, 'hora' => 10],
    null
);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
ok(is_array($res), 'dia_0: devuelve payload');
ok($res['desde_texto'] === null, 'dia_0: temporalidad null');

// ─── VISTA MODAL ANIMO: verificar contrato ──────
$estado = EstadoEmocional::estructura(
    EstadoEmocional::TRISTE,
    null,
    'perder_trabajo',
    $desdeHoy,
    null
);
$vista = EmocionalNarrativa::vistaModalAnimo($partida, $rid, $estado);
ok(is_array($vista), 'vistaModalAnimo: devuelve payload');
ok(isset($vista['pensamiento']), 'vistaModalAnimo: incluye pensamiento');
ok(!isset($vista['explicacion']), 'vistaModalAnimo: sin explicacion');
ok(!isset($vista['consecuencias']), 'vistaModalAnimo: sin consecuencias');
ok(!isset($vista['consejo']), 'vistaModalAnimo: sin consejo');

// ─── VERIFICACIÓN: frases no inventan sentimientos ──
// Encuentro sin tendencia negativa no debe afirmar "me cae mal"
$estado = EstadoEmocional::estructura(
    EstadoEmocional::TRISTE,
    null,
    'encuentro',
    $desdeHoy,
    null,
    ['resultado_experiencia' => 'muy_mal', 'encuentro_id' => 'enc_sin_tendencia']
);
$res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
$fraseProhibida = 'me cae';
ok(strpos($res['pensamiento'], $fraseProhibida) === false, 'no inventa sentimiento "me cae" sin dato real');

// Formación pareja incluye nombre si hay pareja_id
$otroRid = null;
foreach ($partida['residentes'] as $k => $r) {
    if ($k !== $rid) { $otroRid = $k; break; }
}
if ($otroRid !== null) {
    $estado = EstadoEmocional::estructura(
        EstadoEmocional::ALEGRE,
        null,
        'formacion_pareja',
        $desdeHoy,
        null,
        ['pareja_id' => $otroRid]
    );
    $res = EmocionalNarrativa::pensamientoModal($partida, $rid, $estado);
    ok(strpos($res['pensamiento'], 'relación') !== false, 'formacion_pareja: pensamiento menciona relación');
}

echo "pensamiento_modal_test: todo OK\n";
