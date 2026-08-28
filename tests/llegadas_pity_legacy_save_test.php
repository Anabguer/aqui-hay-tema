<?php
declare(strict_types=1);

/**
 * Save legacy sin contador pity (p. ej. día 10 / N=3): backfill seguro + oferta por motor.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\TutorialIncorporaciones;
use AquiHayTema\Engine\TutorialPrimerosPasos;

$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'legacy-pity-d10');

$pareja = $p['tutorial']['pareja_mision1'] ?? null;
ok(is_array($pareja), 'fixture: pareja tutorial');
$a = (string) ($pareja['a'] ?? '');
$b = (string) ($pareja['b'] ?? '');
PropuestaEncuentroEngine::proponer($p, [$a, $b], 1, 18, PropuestaNivel::PRESENTAR, 'lug_cafeteria');
$mid = (string) ($p['tutorial']['mensajito_id'] ?? '');
BuzonEngine::marcarLeido($p, $mid);
TutorialPrimerosPasos::alLeerMensaje($p, $mid, new \AquiHayTema\Engine\Catalog($root));
$tercero = (string) ($p['tutorial']['tercero'] ?? '');
PropuestaEncuentroEngine::proponer($p, [$tercero], 1, 19, 'individual', 'lug_cine');
for ($h = 0; $h < 14; $h++) {
    $svc->avanzarReloj($p, 1);
}

ok(count(TutorialIncorporaciones::residentesActivos($p)) === 3, 'fixture base: 3 residentes');
ok(($p['llegadas']['modo'] ?? '') === 'normal', 'fixture base: modo normal');

// Simular save pre-pity: día 10, sin contadores nuevos
$p['reloj']['dia_pueblo'] = 10;
$p['reloj']['hora_actual'] = 16;
$p['llegadas']['cooldown_hasta_dia'] = 0;
$p['llegadas']['candidato_activo'] = null;
$p['llegadas']['en_camino'] = null;
$p['llegadas']['historial'] = [];
unset(
    $p['llegadas']['dias_sin_oferta'],
    $p['llegadas']['ultimo_dia_intento_pity'],
    $p['llegadas']['_pity_legacy_backfill_v1']
);

$nAntes = count(TutorialIncorporaciones::residentesActivos($p));
CandidatoLlegadaEngine::ensure($p);

ok(isset($p['llegadas']['dias_sin_oferta']), 'ensure inicializa dias_sin_oferta');
ok((int) $p['llegadas']['dias_sin_oferta'] >= 3, 'backfill legacy: pity listo (d10/N=3)');
ok(CandidatoLlegadaEngine::forzarOfertaPorPity($nAntes, (int) $p['llegadas']['dias_sin_oferta']), 'backfill activa umbral pity');
ok(!empty($p['llegadas']['_pity_legacy_backfill_v1']), 'backfill marcado idempotente');

$diasTras = (int) $p['llegadas']['dias_sin_oferta'];
CandidatoLlegadaEngine::ensure($p);
ok((int) $p['llegadas']['dias_sin_oferta'] === $diasTras, 'segunda ensure no re-backfill');

$tick = CandidatoLlegadaEngine::tick($p, $root, null, 24);
ok($tick['ofrecidos'] !== null, 'motor ofrece candidato tras backfill (sin insertar residente)');
ok(count(TutorialIncorporaciones::residentesActivos($p)) === $nAntes, 'población intacta hasta aceptar');
ok(is_array($p['llegadas']['candidato_activo'] ?? null), 'candidato activo en buzón');

echo $fail === 0 ? "OK llegadas_pity_legacy_save_test\n" : "FAIL llegadas_pity_legacy_save_test ($fail)\n";
exit($fail > 0 ? 1 : 0);
