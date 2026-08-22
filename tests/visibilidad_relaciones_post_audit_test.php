<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CotilleoNarrativo;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroExperienciaNarrativa;
use AquiHayTema\Engine\EtiquetaRelacionPlay;
use AquiHayTema\Engine\HayTema;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionNarrativaBridge;
use AquiHayTema\Engine\RelacionVistaJugador;
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

DomainBootstrap::boot();
$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$p = $service->nuevaPartida('playtest_01', 'vis-rel-post-audit');
$a = 'per_p001';
$b = 'per_p002';
$c = 'per_p003';
$lugar = 'lug_cine';

// 1–2. Social A→B ≠ B→A y barra distinta para valores distintos
RelacionEngine::upsertSocial($p, $a, $b, 'conocidos', 2, true);
RelacionEngine::upsertSocial($p, $b, $a, 'conocidos', 16, true);

$va = RelacionVistaJugador::de($p, $a, $b, $cal);
$vb = RelacionVistaJugador::de($p, $b, $a, $cal);

ok($va['social_valor'] === 2 && $vb['social_valor'] === 16, 'social A→B y B→A distintos en ficha');
ok($va['social_bar_pct'] !== $vb['social_bar_pct'], 'barra social distinta para valores 2 vs 16');
ok(
    EtiquetaRelacionPlay::barraSocialPct(2, true) !== EtiquetaRelacionPlay::barraSocialPct(16, true),
    'barraSocialPct motor distingue 2 y 16'
);

// 3–4. Flechazo unilateral: romance solo en dirección correcta; copy unilateral
RelacionEngine::setRomanceHacia($p, $a, $b, 12);
RelacionBitacora::registrar($p, RelacionBitacora::FLECHAZO, [$a, $b], $a . '>' . $b);

$vaRom = RelacionVistaJugador::de($p, $a, $b, $cal);
$vbRom = RelacionVistaJugador::de($p, $b, $a, $cal);
ok($vaRom['romance_visible'] === true, 'A→B romance visible en ficha de A');
ok($vbRom['romance_visible'] === false, 'B→A sin romance visible en ficha de B');
ok(SenalRomantica::desdeHacia($p, $a, $b, $cal)['ok'] === true, 'señal A→B');
ok(SenalRomantica::desdeHacia($p, $b, $a, $cal)['ok'] === false, 'señal B→A no');

$pCopy = $service->nuevaPartida('playtest_01', 'vis-rel-copy');
RelacionEngine::upsertSocial($pCopy, $a, $b, 'conocidos', 5, true);
RelacionEngine::upsertSocial($pCopy, $b, $a, 'conocidos', 5, true);
RelacionEngine::setRomanceHacia($pCopy, $a, $b, 12);
$bzAntes = count($pCopy['buzon'] ?? []);
RelacionBitacora::registrar($pCopy, RelacionBitacora::FLECHAZO, [$a, $b], $a . '>' . $b);
$msg = null;
foreach ($pCopy['buzon'] ?? [] as $m) {
    if (is_array($m) && ($m['hito_tipo'] ?? '') === RelacionBitacora::FLECHAZO) {
        $msg = $m;
    }
}
$texto = (string) ($msg['texto'] ?? '');
ok($texto !== '', 'copy flechazo generado');
ok(!preg_match('/\by\b.*\by\b.*están por otro carril/i', $texto), 'copy unilateral no parece mutuo genérico');
ok(str_contains($texto, 'fija') || str_contains($texto, 'interés') || str_contains($texto, '…'), 'copy unilateral menciona interés');

// 5. HayTema: dos pares en mismo lugar sin trío
$pTema = $service->nuevaPartida('playtest_01', 'vis-rel-tema');
$pTema['reloj']['dia_pueblo'] = 3;
$pTema['historial_coincidencias'] = [];
for ($d = 1; $d <= 3; $d++) {
    $pTema['historial_coincidencias'][] = [
        'dia' => $d,
        'lugar_id' => $lugar,
        'residentes' => [$a, $b],
    ];
    $pTema['historial_coincidencias'][] = [
        'dia' => $d,
        'lugar_id' => $lugar,
        'residentes' => [$b, $c],
    ];
}
$presentes = [$a, $b, $c];
$ta = HayTema::de($pTema, $a, $lugar, $presentes);
$tb = HayTema::de($pTema, $b, $lugar, $presentes);
$tc = HayTema::de($pTema, $c, $lugar, $presentes);
ok($ta['hay_tema'] && $tb['hay_tema'] && $tc['hay_tema'], 'hay tema para los tres');
ok(
    ($ta['tema_id'] ?? '') !== ($tc['tema_id'] ?? ''),
    'Diana↔Raúl y Raúl↔Benito no fusionan tema_id'
);
ok(
    str_contains((string) ($ta['tema_id'] ?? ''), CotilleoNarrativo::clavePar([$a, $b], $lugar))
    || str_contains((string) ($tb['tema_id'] ?? ''), CotilleoNarrativo::clavePar([$a, $b], $lugar)),
    'tema A-B por par'
);
ok(
    str_contains((string) ($tc['tema_id'] ?? ''), CotilleoNarrativo::clavePar([$b, $c], $lugar)),
    'tema B-C por par distinto'
);
$txtA = (string) (($ta['tema_vista']['texto'] ?? '') ?: '');
$txtC = (string) (($tc['tema_vista']['texto'] ?? '') ?: '');
if ($txtA !== '' && $txtC !== '') {
    ok($txtA !== $txtC || ($ta['tema_id'] ?? '') !== ($tc['tema_id'] ?? ''), 'textos/temas no trío grupal');
}

// 6. Encuentro malo conserva causa narrativa real
$pEnc = $service->nuevaPartida('playtest_01', 'vis-rel-enc');
RelacionEngine::upsertSocial($pEnc, $a, $b, 'conocidos', 5, true);
RelacionEngine::upsertSocial($pEnc, $b, $a, 'conocidos', 5, true);
$pEnc['residentes'][$a]['runtime']['estado_emocional'] = ['id' => 'enfadado'];
$exp = [
    'participantes' => [$a, $b],
    'por_participante' => [
        $a => ['resultado' => 'mal', 'carga' => -0.4],
        $b => ['resultado' => 'normal', 'carga' => 0.0],
    ],
    'factores' => [
        'compat_ab' => ['total' => 25],
        'compat_ba' => ['total' => 30],
        'quimica' => ['a_hacia_b' => 70],
        'conflicto' => 6,
        'emocional_a' => 'enfadado',
        'emocional_b' => 'neutro',
        'social_ab' => ['valor' => 10],
        'romance_ab' => 0,
        'plan_a' => ['aporte' => 0, 'penalizacion' => 2],
        'plan_b' => ['aporte' => 1, 'penalizacion' => 0],
    ],
];
$enc = ['participantes' => [$a, $b], 'lugar' => $lugar, 'tipo' => 'quedar'];
$narr = EncuentroExperienciaNarrativa::de($pEnc, $enc, $exp, $cal);
ok($narr !== null, 'experiencia narrativa para encuentro malo');
ok(($narr['causa_principal'] ?? '') !== '', 'causa_principal persistida');
ok(($narr['texto'] ?? '') !== '', 'texto narrativo derivado');
ok(is_array($narr['factores_relevantes'] ?? null), 'factores relevantes');

// 7. Misión primera_cita: quedar no cuenta; primera_cita sí
$mision = ['plantilla_id' => 'primera_cita_hoy', 'params' => []];
$encQuedar = ['tipo' => 'quedar', 'participantes' => [$a, $b], 'lugar' => $lugar];
$encPrimera = ['tipo' => PropuestaNivel::PRIMERA_CITA, 'participantes' => [$a, $b], 'lugar' => $lugar];
ok(!MisionDiariaEngine::encaja($mision, $encQuedar), 'quedar en cine NO completa primera_cita_hoy');
ok(MisionDiariaEngine::encaja($mision, $encPrimera), 'primera_cita sí completa primera_cita_hoy');

echo "\n" . ($failures === 0 ? 'ALL OK' : "FAILURES: $failures") . "\n";
exit($failures > 0 ? 1 : 0);
