<?php
declare(strict_types=1);

/*
 * R07 - CADENCIA B4 EN PUEBLOS PEQUENOS (curva aprobada).
 * cap_min=2 + impulso_cap_pequeno=1.25 SOLO cuando cap<=2.
 * Determinista: mismas semillas hash para ambas configs; sin tolerancias estadisticas.
 * No modifica calibracion: lee knobs con sus defaults y sobreescribe por $cal.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorPeticionesPueblo;

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

$cal = CalibracionConfig::load($root);
$calSinImpulso = $cal;
$calSinImpulso['peticiones_pueblo']['impulso_cap_pequeno'] = 1.0;

// --- 1) Suelo de cap -------------------------------------------------------
ok(PeticionPuebloEngine::capSimultaneas(3, $cal) === 2, 'cap n=3 -> 2');
ok(PeticionPuebloEngine::capSimultaneas(6, $cal) === 2, 'cap n=6 -> 2 (ceil ya era 2)');
ok(PeticionPuebloEngine::capSimultaneas(7, $cal) === 3, 'cap n=7 -> 3 (sin suelo)');
ok(PeticionPuebloEngine::capSimultaneas(24, $cal) === 8, 'cap n=24 -> 8 (intacto)');
ok(PeticionPuebloEngine::capSimultaneas(40, $cal) === 10, 'cap n=40 -> 10 (max intacto)');

// --- 2) Mundo lab pop 3 (cap=2): el impulso AMPLIA la aceptacion, nunca la encoge
$mundoBase = SimuladorPeticionesPueblo::partidaLab(3, new RngService('r07-lab-3'), $cal, 'E3');
$mundoBase['reloj']['dia_pueblo'] = 3;
unset($mundoBase['_b4_forzar_nacer']);

$nSeeds = 400;
$conImpulso = 0;
$sinImpulso = 0;
$nestingRoto = 0;
for ($s = 1; $s <= $nSeeds; $s++) {
    // Misma semilla hash en ambas configs -> misma primera tirada -> comparacion exacta.
    $seed = "r07-p3-$s";
    $a = $mundoBase;
    $rA = PeticionPuebloEngine::intentarNacer($a, $cal, new RngService($seed), null);
    $b = $mundoBase;
    $rB = PeticionPuebloEngine::intentarNacer($b, $calSinImpulso, new RngService($seed), null);
    if ($rB !== null) {
        $sinImpulso++;
        if ($rA === null) {
            $nestingRoto++;
        }
    }
    if ($rA !== null) {
        $conImpulso++;
    }
}
ok($nestingRoto === 0, "monotonia: todo nacimiento sin impulso sigue habiendo con impulso ($nestingRoto rotos)");
ok($sinImpulso > 20 && $sinImpulso < $nSeeds, "prueba con senal real sin impulso ($sinImpulso de $nSeeds)");
ok($conImpulso > $sinImpulso, "impulso genera mas nacimientos en pop 3 ($conImpulso vs $sinImpulso de $nSeeds)");
ok($conImpulso - $sinImpulso >= 5, 'el efecto es apreciable, no ruido (+' . ($conImpulso - $sinImpulso) . ')');

// --- 3) Mundo lab pop 8 (cap=3): el impulso NO cambia nada ------------------
$mundo8 = SimuladorPeticionesPueblo::partidaLab(8, new RngService('r07-lab-8'), $cal, 'E3');
$mundo8['reloj']['dia_pueblo'] = 3;
unset($mundo8['_b4_forzar_nacer']);
$diferenciasPop8 = 0;
$nacidosPop8 = 0;
for ($s = 1; $s <= $nSeeds; $s++) {
    $seed = "r07-p8-$s";
    $a = $mundo8;
    $rA = PeticionPuebloEngine::intentarNacer($a, $cal, new RngService($seed), null);
    $b = $mundo8;
    $rB = PeticionPuebloEngine::intentarNacer($b, $calSinImpulso, new RngService($seed), null);
    if (($rA !== null) !== ($rB !== null)) {
        $diferenciasPop8++;
    }
    if ($rA !== null) {
        $nacidosPop8++;
    }
}
ok($diferenciasPop8 === 0, "pop 8: resultado identico con y sin knob ($diferenciasPop8 diferencias)");
ok($nacidosPop8 > 20, "pop 8: la prueba no es vacua (nacidos=$nacidosPop8)");

// --- 4) El cap suelo se respeta en pipeline ---------------------------------
$mundoCap = SimuladorPeticionesPueblo::partidaLab(3, new RngService('r07-cap'), $cal, 'E3');
$mundoCap['reloj']['dia_pueblo'] = 3;
$mundoCap['_b4_forzar_nacer'] = true;
$nacidasForzadas = 0;
for ($i = 0; $i < 10; $i++) {
    $r = PeticionPuebloEngine::intentarNacer($mundoCap, $cal, new RngService('r07-cap-' . $i), null);
    if ($r === null) {
        break;
    }
    $nacidasForzadas++;
}
ok($nacidasForzadas === 2, "forzado se detiene en el cap (nacidas=$nacidasForzadas, cap=2)");
ok(count(PeticionPuebloEngine::abiertas($mundoCap)) === 2, 'dos abiertas simultaneas posibles');

echo "\n";
echo $failures === 0 ? "OK peticiones_cadencia_r07\n" : "FAIL peticiones_cadencia_r07 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
