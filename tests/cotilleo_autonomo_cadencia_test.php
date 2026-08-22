<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CotilleoAutonomoCadencia;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\LugarAtributos;
use AquiHayTema\Engine\PartidaService;

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

function contarAutonomos(array $partida): int
{
    return CotilleoAutonomoCadencia::contarPublicadosHoy($partida);
}

function contarCitasCotilleo(array $partida): int
{
    $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
    $n = 0;
    foreach ($partida['buzon'] ?? [] as $m) {
        if (!is_array($m)) {
            continue;
        }
        if ((string) ($m['tipo'] ?? '') !== 'cotilleo') {
            continue;
        }
        if ((int) ($m['dia'] ?? 0) !== $hoy) {
            continue;
        }
        $n++;
    }
    return $n;
}

/**
 * @return array<string, mixed>
 */
function terminarAutonomo(
    array &$partida,
    PartidaService $service,
    string $residenteId,
    string $lugarId,
    int $hora,
    string $encId,
    ?string $emo = null
): array {
    if ($emo !== null) {
        EstadoEmocional::ensureResidente($partida['residentes'][$residenteId], $partida['reloj']);
        $partida['residentes'][$residenteId]['runtime']['estado_emocional']['id'] = $emo;
    }
    $attr = LugarAtributos::de($lugarId);
    $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
    $partida['encuentros'][] = [
        'id' => $encId,
        'tipo' => 'individual',
        'intencion' => 'autonomo',
        'participantes' => [$residenteId],
        'lugar' => $lugarId,
        'dia' => $dia,
        'hora' => $hora,
        'duracion_horas' => $attr['horas'],
        'duracion_minutos' => $attr['duracion_minutos'],
        'estado' => 'programado',
        'reserva_agenda' => ['tipo' => 'autonomo', 'origen' => 'npc_autonomo'],
    ];
    $partida['reloj']['hora_actual'] = $hora + max(1, (int) ($attr['horas'] ?? 1));
    EncuentroLifecycle::sincronizarConReloj($partida, null, $service->getCatalog());
    foreach ($partida['encuentros'] as $enc) {
        if (($enc['id'] ?? '') === $encId) {
            return $enc;
        }
    }
    return [];
}

$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'cadencia-auto');
$partida['features']['buzon_enabled'] = true;

ok(contarAutonomos($partida) === 0, '0 autónomos → 0 entradas');

terminarAutonomo($partida, $service, 'per_p003', 'lug_cine', 10, 'enc_a1');
ok(contarAutonomos($partida) === 1, '1 autónomo → 1 entrada');

terminarAutonomo($partida, $service, 'per_p004', 'lug_biblioteca', 12, 'enc_a2');
ok(contarAutonomos($partida) === 2, '2 autónomos → 2 entradas');

// Tercero con emoción: debe desplazar al más rutinario/neutro anterior si cupo lleno
$partida['residentes']['per_p001']['runtime']['perfil_partida']['lugares_preferentes'] = ['lug_cafeteria'];
terminarAutonomo($partida, $service, 'per_p001', 'lug_cafeteria', 8, 'enc_a0');
terminarAutonomo($partida, $service, 'per_p002', 'lug_bar', 14, 'enc_a3', 'enfadado');

ok(contarAutonomos($partida) === 2, '3+ autónomos → máximo 2 publicados');
$pub = CotilleoAutonomoCadencia::idsPublicadosHoy($partida);
ok(in_array('enc_a3', $pub, true), 'prioriza emoción distinta de neutro');
ok(!in_array('enc_a0', $pub, true), 'descarta rutina neutra ante mejor candidato');

// Cita relacional no cuenta en el cupo autónomo
$antesCitas = contarCitasCotilleo($partida);
$enc = $service->programarEncuentro($partida, ['per_p001', 'per_p002'], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa cita con cupo autónomo lleno');
$dia = (int) $enc['encuentro']['dia'];
$hora = (int) $enc['encuentro']['hora'];
$now = ((int) $partida['reloj']['dia_pueblo']) * 24 + (int) $partida['reloj']['hora_actual'];
$dur = max(1, (int) ($enc['encuentro']['duracion_horas'] ?? 1));
$service->avanzarRelojPasoAPaso($partida, max(1, $dia * 24 + $hora + $dur - $now));
ok(contarAutonomos($partida) === 2, 'cupo autónomo sigue en 2 tras cita');
ok(contarCitasCotilleo($partida) > $antesCitas, 'cita relacional aparece aunque cupo autónomo lleno');

// Persistencia save/load
$partidaId = (string) ($partida['meta']['partida_id'] ?? '');
ok($partidaId !== '', 'partida con id');
$service->guardar($partida);
$cargada = $service->cargar($partidaId);
ok(contarAutonomos($cargada) === 2, 'save/load conserva máximo 2 autónomos');
ok(count(CotilleoAutonomoCadencia::candidatosHoy($cargada)) >= 4, 'save/load conserva pool de candidatos');

$autos = array_values(array_filter($cargada['buzon'] ?? [], static fn($m) => is_array($m) && ($m['tipo'] ?? '') === CotilleoAutonomoCadencia::TIPO_BUZON));
foreach ($autos as $m) {
    ok(($m['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO, 'autónomo en canal cotilleo');
}

echo "\n--- Publicados ---\n";
foreach ($autos as $m) {
    echo (string) ($m['texto'] ?? '') . "\n";
}

exit($failures > 0 ? 1 : 0);
