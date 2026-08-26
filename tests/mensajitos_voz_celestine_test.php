<?php
declare(strict_types=1);

/*
 * GATES DEL CONTRATO NARRATIVO DE MENSAJITOS (docs/BUZON_DE_CELESTINE.md).
 *
 * Canal Mensajitos (buzon): primera persona, un vecino escribiéndole a
 * Celestine. Nunca log, nunca aviso de sistema, nunca cotilleo en 3.ª persona.
 * Canal Cotilleos: narración pública del pueblo (3.ª persona correcta ahí).
 *
 * No modifica mecánicas: solo comprueba CÓMO se comunica cada hecho.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\MarchaEngine;
use AquiHayTema\Engine\MensajitoVoz;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionFeedback;
use AquiHayTema\Engine\PeticionPlantillas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionNarrativaBridge;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\VidaNarrativaBridge;

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

/** minúsculas sin depender de mbstring (ASCII + acentos españoles). */
function baj(string $s): string
{
    return strtr(strtolower($s), [
        'Á' => 'á', 'À' => 'à', 'Ä' => 'ä',
        'É' => 'é', 'È' => 'è', 'Ë' => 'ë',
        'Í' => 'í', 'Ì' => 'ì', 'Ï' => 'ï',
        'Ó' => 'ó', 'Ò' => 'ò', 'Ö' => 'ö',
        'Ú' => 'ú', 'Ù' => 'ù', 'Ü' => 'ü',
        'Ñ' => 'ñ', 'Ç' => 'ç',
    ]);
}

/** Frases de informe de sistema / cotilleo prohibidas en el canal Mensajitos. */
function frasesProhibidasBuzon(): array
{
    return [
        'se ha quedado sin respuesta',
        'no se hizo nada',
        'encargo cumplido',
        'está que se sale',
        'pasabas de su mensajito',
        'le ha sentado regular que ignoraras',
        'quiere hablar contigo',
        'te ha dejado un recado',
        'está pensando en marcharse del pueblo',
        'ha decidido darle otra oportunidad al pueblo',
        'ya está en el pueblo. tiene vivienda',
        'estamos esperando a ',
        'le han soltado del trabajo',
        'se le ha pasado el arroz', // 3.ª persona sobre el NPC
        'mirando el reloj',
        'olor a plantón',
    ];
}

/** Jerga técnica que no debe llegar jamás al texto del jugador. */
function jergaProhibida(): array
{
    return ['pet_', 'msg_', '_lab', 'schema', 'placeholder', 'lab_r', 'per_', 'lug_'];
}

/** Gate genérico para un mensaje del canal buzón. */
function cumpleContrato(array $m, array $ctx = []): bool
{
    $texto = baj((string) ($m['texto'] ?? ''));
    if (trim($texto) === '') {
        return false;
    }
    if ((string) ($m['canal'] ?? BuzonEngine::canalDe((string) ($m['clasificacion'] ?? ''))) !== BuzonEngine::CANAL_BUZON) {
        return false;
    }
    foreach (frasesProhibidasBuzon() as $f) {
        if (strpos($texto, baj($f)) !== false) {
            return false;
        }
    }
    foreach (jergaProhibida() as $j) {
        if (strpos($texto, $j) !== false) {
            return false;
        }
    }
    if (strpos((string) ($m['texto'] ?? ''), '·') !== false) {
        return false; // separador estilo informe del sistema antiguo
    }
    if (!empty($ctx['de_persona']) && (string) ($m['de_persona'] ?? '') !== (string) $ctx['de_persona']) {
        return false;
    }
    return true;
}

function crearPetRender(array &$p, string $rid, string $plantillaId, array $params): array
{
    $pl = PeticionPlantillas::porId($plantillaId);
    $texto = (string) ($pl['copy'] ?? 'pet');
    if (isset($params['lugar_id'])) {
        $texto = str_replace('{lugar}', 'el cine', $texto);
    }
    if (isset($params['otro'])) {
        $texto = str_replace('{otro}', \AquiHayTema\Engine\IdentidadPublica::nombre($p, (string) $params['otro']), $texto);
    }
    $r = PeticionEngine::crear($p, $rid, (string) ($pl['tipo_legado'] ?? 'otro'), [
        'schema_b4' => true,
        'plantilla_id' => $plantillaId,
        'familia' => (string) ($pl['familia'] ?? ''),
        'params' => $params,
        'texto' => $texto,
        'hecho' => (string) ($pl['hecho'] ?? ''),
        'peso' => (string) ($pl['peso'] ?? PeticionEsquemas::PESO_FACIL),
        'exigencia' => (int) ($pl['exigencia'] ?? 0),
        'plazo_horas' => (int) ($pl['plazo_horas'] ?? 24),
        'cuenta_latido' => false,
        '_placeholder_copy' => false,
    ], null);
    return $r['peticion'] ?? [];
}

/** Último mensaje del buzón (el recién creado). */
function ultimoMsg(array $p): array
{
    $n = count($p['buzon']);
    return $n > 0 ? $p['buzon'][$n - 1] : [];
}

function partidaJuegoV1(string $root, string $seed): array
{
    Reloj::fijarAhora(new DateTimeImmutable(Reloj::TEST_AHORA, Reloj::zona()));
    DomainBootstrap::resetForTests();
    DomainBootstrap::boot();
    $service = new \AquiHayTema\Engine\PartidaService($root);
    $p = $service->nuevaPartida('juego_v1', $seed, ['fecha' => '2026-08-17', 'hora' => 8]);
    // Tutorial simplificado: basta con residentes activos y feature flags; no
    // hace falta celebrar los encuentros M1-M3 para las gates de copy.
    $p['tutorial']['activo'] = false;
    $p['tutorial']['jugable_completado'] = true;
    return [$service, $p];
}

// ============================================================================
echo "== 1) Petición normal (ir_al_lugar): nace en 1.ª persona, canal buzón ==\n";
[$service, $p] = partidaJuegoV1($root, 'voz-a-0');
$ridA = '';
foreach ($p['residentes'] as $idR => $rR) {
    if (($rR['presencia'] ?? '') === 'residente') {
        $ridA = (string) $idR;
        break;
    }
}
ok($ridA !== '', 'hay residente activo');
$pA = crearPetRender($p, $ridA, 'ir_al_lugar', ['lugar_id' => 'lug_cine']);
$msgA = ultimoMsg($p);
ok(($msgA['canal'] ?? '') === BuzonEngine::CANAL_BUZON, 'canal buzon');
ok(cumpleContrato($msgA, ['de_persona' => $ridA]), 'contrato narrativo general');
ok(strpos((string) $msgA['texto'], 'Me apetece ir a') === 0, 'primera persona ("Me apetece ir a…")');
ok(strpos((string) $msgA['texto'], ': ') === false || strpos((string) $msgA['texto'], "\n") !== false, 'sin prefijo "Nombre:" de log');
ok(preg_match('/Te quedan \d+ h$/', (string) $msgA['texto']) === 1, 'plazo canónico dirigido a Celestine');

echo "\n== 2) Conocer a alguien / quedar con alguien / actividad ==\n";
$pB = crearPetRender($p, $ridA, 'conocer_a_alguien', []);
$msgB = ultimoMsg($p);
ok(strpos((string) $msgB['texto'], 'Me gustaría conocer a alguien.') === 0, 'conocer: 1.ª persona');
ok(cumpleContrato($msgB, ['de_persona' => $ridA]), 'conocer: contrato general');

$ridC = '';
foreach ($p['residentes'] as $idR => $rR) {
    if ((string) $idR !== $ridA && ($rR['presencia'] ?? '') === 'residente') {
        $ridC = (string) $idR;
        break;
    }
}
$pC = crearPetRender($p, $ridA, 'quedar_con_x', ['otro' => $ridC]);
$msgC = ultimoMsg($p);
ok(strpos((string) $msgC['texto'], 'Quiero quedar con') === 0, 'quedar: 1.ª persona');
ok(cumpleContrato($msgC, ['de_persona' => $ridA]), 'quedar: contrato general');

$pD = crearPetRender($p, $ridA, 'algo_distinto', ['lugar_id' => 'lug_parque']);
$msgD = ultimoMsg($p);
ok(strpos((string) $msgD['texto'], 'Necesito hacer algo distinto.') === 0, 'actividad: 1.ª persona');
ok(cumpleContrato($msgD, ['de_persona' => $ridA]), 'actividad: contrato general');

echo "\n== 3) Éxito: el peticionario AGRADECE a Celestine, sin informe de sistema ==\n";
$cal = CalibracionConfig::load($root);
$rCum = PeticionPuebloEngine::cumplir($p, (string) $pD['id'], $cal, null);
ok(!empty($rCum['ok']), 'cumplir() ok');
$msgCum = ultimoMsg($p);
ok(($msgCum['tipo'] ?? '') === 'peticion_resultado', 'resultado presente');
ok(cumpleContrato($msgCum, ['de_persona' => $ridA]), 'cumplida: contrato general');
$txCum = baj((string) $msgCum['texto']);
ok(strpos($txCum, 'gracias') !== false || strpos($txCum, 'se pudo') !== false || strpos($txCum, 'me vino') !== false
    || strpos($txCum, 'necesitaba') !== false || strpos($txCum, 'encantó') !== false, 'suena a agradecimiento del NPC');
ok(($p['buzon'][count($p['buzon']) - 2]['estado'] ?? '') === 'resuelto', 'mensajito original cerrado como resuelto');

echo "\n== 4) Caducidad: EL MISMO NPC cierra su propio mensaje ==\n";
$pE = crearPetRender($p, $ridA, 'salir_de_casa', []);
Reloj::avanzarHoras($p, 25);
ok(PeticionEngine::caducarVencidas($p) >= 1, 'caduca por reloj de juego');
$nFallo = PeticionPuebloEngine::aplicarFalloPendiente($p, $cal, null);
ok($nFallo >= 1, 'fallo aplicado');
$msgCad = ultimoMsg($p);
ok(($msgCad['tipo'] ?? '') === 'peticion_resultado', 'eco de caducidad presente');
ok(cumpleContrato($msgCad, ['de_persona' => $ridA]), 'caducada: contrato general');
$txCad = baj((string) $msgCad['texto']);
$cierresValidos = ['olvídalo', 'déjalo', 'las ganas', 'por perdido', 'dejamos ahí', 'te escribo', 'esfumaron', 'zanjado', 'archivado', 'se acabó', 'quedó en nada'];
$esCierrePrimeraPersona = false;
foreach ($cierresValidos as $cv) {
    if (strpos($txCad, $cv) !== false) {
        $esCierrePrimeraPersona = true;
    }
}
ok($esCierrePrimeraPersona, "cierre en 1.ª persona del propio NPC (\"$txCad\")");
// El mensajito original queda cerrado (leido), reservando 'resuelto' para cumplidas.
$origE = null;
foreach ($p['buzon'] as $m) {
    if (($m['id'] ?? '') === ($pE['buzon_id'] ?? '')) {
        $origE = $m;
    }
}
ok(($origE['estado'] ?? '') === 'leido', 'original cerrado como leido (no resuelto)');

echo "\n== 5) Ignorada: tono distinto al de caducidad ==\n";
$pF = crearPetRender($p, $ridA, 'algo_distinto', ['lugar_id' => 'lug_parque']);
$rIg = PeticionEngine::ignorar($p, (string) $pF['id'], null);
ok(!empty($rIg['ok']), 'ignorar ok');
$msgIg = ultimoMsg($p);
ok(cumpleContrato($msgIg, ['de_persona' => $ridA]), 'ignorada: contrato general');

echo "\n== 6) Rechazo de tercero: sigue abierta, menciona al otro, plazo a Celestine ==\n";
$pG = crearPetRender($p, $ridA, 'quedar_con_x', ['otro' => $ridC]);
PeticionFeedback::alRechazoTercero($p, $pG, $ridC, null);
$msgTer = ultimoMsg($p);
ok(($msgTer['tipo'] ?? '') === 'peticion_resultado', 'eco rechazo tercero presente');
ok(cumpleContrato($msgTer, ['de_persona' => $ridA]), 'rechazo tercero: contrato general');
ok(strpos((string) $msgTer['texto'], \AquiHayTema\Engine\IdentidadPublica::nombre($p, $ridC)) !== false, 'menciona al tercero');
$txTer = baj((string) $msgTer['texto']);
ok(strpos($txTer, 'pendiente, no cancelado') !== false
    || strpos($txTer, 'otro día') !== false
    || strpos($txTer, 'luego') !== false
    || strpos($txTer, 'reintentemos') !== false, 'deja claro que sigue abierta');

echo "\n== 7) Variedad entre NPCs y determinismo ==\n";
$vistos = [];
for ($i = 0; $i < 12; $i++) {
    [, $pv] = partidaJuegoV1($root, 'voz-var-' . $i);
    $ridV = '';
    foreach ($pv['residentes'] as $idR => $rR) {
        if (($rR['presencia'] ?? '') === 'residente') {
            $ridV = (string) $idR;
            break;
        }
    }
    if ($ridV === '') {
        continue;
    }
    $pvx = crearPetRender($pv, $ridV, 'salir_de_casa', []);
    Reloj::avanzarHoras($pv, 30);
    PeticionEngine::caducarVencidas($pv);
    PeticionPuebloEngine::aplicarFalloPendiente($pv, CalibracionConfig::load($root), null);
    $mc = ultimoMsg($pv);
    if (($mc['tipo'] ?? '') === 'peticion_resultado' && cumpleContrato($mc, ['de_persona' => $ridV])) {
        $vistos[(string) $mc['texto']] = true;
    }
}
ok(count($vistos) >= 2, 'variedad: ≥2 redacciones distintas de caducidad (' . count($vistos) . ')');

// Determinismo: misma partida+seed ⇒ misma línea (CopyVariante es determinista).
[, $pd1] = partidaJuegoV1($root, 'voz-det-1');
[, $pd2] = partidaJuegoV1($root, 'voz-det-1');
$l1 = MensajitoVoz::linea($pd1, 'resultado_caducada', ['texto' => 'X'], 's-det', $ridA);
$l2 = MensajitoVoz::linea($pd2, 'resultado_caducada', ['texto' => 'X'], 's-det', $ridA);
ok($l1 === $l2 && $l1 !== '', 'determinista con misma seed');

echo "\n== 8) Bancos unitarios: candidato, marchas, bienvenida, tutorial ==\n";
[, $pu] = partidaJuegoV1($root, 'voz-unit-0');
$oferta = MensajitoVoz::linea($pu, 'candidato_oferta', ['nombre' => 'Bruno', 'dia' => 4], 'of|1', null);
ok((bool) preg_match('/(me llamo|soy) Bruno/i', $oferta), 'candidato_oferta firma el candidato: "' . $oferta . '"');
ok(strpos($oferta, 'quiere mudarse') === false, 'sin voz de sistema en oferta');
$camino = MensajitoVoz::linea($pu, 'candidato_en_camino', ['min' => 3, 's' => 's'], 'cam|1', null);
ok((bool) preg_match('/voy|pongo en camino/i', $camino) && strpos($camino, 'Estamos esperando') === false, 'en_camino lo dice el candidato');
$marcha = MensajitoVoz::linea($pu, 'marcha_intencion', [], 'mar|1', $ridA);
ok((bool) preg_match('/(estoy pensando|decírtelo|maletas|marcharme|irme)/i', $marcha), 'intención de marcha en 1.ª persona');
$queda = MensajitoVoz::linea($pu, 'marcha_se_queda', [], 'q|1', $ridA);
ok((bool) preg_match('/(me quedo|aquí sigo|me has convencido)/i', $queda), '"se queda" lo dice el NPC');
$bien = MensajitoVoz::linea($pu, 'bienvenida_bucle', [], 'b|1', $ridA);
ok(strpos($bien, 'te ha dejado un recado') === false && (bool) preg_match('/celestine/i', $bien), 'bienvenida directa al jugador');
$tut = MensajitoVoz::linea($pu, 'tutorial_primeros_pasos', ['nombre' => 'Ana'], 't|1', $ridA);
ok(strpos($tut, 'Nuevo plan') !== false, 'tutorial conserva instrucción funcional');
ok(strpos($tut, 'llevo un rato pensando') === false && (bool) preg_match('/me apetece ir al cine/i', $tut), 'tutorial en 1.ª persona');
$solo = MensajitoVoz::linea($pu, 'resultado_cumplida', ['otro' => ''], 'rc|vacio', $ridA);
ok($solo !== '' && strpos($solo, '{') === false, 'variantes sin tokens vacíos quedan limpias');
$conOtro = MensajitoVoz::linea($pu, 'resultado_cumplida', ['otro' => 'Ana'], 'rc|otro', $ridA);
ok(strpos($conOtro, 'Ana') !== false, 'variante con tercero usa su nombre');

echo "\n== 9) Marchas integradas: intención interactiva + cierre cotilleo intacto ==\n";
[, $pm] = partidaJuegoV1($root, 'voz-marcha-0');
$ridM = '';
foreach ($pm['residentes'] as $idR => $rR) {
    if (($rR['presencia'] ?? '') === 'residente') {
        $ridM = (string) $idR;
        break;
    }
}
$int = MarchaEngine::forzarIntencionDev($pm, $ridM, MarchaEngine::CAUSA_AISLAMIENTO);
$msgMi = ultimoMsg($pm);
ok(cumpleContrato($msgMi, ['de_persona' => $ridM]), 'marcha_intencion: contrato general');
ok(BuzonEngine::tieneDecisionPendiente(BuzonEngine::normalizar($msgMi)), 'sigue siendo interactivo (2 botones)');
ok(is_array(\AquiHayTema\Engine\MensajitoAcciones::vistaDe($msgMi['acciones'] ?? [], true))
    && count(\AquiHayTema\Engine\MensajitoAcciones::vistaDe($msgMi['acciones'] ?? [], true)) === 2, 'acciones_ui intactas');
$rQ = MarchaEngine::pedirQuedarse($pm, $root, (string) $msgMi['id'], null);
ok(!empty($rQ['ok']), 'pedirQuedarse ok');
$msgQ = ultimoMsg($pm);
ok(cumpleContrato($msgQ, ['de_persona' => $ridM]) && ($msgQ['tipo'] ?? '') === 'marcha_se_queda', '"se queda" en 1.ª persona');
// dejarIr genera cotilleo público: AHÍ sí es correcta la 3.ª persona.
MarchaEngine::forzarIntencionDev($pm, $ridM, MarchaEngine::CAUSA_CRISIS);
$rD = MarchaEngine::dejarIr($pm, $root, null, null);
ok(!empty($rD['ok']), 'dejarIr ok');
$msgPub = ultimoMsg($pm);
ok(($msgPub['clasificacion'] ?? '') === BuzonEngine::COTILLEO && ($msgPub['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO, 'marcha efectiva = canal COTILLEO (separación respetada)');

echo "\n== 10) Candidato integrado: oferta firmada + remitente visible en UI ==\n";
[, $pc] = partidaJuegoV1($root, 'voz-cand-0');
$pc['llegadas']['modo'] = 'normal';
$pc['llegadas']['normal_desde_dia'] = 1;
$pc['llegadas']['cooldown_hasta_dia'] = 0;
$pc['llegadas']['candidato_activo'] = null;
$pc['llegadas']['en_camino'] = null;
$ofrecido = null;
for ($i = 0; $i < 40 && $ofrecido === null; $i++) {
    $ofrecido = CandidatoLlegadaEngine::intentarOfrecer($pc, $root, null, 240);
}
if ($ofrecido === null) {
    ok(false, 'no hubo oferta de candidato en 40 intentos (revisar pool/flags)');
} else {
    $msgOf = null;
    foreach ($pc['buzon'] as $m) {
        if (($m['tipo'] ?? '') === CandidatoLlegadaEngine::TIPO_MSG) {
            $msgOf = $m;
        }
    }
    ok($msgOf !== null && cumpleContrato($msgOf), 'oferta de candidato: contrato general');
    ok($msgOf !== null && (string) ($msgOf['remitente_nombre'] ?? '') !== '', 'remitente_nombre presente para UI');
    ok($msgOf !== null && strpos((string) $msgOf['texto'], (string) ($msgOf['remitente_nombre'] ?? '#')) !== false, 'la oferta incluye su nombre');
    ok($msgOf !== null && is_array($msgOf['acciones'] ?? null) && count($msgOf['acciones']) === 2, 'decisiones intactas');
}

echo "\n== 11) Separación de canales: hito relacional = cotilleo, no Mensajito ==\n";
[, $ph] = partidaJuegoV1($root, 'voz-canales-0');
$idsRes = [];
foreach ($ph['residentes'] as $idR => $rR) {
    if (($rR['presencia'] ?? '') === 'residente') {
        $idsRes[] = (string) $idR;
    }
}
if (count($idsRes) < 2) {
    ok(false, 'faltan residentes para gate de canales');
} else {
    $nBuzonAntes = count($ph['buzon']);
    RelacionNarrativaBridge::alHito($ph, RelacionBitacora::SE_CONOCIERON, [$idsRes[0], $idsRes[1]], null);
    $msgHito = ultimoMsg($ph);
    ok(count($ph['buzon']) > $nBuzonAntes, 'hito publicado');
    ok(($msgHito['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO, 'hito va al canal cotilleo');
    ok(($msgHito['cotilleo_meta'] ?? null) !== null, 'hito lleva meta de cotilleo');
}
// Vida: acontecimiento IMPORTANTE (canal Mensajitos) debe sonar a 1.ª persona.
$item = ['visibilidad_jugador' => 'importante', 'importancia' => 'alta'];
$msgVida = VidaNarrativaBridge::alAcontecimiento(
    $ph,
    'perder_trabajo',
    [$idsRes[0] ?? $ridA],
    $item,
    [],
    null
);
if ($msgVida === null) {
    ok(false, 'vida: esperaba mensaje en canal Mensajitos para visibilidad importante');
} else {
    ok(($msgVida['canal'] ?? '') === BuzonEngine::CANAL_BUZON, 'vida importante = canal buzon');
    ok(stripos((string) $msgVida['texto'], 'me han soltado del trabajo') !== false, 'vida: 1.ª persona en Mensajitos');
    // Y con visibilidad cotilleosa, 3.ª persona correcta en su canal:
    $msgCoti = VidaNarrativaBridge::alAcontecimiento(
        $ph,
        'encontrar_trabajo',
        [$idsRes[0] ?? $ridA],
        ['visibilidad_jugador' => 'cotilleo', 'importancia' => 'relevante'],
        [],
        null
    );
    ok($msgCoti === null || (($msgCoti['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO), 'vida cotilleo = canal cotilleo');
}

echo "\n== 12) Sin filtración de información privada ==\n";
$catalog = new Catalog($root);
$ocultos = [];
foreach ($ph['residentes'] as $idR => $rR) {
    $cid = (string) ($rR['catalog_id'] ?? '');
    if ($cid === '' || !PoolCanonExiste($catalog, $cid)) {
        continue;
    }
    try {
        $ficha = $catalog->loadPersonaje($cid);
    } catch (\Throwable $e) {
        continue;
    }
    foreach (($ficha['vida']['rasgos_ocultos'] ?? []) as $ro) {
        if (is_string($ro) && mb_strlen($ro) > 3) {
            $ocultos[] = baj($ro);
        }
    }
    foreach (($ficha['romance']['preferencias_romanticas'] ?? []) as $pr) {
        if (is_array($pr) && empty($pr['visible']) && is_string($pr['texto'] ?? null) && mb_strlen((string) $pr['texto']) > 8) {
            // Un fragmento distintivo (primeras 5 palabras) de preferencias ocultas.
            $partes = preg_split('/\s+/', trim((string) $pr['texto']));
            if (is_array($partes) && count($partes) >= 5) {
                $ocultos[] = baj(implode(' ', array_slice($partes, 0, 5)));
            }
        }
    }
}
$fuga = false;
$fugaTxt = '';
foreach ($ph['buzon'] as $m) {
    $tx = baj((string) ($m['texto'] ?? ''));
    foreach ($ocultos as $o) {
        if ($o !== '' && strpos($tx, $o) !== false) {
            $fuga = true;
            $fugaTxt = (string) $m['texto'];
            break 2;
        }
    }
}
ok(!$fuga, 'ningún rasgo oculto ni preferencia privada en el buzón' . ($fuga ? ' (fuga: ' . $fugaTxt . ')' : ''));

echo "\n== 13) Persistencia save/load intacta ==\n";
$json = json_encode($ph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
ok($json !== false, 'partida serializable');
$p2 = json_decode((string) $json, true);
ok(is_array($p2) && count($p2['buzon']) === count($ph['buzon']), 'round-trip JSON conserva todos los mensajes');
$iguales = true;
foreach ($ph['buzon'] as $idx => $mOrig) {
    $mNew = $p2['buzon'][$idx] ?? null;
    if (!is_array($mNew)
        || ($mNew['id'] ?? '') !== ($mOrig['id'] ?? '')
        || ($mNew['texto'] ?? '') !== ($mOrig['texto'] ?? '')
        || ($mNew['estado'] ?? '') !== ($mOrig['estado'] ?? '')
        || ($mNew['estado_decision'] ?? '') !== ($mOrig['estado_decision'] ?? '')
        || ($mNew['de_persona'] ?? null) !== ($mOrig['de_persona'] ?? null)
        || ($mNew['canal'] ?? '') !== ($mOrig['canal'] ?? '')) {
        $iguales = false;
    }
}
ok($iguales, 'campos narrativos y mecánicos estables tras round-trip');
$list1 = BuzonEngine::listar($ph, null, null, BuzonEngine::CANAL_BUZON);
$list2 = BuzonEngine::listar($p2, null, null, BuzonEngine::CANAL_BUZON);
ok(count($list1) === count($list2), 'listado de Mensajitos idéntico tras carga');

echo "\n== 14) Gate global: TODO el canal buzón generado en esta suite pasa el contrato ==\n";
$mal = [];
foreach ([$p, $pm, $pc, $ph] as $px) {
    foreach ($px['buzon'] as $m) {
        if (!is_array($m)) {
            continue;
        }
        $canal = (string) ($m['canal'] ?? BuzonEngine::canalDe((string) ($m['clasificacion'] ?? '')));
        if ($canal !== BuzonEngine::CANAL_BUZON) {
            continue;
        }
        if (!cumpleContrato($m)) {
            $mal[] = (string) ($m['tipo'] ?? '?') . ' :: ' . (string) ($m['texto'] ?? '');
        }
    }
}
ok($mal === [], 'todos los Mensajitos generados cumplen el contrato' . ($mal !== [] ? ' — fallos: ' . implode(' | ', array_slice($mal, 0, 4)) : ''));

function PoolCanonExiste(Catalog $catalog, string $cid): bool
{
    try {
        $catalog->loadPersonaje($cid);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

echo "\n";
echo $failures === 0 ? "SUITE COMPLETA: 0 fallos\n" : "SUITE CON FALLOS: $failures\n";
exit($failures === 0 ? 0 : 1);
